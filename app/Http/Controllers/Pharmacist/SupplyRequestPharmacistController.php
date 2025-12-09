<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\InternalSupplyRequest;
use App\Models\InternalSupplyRequestItem;
use App\Models\Pharmacy; // <--- إضافة موديل الصيدلية
use Illuminate\Support\Facades\DB;

class SupplyRequestPharmacistController extends BaseApiController
{
    /**
     * Create a new internal supply request from Pharmacy to Warehouse/Admin.
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

            $supplyRequest = new InternalSupplyRequest();
            
            // Use correct column names
            $supplyRequest->requested_by = $user->id;
            $supplyRequest->pharmacy_id = $pharmacyId; // <--- استخدام الصيدلية المستنتجة
            
            $supplyRequest->status = 'pending';
            $supplyRequest->notes = $request->notes;
            $supplyRequest->save();

            foreach ($request->items as $item) {
                $requestItem = new InternalSupplyRequestItem();
                $requestItem->request_id = $supplyRequest->id; 
                $requestItem->drug_id = $item['drugId'];
                $requestItem->requested_qty = $item['quantity']; 
                $requestItem->save();
            }

            DB::commit();

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
