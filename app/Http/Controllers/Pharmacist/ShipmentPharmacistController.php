<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\InternalSupplyRequest;
use App\Models\InternalSupplyRequestItem;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShipmentPharmacistController extends BaseApiController
{
    /**
     * GET /api/pharmacist/shipments
     * List incoming shipments/orders for the pharmacist
     */
   public function index(Request $request)
    {
        // Fetch ALL requests (removed the '!=' pending filter)
        $shipments = InternalSupplyRequest::with('items.drug')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($shipment) {
                return [
                    'id' => $shipment->id,
                    'shipmentNumber' => 'SHP-' . $shipment->id,
                    'requestDate' => Carbon::parse($shipment->created_at)->format('Y/m/d'),
                    
                    // Ensure status is translated to Arabic
                    'status' => $this->translateStatus($shipment->status),
                    
                    'received' => $shipment->status === 'delivered',
                    'items' => $shipment->items->map(function($item) {
                        return [
                            'name' => $item->drug->name ?? 'Unknown',
                            'quantity' => $item->requested_qty // Use 'requested_qty' per your DB
                        ];
                    }),
                    'confirmationDetails' => $shipment->status === 'delivered' ? [
                        'confirmedAt' => $shipment->updated_at->format('Y/m/d H:i')
                    ] : null
                ];
            });

        return $this->sendSuccess($shipments, 'تم جلب الشحنات بنجاح.');
    }

    /**
     * GET /api/pharmacist/shipments/{id}
     * Get details of a specific shipment
     */
    public function show($id)
    {
        $shipment = InternalSupplyRequest::with('items.drug')->findOrFail($id);

        $data = [
            'id' => $shipment->id,
            'shipmentNumber' => 'SHP-' . $shipment->id,
            'requestDate' => Carbon::parse($shipment->created_at)->format('Y/m/d'),
            'status' => $this->translateStatus($shipment->status),
            'items' => $shipment->items->map(function($item) {
                return [
                    'name' => $item->drug->name ?? 'Unknown',
                    'quantity' => $item->quantity
                ];
            })
        ];

        return $this->sendSuccess($data, 'تم جلب تفاصيل الشحنة.');
    }

    /**
     * POST /api/pharmacist/shipments/{id}/confirm
     * Confirm receipt of shipment
     */
       public function confirm(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $shipment = InternalSupplyRequest::with('items')->findOrFail($id);

            // Check against 'fulfilled' instead of 'delivered'
            if ($shipment->status === 'fulfilled') {
                return $this->sendError('تم استلام هذه الشحنة مسبقاً.');
            }

            // 1. Update Shipment Status to valid ENUM value
            $shipment->status = 'fulfilled'; // <--- WAS 'delivered', NOW 'fulfilled'
            $shipment->save();

            // 2. Add items to Pharmacy Inventory
            foreach ($shipment->items as $item) {
                $inventory = Inventory::firstOrNew(['drug_id' => $item->drug_id]);
                
                // Use requested_qty based on your previous item table fix
                $qtyToAdd = $item->requested_qty ?? 0;

                $inventory->current_quantity = ($inventory->current_quantity ?? 0) + $qtyToAdd;
                $inventory->save();
            }

            DB::commit();

            return $this->sendSuccess([
                'id' => $id,
                'status' => 'تم الإستلام', // Front-end label
                'confirmationDetails' => [
                    'confirmedAt' => now()->format('Y/m/d H:i')
                ]
            ], 'تم تأكيد استلام الشحنة وإضافتها للمخزون.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('فشل في تأكيد الاستلام: ' . $e->getMessage());
        }
    }

    // Helper to translate DB status to Arabic for frontend
    private function translateStatus($status)
    {
        return match($status) {
            'pending' => 'قيد الانتظار',
            'approved' => 'قيد التجهيز',
            'shipped' => 'تم الشحن',
            'delivered' => 'تم الإستلام',
            'rejected' => 'مرفوضة',
            default => $status
        };
    }
}
