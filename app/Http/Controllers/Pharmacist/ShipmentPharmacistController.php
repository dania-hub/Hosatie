<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\InternalSupplyRequest;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\AuditLog;
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

    /**
     * GET /api/pharmacist/shipments/{id}
     * عرض تفاصيل شحنة واحدة.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        
        $shipment = InternalSupplyRequest::with('items.drug')
            ->where('id', $id)
            ->first();

        if (!$shipment) {
            return $this->sendError('الشحنة غير موجودة.', [], 404);
        }

        // التحقق من أن الشحنة تخص صيدلية المستخدم
        if ($user->pharmacy_id && $shipment->pharmacy_id !== $user->pharmacy_id) {
            // يمكن تفعيل هذا الشرط للأمان
            // return $this->sendError('هذه الشحنة لا تخص صيدليتك.', [], 403);
        }

        $data = [
            'id' => $shipment->id,
            'shipmentNumber' => 'SHP-' . $shipment->id,
            'requestDate' => Carbon::parse($shipment->created_at)->format('Y/m/d'),
            'status' => $this->translateStatus($shipment->status),
            'received' => $shipment->status === 'fulfilled',
            'items' => $shipment->items->map(function($item) {
                return [
                    'id' => $item->id,
                    'drugId' => $item->drug_id,
                    'name' => $item->drug->name ?? 'Unknown',
                    'genericName' => $item->drug->generic_name ?? null,
                    'strength' => $item->drug->strength ?? null,
                    'quantity' => $item->requested_qty,
                    'approvedQty' => $item->approved_qty ?? null,
                    'fulfilledQty' => $item->fulfilled_qty ?? null,
                    'unit' => $item->drug->unit ?? 'علبة'
                ];
            }),
            'notes' => $shipment->notes,
            'confirmationDetails' => $shipment->status === 'fulfilled' ? [
                'confirmedAt' => $shipment->updated_at->format('Y/m/d H:i')
            ] : null
        ];

        return $this->sendSuccess($data, 'تم جلب تفاصيل الشحنة بنجاح.');
    }

    /**
     * POST /api/pharmacist/shipments/{id}/confirm
     * تأكيد الاستلام: ينقل الأدوية إلى مخزون الصيدلية.
     */
   public function confirm(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // 1) جلب الطلب الداخلي مع العناصر
            $shipment = InternalSupplyRequest::with('items')->findOrFail($id);

            if ($shipment->status === 'fulfilled') {
                return $this->sendError('تم استلام هذه الشحنة مسبقاً.');
            }

            // 2) تحقق أن الشحنة تخص صيدلية هذا المستخدم (أمان)
            $user = $request->user();
            if ($user->pharmacy_id && $shipment->pharmacy_id !== $user->pharmacy_id) {
                // يمكن تفعيل هذا الشرط لو أردت
                // return $this->sendError('هذه الشحنة لا تخص صيدليتك.');
            }

            // 3) تغيير الحالة إلى fulfilled (استلام نهائي)
            $shipment->status = 'fulfilled';
            $shipment->save();

            // 4) إضافة الأدوية لمخزون الصيدلية
            $targetPharmacyId = $shipment->pharmacy_id ?: ($user->pharmacy_id ?? null);
            if (!$targetPharmacyId) {
                DB::rollBack();
                return $this->sendError('لا يوجد صيدلية مرتبطة بهذا الطلب أو بالمستخدم.');
            }

            foreach ($shipment->items as $item) {
                // الكمية التي ستضاف للصيدلية:
                // الأفضل استخدام approved_qty أو fulfilled_qty إن حدّثتها في خطوة المخزن
                $qtyToAdd = $item->approved_qty ?? $item->requested_qty ?? 0;

                if ($qtyToAdd <= 0) {
                    continue;
                }

                // البحث عن مخزون هذا الدواء في هذه الصيدلية
                $inventory = Inventory::firstOrNew([
                    'drug_id'    => $item->drug_id,
                    'pharmacy_id'=> $targetPharmacyId,
                ]);

                // سجل جديد؟ نتأكد ألا يكون مرتبطاً بمستودع
                if (!$inventory->exists) {
                    $inventory->warehouse_id = null;
                }

                $inventory->current_quantity = ($inventory->current_quantity ?? 0) + $qtyToAdd;
                $inventory->save();

                // (اختياري) يمكنك هنا أيضاً تحديث fulfilled_qty في item:
                $item->fulfilled_qty = $qtyToAdd;
                $item->save();
            }

            DB::commit();

AuditLog::create([
    'user_id'    => $user->id,
     'hospital_id'=> $user->hospital_id,
    'action'     => 'pharmacist_confirm_internal_receipt',
    'table_name' => 'internal_supply_request',
    'record_id'  => $shipment->id,
    'old_values' => null,
    'new_values' => json_encode([
        'status'      => $shipment->status,
        'pharmacy_id' => $shipment->pharmacy_id,
        'items'       => $shipment->items->map(fn($item) => [
            'item_id'       => $item->id,
            'drug_id'       => $item->drug_id,
            'fulfilled_qty' => $item->fulfilled_qty ?? $item->approved_qty ?? $item->requested_qty,
        ]),
    ]),
    'ip_address' => $request->ip(),
]);
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
        }}
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
