<?php

namespace App\Http\Controllers\DepartmentAdmin;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\InternalSupplyRequest;
use App\Models\InternalSupplyRequestItem;
use App\Models\AuditLog;
use App\Models\Department;
use Illuminate\Support\Facades\DB;

use App\Services\StaffNotificationService;

class SupplyRequestControllerDepartmentAdmin extends BaseApiController
{
    public function __construct(
        private StaffNotificationService $notifications
    ) {}
    /**
     * POST /api/department-admin/supply-requests
     * Create a new request for medicines
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.drugId' => 'required|exists:drugs,id',
            'items.*.quantity' => 'required|integer|min:1',
            // notes يتم حفظها في audit_log فقط (لا يوجد عمود notes في الجدول)
            'notes' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $user = $request->user();

            // تحديد القسم المرتبط بالمستخدم (department) — طلبات القسم تعتمد على department_id دون pharmacy_id
            $departmentId = null;
            $departmentName = 'غير محدد';
            if ($user->type === 'department_admin' || $user->type === 'department_head') {
                $department = Department::where('head_user_id', $user->id)
                    ->where('hospital_id', $user->hospital_id)
                    ->first();
                if ($department) {
                    $departmentId = $department->id;
                    $departmentName = $department->name;
                } elseif ($user->department_id) {
                    $department = Department::where('hospital_id', $user->hospital_id)->find($user->department_id);
                    if ($department) {
                        $departmentId = $department->id;
                        $departmentName = $department->name;
                    }
                } elseif ($user->department) {
                    $departmentId = $user->department->id;
                    $departmentName = $user->department->name;
                }
            }

            if (!$departmentId) {
                throw new \Exception("لا يوجد قسم محدد لإنشاء الطلب منه. يرجى التأكد من ربط حسابك بقسم.");
            }

            // التحقق من أن المستشفى نشط
            $hospital = \App\Models\Hospital::find($user->hospital_id);
            if (!$hospital || $hospital->status !== 'active') {
                throw new \Exception("لا يمكن إنشاء طلب توريد في مستشفى معطل.");
            }

            // إنشاء الطلب — استخدام department_id فقط، pharmacy_id = null (طلبات القسم)
            $supplyRequest = InternalSupplyRequest::create([
                'pharmacy_id' => null,
                'department_id' => $departmentId,
                'requested_by' => $user->id,
                'status' => 'pending',
            ]);

            // دمج الأدوية المكررة قبل الحفظ (حماية إضافية)
            $mergedItems = [];
            foreach ($request->items as $item) {
                $drugId = $item['drugId'];
                if (isset($mergedItems[$drugId])) {
                    // إذا كان الدواء موجوداً مسبقاً، نضيف الكمية
                    $mergedItems[$drugId]['quantity'] += $item['quantity'];
                } else {
                    // إذا لم يكن موجوداً، نضيفه كعنصر جديد
                    $mergedItems[$drugId] = [
                        'drugId' => $drugId,
                        'quantity' => $item['quantity']
                    ];
                }
            }

            // حفظ العناصر المدمجة
            foreach ($mergedItems as $item) {
                $drug = \App\Models\Drug::findOrFail($item['drugId']);
                
                // Block Archived Drugs
                if ($drug->status === \App\Models\Drug::STATUS_ARCHIVED) {
                    throw new \Exception("لا يمكن طلب الدواء '{$drug->name}' لأنه مؤرشف وغير متاح للطلب.");
                }

                // Block Phasing Out Drugs if no warehouse stock
                if ($drug->status === \App\Models\Drug::STATUS_PHASING_OUT) {
                    $warehouseInventory = \App\Models\Inventory::where('drug_id', $drug->id)
                        ->whereNotNull('warehouse_id')
                        ->whereHas('warehouse', function($q) use ($user) {
                            $q->where('hospital_id', $user->hospital_id);
                        })
                        ->first();

                    if (!$warehouseInventory || $warehouseInventory->current_quantity <= 0) {
                        throw new \Exception("لا يمكن طلب الدواء '{$drug->name}' لأنه في مرحلة الإيقاف التدريجي ونفذ من المستودع.");
                    }
                }

                InternalSupplyRequestItem::create([
                    'request_id' => $supplyRequest->id,
                    'drug_id' => $item['drugId'],
                    'requested_qty' => $item['quantity'],
                    'approved_qty' => null,
                    'fulfilled_qty' => null,
                ]);
            }

            DB::commit();

            try {
                $this->notifications->notifyWarehouseNewInternalRequest($supplyRequest);
            } catch (\Exception $e) {
                \Log::error('Failed to notify warehouse manager', ['error' => $e->getMessage()]);
            }

            // تسجيل العملية في audit_log (بعد commit الناجح)
            try {
                $newValuesData = [
                    'request_id' => $supplyRequest->id,
                    'department_id' => $departmentId,
                    'department_name' => $departmentName,
                    'item_count' => count($request->items),
                    'notes' => $request->notes ?? null,
                ];
                
                \Log::info('Creating audit log for supply request', [
                    'request_id' => $supplyRequest->id,
                    'user_id' => $user->id,
                    'department_id' => $departmentId,
                    'new_values' => $newValuesData,
                ]);
                
                AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => $user->hospital_id,
                    'action' => 'department_create_supply_request',
                    'table_name' => 'internal_supply_request',
                    'record_id' => $supplyRequest->id,
                    'old_values' => null,
                    'new_values' => json_encode($newValuesData),
                    'ip_address' => $request->ip(),
                ]);
                
                \Log::info('Audit log created successfully', [
                    'request_id' => $supplyRequest->id,
                    'department_id' => $departmentId,
                ]);
            } catch (\Exception $e) {
                // في حالة فشل الـ logging، نستمر (لا نريد أن نفشل العملية بسبب الـ logging)
                \Log::error('Failed to log supply request creation', ['error' => $e->getMessage()]);
            }

            // تحميل العلاقات
            $supplyRequest->load('items.drug');

            return $this->sendSuccess([
                'id' => $supplyRequest->id,
                'requestNumber' => 'REQ-' . $supplyRequest->id,
                'status' => $this->translateStatus($supplyRequest->status),
                'itemCount' => $supplyRequest->items->count(),
                'created_at' => $supplyRequest->created_at->toIso8601String(),
            ], 'تم إنشاء طلب التوريد بنجاح.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('فشل في إنشاء طلب التوريد: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * ترجمة حالة الطلب
     */
    private function translateStatus($status)
    {
        $translations = [
            'pending' => 'قيد الانتظار',
            'approved' => 'قيد الاستلام',
            'rejected' => 'مرفوضة',
            'fulfilled' => 'تم الإستلام',
            'cancelled' => 'ملغاة',
        ];

        return $translations[$status] ?? $status;
    }
}
