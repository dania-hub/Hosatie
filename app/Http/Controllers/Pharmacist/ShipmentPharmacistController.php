<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\InternalSupplyRequest;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShipmentPharmacistController extends BaseApiController
{
    /**
     * GET /api/pharmacist/shipments
     * عرض الشحنات الواردة لصيدلية المستخدم الحالي فقط.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // نفترض أن الصيدلاني مرتبط بصيدلية، أو نجلبه عبر المستشفى
        $query = InternalSupplyRequest::with('items.drug')
            ->orderBy('created_at', 'desc');

        // تصفية الشحنات الخاصة بالصيدلية الحالية (إذا كان المستخدم مرتبطاً بصيدلية)
        if ($user->pharmacy_id) {
            $query->where('pharmacy_id', $user->pharmacy_id);
        } elseif ($user->id) {
            // أو الشحنات التي طلبها هذا المستخدم
            $query->where('requested_by', $user->id);
        }

        $shipments = $query->get()
            ->map(function ($shipment) {
                return [
                    'id' => $shipment->id,
                    'shipmentNumber' => 'SHP-' . $shipment->id,
                    'requestDate' => Carbon::parse($shipment->created_at)->format('Y/m/d'),
                    'status' => $this->translateStatus($shipment->status),
                    'received' => $shipment->status === 'fulfilled', // Corrected status check
                    'items' => $shipment->items->map(function($item) {
                        return [
                            'name' => $item->drug->name ?? 'Unknown',
                            'quantity' => $item->requested_qty
                        ];
                    }),
                    'confirmationDetails' => $shipment->status === 'fulfilled' ? [
                        'confirmedAt' => $shipment->updated_at->format('Y/m/d H:i')
                    ] : null
                ];
            });

        return $this->sendSuccess($shipments, 'تم جلب الشحنات بنجاح.');
    }

    // ... (دالة show تبقى كما هي تقريباً) ...

    /**
     * POST /api/pharmacist/shipments/{id}/confirm
     * تأكيد الاستلام: ينقل الأدوية إلى مخزون الصيدلية.
     */
    public function confirm(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // جلب الشحنة
            $shipment = InternalSupplyRequest::with('items')->findOrFail($id);

            if ($shipment->status === 'fulfilled') {
                return $this->sendError('تم استلام هذه الشحنة مسبقاً.');
            }

            // التحقق من أن الشحنة تخص صيدلية هذا المستخدم (أمان إضافي)
            $user = $request->user();
            if ($user->pharmacy_id && $shipment->pharmacy_id !== $user->pharmacy_id) {
                 // return $this->sendError('هذه الشحنة لا تخص صيدليتك.'); // يمكنك تفعيل هذا الشرط
            }

            // 1. تحديث الحالة
            $shipment->status = 'fulfilled';
            $shipment->save();

            // 2. إضافة الأدوية لمخزون الصيدلية (pharmacy_id الموجود في الطلب)
            $targetPharmacyId = $shipment->pharmacy_id; 
            
            if (!$targetPharmacyId) {
                // احتياط: إذا لم يكن في الطلب pharmacy_id، نستخدم صيدلية المستخدم
                 $targetPharmacyId = $user->pharmacy_id ?? 1; 
            }

            foreach ($shipment->items as $item) {
                // البحث عن مخزون هذا الدواء في الصيدلية المحددة
                $inventory = Inventory::firstOrNew([
                    'drug_id' => $item->drug_id,
                    'pharmacy_id' => $targetPharmacyId // <--- الربط الصحيح
                ]);

                // إذا كان سجلاً جديداً، نتأكد من تصفير warehouse_id
                if (!$inventory->exists) {
                    $inventory->warehouse_id = null;
                }
                
                $qtyToAdd = $item->requested_qty ?? 0;
                $inventory->current_quantity = ($inventory->current_quantity ?? 0) + $qtyToAdd;
                $inventory->save();
            }

            DB::commit();

            return $this->sendSuccess([
                'id' => $id,
                'status' => 'تم الإستلام',
                'confirmationDetails' => [
                    'confirmedAt' => now()->format('Y/m/d H:i')
                ]
            ], 'تم تأكيد استلام الشحنة وإضافتها لمخزون الصيدلية.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('فشل في تأكيد الاستلام: ' . $e->getMessage());
        }
    }

    // Helper function
    private function translateStatus($status)
    {
        return match($status) {
            'pending' => 'قيد الانتظار',
            'approved' => 'قيد التجهيز', // أو 'تمت الموافقة'
            'shipped' => 'تم الشحن',
            'fulfilled' => 'تم الإستلام', // Correct DB value translation
            'rejected' => 'مرفوضة',
            default => $status
        };
    }
}
