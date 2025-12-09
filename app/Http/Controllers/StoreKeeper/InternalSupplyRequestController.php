<?php

namespace App\Http\Controllers\StoreKeeper;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\InternalSupplyRequest;
use Illuminate\Support\Facades\DB;
use App\Models\Inventory;
use App\Models\AuditLog;
class InternalSupplyRequestController extends BaseApiController
{
    // GET /api/storekeeper/shipments
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $requests = InternalSupplyRequest::with(['pharmacy'])
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $requests->map(function ($req) {
            return [
                'id'                => $req->id,
                'shipmentNumber'    => 'INT-' . $req->id, // رقم شحنة افتراضي
                'requestDate'       => $req->created_at,
                'status'            => $req->status,
                'requestStatus'     => $this->mapStatusToArabic($req->status),
                'requestingDepartment' => $req->pharmacy->name ?? 'صيدلية غير محددة',
                'department'        => [
                    'name' => $req->pharmacy->name ?? 'صيدلية غير محددة',
                ],
                'items'             => [], // ستُجلب بالتفصيل في show
                'notes'             => $req->notes,
                'createdAt'         => $req->created_at,
                'updatedAt'         => $req->updated_at,
                'rejectionReason'   => $req->notes, // لو استعملت notes للرفض أيضاً
                'confirmedBy'       => null,
                'confirmedAt'       => null,
            ];
        });

        return response()->json($data);
    }

    private function mapStatusToArabic(string $status): string
    {
        return match ($status) {
            'pending'   => 'جديد',
            'approved'  => 'قيد التجهيز',
            'fulfilled' => 'تم الإستلام',
            'rejected'  => 'مرفوضة',
            'cancelled' => 'ملغاة',
            default     => $status,
        };
    }
        // GET /api/storekeeper/shipments/{id}
    public function show(Request $request, $id)
    {
        $user = $request->user();
        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $req = InternalSupplyRequest::with(['pharmacy', 'items.drug'])
            ->findOrFail($id);

        return response()->json([
            'id'             => $req->id,
            'shipmentNumber' => 'INT-' . $req->id,
            'department'     => $req->pharmacy->name ?? 'صيدلية غير محددة',
            'date'           => $req->created_at,
            'status'         => $this->mapStatusToArabic($req->status),
            'notes'          => $req->notes,
            'items'          => $req->items->map(function ($item) {
                return [
                    'id'             => $item->id,
                    'drug_id'        => $item->drug_id,
                    'drug_name'      => $item->drug->name ?? '',
                    'requested_qty'  => $item->requested_qty,
                    'approved_qty'   => $item->approved_qty,
                    'fulfilled_qty'  => $item->fulfilled_qty,
                ];
            }),
            'confirmationDetails' => null,
        ]);
    }
    // POST /api/storekeeper/shipments/{id}/reject
    public function reject(Request $request, $id)
    {
        $user = $request->user();
        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $validated = $request->validate([
            'rejectionReason' => 'required|string|max:1000',
        ]);

        $req = InternalSupplyRequest::findOrFail($id);

        if ($req->status !== 'pending' && $req->status !== 'approved') {
            return response()->json([
                'message' => 'لا يمكن رفض طلب في هذه الحالة',
            ], 409);
        }

        $req->status = 'rejected';
        // يمكنك تخزين سبب الرفض في notes أو في عمود مستقل لو أضفته
        $req->notes = trim(($req->notes ? $req->notes . "\n" : '') . 'سبب الرفض: ' . $validated['rejectionReason']);
        $req->save();

        return response()->json(['message' => 'تم رفض الطلب الداخلي بنجاح']);
    }
    // POST /api/storekeeper/shipments/{id}/confirm
    public function confirm(Request $request, $id)
    {
        $user = $request->user();
        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $validated = $request->validate([
            'items'                 => 'required|array|min:1',
            'items.*.id'            => 'required|exists:internal_supply_request_item,id',
            'items.*.sentQuantity'  => 'required|integer|min:0',
            'notes'                 => 'nullable|string|max:1000',
        ]);

        $req = InternalSupplyRequest::with('items')->findOrFail($id);

        if (in_array($req->status, ['rejected', 'cancelled', 'fulfilled'])) {
            return response()->json(['message' => 'لا يمكن تأكيد طلب في هذه الحالة'], 409);
        }

        $pharmacyId = $req->pharmacy_id;
        // TODO: اجلب warehouse_id الصحيح من المستخدم/المستشفى
        $warehouseId = 1; // مؤقتاً

        DB::beginTransaction();

        try {
            foreach ($validated['items'] as $itemData) {
                $item = $req->items->firstWhere('id', $itemData['id']);
                if (!$item) continue;

                $drugId = $item->drug_id;
                $qty    = (int) $itemData['sentQuantity'];
                if ($qty <= 0) continue;

                // 1) مخزون المستودع لهذا الدواء
                $warehouseInventory = Inventory::where('warehouse_id', $warehouseId)
                    ->where('drug_id', $drugId)
                    ->lockForUpdate()
                    ->first();

                if (!$warehouseInventory) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "لا يوجد مخزون للمستودع لهذا الدواء (ID: {$drugId})"
                    ], 404);
                }

                if ($warehouseInventory->current_quantity < $qty) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "الكمية غير متوفرة في المخزن للدواء ID: {$drugId}",
                    ], 409);
                }

                // 2) خصم من المستودع
                $warehouseInventory->current_quantity -= $qty;
                $warehouseInventory->save();

                // 3) تثبيت الكميات في عناصر الطلب الداخلي
                $item->approved_qty  = $qty;
                $item->fulfilled_qty = 0; // لم تستلم الصيدلية بعد
                $item->save();
            }

            // 4) تحديث حالة الطلب
            $req->status = 'approved'; // أو 'shipped' حسب تصميمك
            if (!empty($validated['notes'])) {
                $req->notes = trim(($req->notes ? $req->notes . "\n" : '') . 'ملاحظات أمين المخزن: ' . $validated['notes']);
            }
            $req->save();

            DB::commit();

// تسجيل عملية تجهيز الطلب الداخلي وخصم الكميات
AuditLog::create([
    'user_id'    => $user->id,
     'hospital_id'=> $user->hospital_id,
    'action'     => 'storekeeper_confirm_internal_request',
    'table_name' => 'internal_supply_request',
    'record_id'  => $req->id,
    'old_values' => null,
    'new_values' => json_encode([
        'status' => $req->status,
        'items'  => collect($validated['items'])->map(fn($i) => [
            'item_id'       => $i['id'],
            'sentQuantity'  => $i['sentQuantity'],
        ]),
        'notes'  => $validated['notes'] ?? null,
    ]),
    'ip_address' => $request->ip(),
]);

            return response()->json(['message' => 'تم تأكيد تجهيز الطلب الداخلي وخصم الكميات من مخزون المستودع بنجاح']);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'فشل في تأكيد تجهيز الطلب الداخلي',
                'error'   => $e->getMessage(),
            ], 500);
        }
}}

 