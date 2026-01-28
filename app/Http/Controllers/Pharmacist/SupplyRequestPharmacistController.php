<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\InternalSupplyRequest;
use App\Models\InternalSupplyRequestItem;
use App\Models\Pharmacy; // <--- إضافة موديل الصيدلية
use App\Models\AuditLog;
use App\Models\Drug;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;

use App\Services\StaffNotificationService;

class SupplyRequestPharmacistController extends BaseApiController
{
    public function __construct(
        private StaffNotificationService $notifications
    ) {}
    /**
     * Create a new internal supply request from Pharmacy to Warehouse/Admin.
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.drugId' => 'required|exists:drugs,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
            'supplier_id' => 'nullable|exists:suppliers,id'
        ]);

        DB::beginTransaction();
        try {
            $user = $request->user();
            $pharmacyId = null;

            // 1. تحديد الصيدلية الطالبة
            if ($user->pharmacy_id) {
                $pharmacyId = $user->pharmacy_id;
            } elseif ($user->hospital_id) {
                $pharmacy = Pharmacy::where('hospital_id', $user->hospital_id)->first();
                $pharmacyId = $pharmacy ? $pharmacy->id : null;
            }

            // حل مؤقت للتجربة (يمكنك إزالته في الإنتاج)
            if (!$pharmacyId) $pharmacyId = 1;

            if (!$pharmacyId) {
                throw new \Exception("لا توجد صيدلية محددة لإنشاء الطلب منها.");
            }

            // التحقق من أن المستشفى نشط
            $hospital = \App\Models\Hospital::find($user->hospital_id);
            if (!$hospital || $hospital->status !== 'active') {
                throw new \Exception("لا يمكن إنشاء طلب توريد في مستشفى معطل.");
            }

            $supplyRequest = new InternalSupplyRequest();
            
            // Use correct column names
            $supplyRequest->requested_by = $user->id;
            $supplyRequest->pharmacy_id = $pharmacyId; // <--- استخدام الصيدلية المستنتجة
            
            $supplyRequest->status = 'pending';
            $supplyRequest->save();

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
                $drug = Drug::find($item['drugId']);
                if (!$drug) {
                    throw new \Exception("الدواء غير موجود.");
                }

                if ($drug->status === Drug::STATUS_ARCHIVED) {
                    throw new \Exception("لا يمكن طلب الدواء '{$drug->name}' لأنه مؤرشف.");
                }

                if ($drug->status === Drug::STATUS_PHASING_OUT) {
                    // التحقق من مخزون المستودع
                    $warehouseInventory = Inventory::where('drug_id', $drug->id)
                        ->whereNotNull('warehouse_id')
                        ->whereHas('warehouse', function($q) use ($user) {
                            $q->where('hospital_id', $user->hospital_id);
                        })
                        ->first();

                    if (!$warehouseInventory || $warehouseInventory->current_quantity <= 0) {
                        throw new \Exception("لا يمكن طلب الدواء '{$drug->name}' لأنه في مرحلة الإيقاف التدريجي ونفذ من المستودع.");
                    }
                }

                $requestItem = new InternalSupplyRequestItem();
                $requestItem->request_id = $supplyRequest->id; 
                $requestItem->drug_id = $item['drugId'];
                $requestItem->requested_qty = $item['quantity']; 
                $requestItem->save();
            }

            DB::commit();

            try {
                $this->notifications->notifyWarehouseNewInternalRequest($supplyRequest);
            } catch (\Exception $e) {
                \Log::error('Failed to notify warehouse manager', ['error' => $e->getMessage()]);
            }

            // تحديد اسم الصيدلية وقت إنشاء الطلب (لتجنب تغييره عند تغيير صيدلية المستخدم لاحقاً)
            $pharmacyName = 'غير محدد';
            if ($pharmacyId) {
                $pharmacy = Pharmacy::find($pharmacyId);
                if ($pharmacy) {
                    $pharmacyName = $pharmacy->name;
                }
            }

            // تسجيل العملية في AuditLog مع الملاحظة
            try {
                AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => $user->hospital_id,
                    'action' => 'pharmacist_create_supply_request',
                    'table_name' => 'internal_supply_request',
                    'record_id' => $supplyRequest->id,
                    'old_values' => null,
                    'new_values' => json_encode([
                        'request_id' => $supplyRequest->id,
                        'pharmacy_id' => $pharmacyId,
                        'pharmacy_name' => $pharmacyName, // اسم الصيدلية وقت إنشاء الطلب
                        'item_count' => count($request->items),
                        'notes' => $request->notes ?? null, // ملاحظة pharmacist عند إنشاء الطلب
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                // في حالة فشل الـ logging، نستمر (لا نريد أن نفشل العملية بسبب الـ logging)
                \Log::error('Failed to log supply request creation', ['error' => $e->getMessage()]);
            }

            return $this->sendSuccess(
                ['requestNumber' => 'REQ-' . $supplyRequest->id], 
                'تم إرسال طلب التوريد بنجاح'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('حدث خطأ أثناء حفظ الطلب: ' . $e->getMessage());
        }
    }
}
