<?php

namespace App\Http\Controllers\DepartmentAdmin;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\InternalSupplyRequest;
use App\Models\InternalSupplyRequestItem;
use App\Models\Pharmacy;
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
            $pharmacyId = null;

            // تحديد الصيدلية المرتبطة بالمستشفى
            if ($user->pharmacy_id) {
                $pharmacyId = $user->pharmacy_id;
            } elseif ($user->hospital_id) {
                $pharmacy = Pharmacy::where('hospital_id', $user->hospital_id)->first();
                $pharmacyId = $pharmacy ? $pharmacy->id : null;
            }

            // حل مؤقت للتجربة (يمكنك إزالته في الإنتاج)
            if (!$pharmacyId) {
                // محاولة جلب أول صيدلية في المستشفى
                $pharmacy = Pharmacy::where('hospital_id', $user->hospital_id)->first();
                $pharmacyId = $pharmacy ? $pharmacy->id : 1; // استخدام 1 كقيمة افتراضية للتجربة
            }

            if (!$pharmacyId) {
                throw new \Exception("لا توجد صيدلية محددة لإنشاء الطلب منها.");
            }

            // إنشاء الطلب (notes لا تُخزن في الجدول، تُحفظ فقط في الـ audit_log)
            $supplyRequest = InternalSupplyRequest::create([
                'pharmacy_id' => $pharmacyId,
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
                InternalSupplyRequestItem::create([
                    'request_id' => $supplyRequest->id,
                    'drug_id' => $item['drugId'],
                    // تخزين الكمية المطلوبة من department في requested_qty
                    // approved_qty: سيتم تعيينه من storekeeper عند الإرسال
                    // fulfilled_qty: سيتم تعيينه من department/pharmacist عند الاستلام
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

            // تحديد اسم القسم و department_id وقت إنشاء الطلب (لتجنب تغييره عند تغيير قسم المستخدم لاحقاً)
            $departmentName = 'غير محدد';
            $departmentId = null;
            if ($user->type === 'department_admin' || $user->type === 'department_head') {
                // أولاً: البحث عن القسم الذي يكون head_user_id = user->id
                $department = Department::where('head_user_id', $user->id)
                    ->where('hospital_id', $user->hospital_id)
                    ->first();
                if ($department) {
                    $departmentName = $department->name;
                    $departmentId = $department->id;
                    \Log::info('Department found via head_user_id', [
                        'user_id' => $user->id,
                        'department_id' => $departmentId,
                        'department_name' => $departmentName,
                    ]);
                } 
                // ثانياً: محاولة جلب القسم من department_id
                elseif ($user->department_id) {
                    $department = Department::where('hospital_id', $user->hospital_id)
                        ->find($user->department_id);
                    if ($department) {
                        $departmentName = $department->name;
                        $departmentId = $department->id;
                        \Log::info('Department found via user->department_id', [
                            'user_id' => $user->id,
                            'department_id' => $departmentId,
                            'department_name' => $departmentName,
                        ]);
                    }
                }
                // ثالثاً: محاولة جلب القسم من العلاقة
                elseif ($user->department) {
                    $departmentName = $user->department->name;
                    $departmentId = $user->department->id;
                    \Log::info('Department found via user->department relationship', [
                        'user_id' => $user->id,
                        'department_id' => $departmentId,
                        'department_name' => $departmentName,
                    ]);
                } else {
                    \Log::warning('No department found for user', [
                        'user_id' => $user->id,
                        'user_type' => $user->type,
                        'user->department_id' => $user->department_id ?? null,
                    ]);
                }
            }

            // تسجيل العملية في audit_log (بعد commit الناجح)
            try {
                $newValuesData = [
                    'request_id' => $supplyRequest->id,
                    'pharmacy_id' => $pharmacyId,
                    'item_count' => count($request->items),
                    'notes' => $request->notes ?? null, // ملاحظة department عند إنشاء الطلب
                    'department_name' => $departmentName, // اسم القسم وقت إنشاء الطلب (يُستخدم لتجنب تغييره لاحقاً)
                    'department_id' => $departmentId, // حفظ department_id من القسم الذي يديره المستخدم
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
