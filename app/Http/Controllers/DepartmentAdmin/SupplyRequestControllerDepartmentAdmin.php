<?php

namespace App\Http\Controllers\DepartmentAdmin;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\InternalSupplyRequest;
use App\Models\InternalSupplyRequestItem;
use App\Models\Pharmacy;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class SupplyRequestControllerDepartmentAdmin extends BaseApiController
{
    /**
     * POST /api/department-admin/supply-requests
     * Create a new request for medicines
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.drugId' => 'required|exists:drug,id',
            'items.*.quantity' => 'required|integer|min:1',
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

            // إنشاء الطلب
            $supplyRequest = InternalSupplyRequest::create([
                'pharmacy_id' => $pharmacyId,
                'requested_by' => $user->id,
                'status' => 'pending',
                'notes' => $request->notes,
            ]);

            // إضافة عناصر الطلب
            foreach ($request->items as $item) {
                InternalSupplyRequestItem::create([
                    'request_id' => $supplyRequest->id,
                    'drug_id' => $item['drugId'],
                    'requested_qty' => $item['quantity'],
                    'approved_qty' => null,
                    'fulfilled_qty' => null,
                ]);
            }

            DB::commit();

            // تسجيل العملية في audit_log (بعد commit الناجح)
            try {
                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'إنشاء طلب توريد',
                    'table_name' => 'internal_supply_request',
                    'record_id' => $supplyRequest->id,
                    'old_values' => null,
                    'new_values' => json_encode([
                        'request_id' => $supplyRequest->id,
                        'pharmacy_id' => $pharmacyId,
                        'item_count' => count($request->items),
                        'notes' => $request->notes,
                    ]),
                    'ip_address' => $request->ip(),
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
