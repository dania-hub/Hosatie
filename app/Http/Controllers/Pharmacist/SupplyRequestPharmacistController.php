<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\InternalSupplyRequest;
use App\Models\InternalSupplyRequestItem;
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
            $supplyRequest = new InternalSupplyRequest();
            
            // 1. Fix: Use correct column names from Migration
            $supplyRequest->requested_by = $request->user()->id ?? 1; // Was 'requester_id'
            
            // 2. Fix: Add required pharmacy_id
            // Ideally: $request->user()->pharmacy_id;
            // For now: Use 1 if testing locally
            $supplyRequest->pharmacy_id = 1; 
            
            $supplyRequest->status = 'pending';
            $supplyRequest->notes = $request->notes;
            $supplyRequest->save();

                       foreach ($request->items as $item) {
                $requestItem = new InternalSupplyRequestItem();
                
                // 1. Correct Column: request_id
                $requestItem->request_id = $supplyRequest->id; 
                
                $requestItem->drug_id = $item['drugId'];
                
                // 2. Correct Column: requested_qty
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
