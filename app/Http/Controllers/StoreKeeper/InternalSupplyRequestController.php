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
            'approved'  => 'قيد الاستلام',
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

        // TODO: اجلب warehouse_id الصحيح من المستخدم/المستشفى
        $warehouseId = 1; // مؤقتاً

        return response()->json([
            'id'             => $req->id,
            'shipmentNumber' => 'INT-' . $req->id,
            'department'     => $req->pharmacy->name ?? 'صيدلية غير محددة',
            'date'           => $req->created_at,
            'status'         => $this->mapStatusToArabic($req->status),
            'notes'          => $req->notes,
            'items'          => $req->items->map(function ($item) use ($warehouseId) {
                // جلب المخزون المتاح لهذا الدواء في المستودع
                $inventory = Inventory::where('warehouse_id', $warehouseId)
                    ->where('drug_id', $item->drug_id)
                    ->first();
                
                return [
                    'id'             => $item->id,
                    'drug_id'        => $item->drug_id,
                    'drug_name'      => $item->drug->name ?? '',
                    'name'           => $item->drug->name ?? '', // للتوافق مع المكون
                    'requested_qty'  => $item->requested_qty,
                    'quantity'       => $item->requested_qty, // للتوافق مع المكون
                    'approved_qty'   => $item->approved_qty,
                    'fulfilled_qty'  => $item->fulfilled_qty,
                    'sentQuantity'   => $item->approved_qty, // للتوافق مع المكون
                    'receivedQuantity' => $item->fulfilled_qty, // للتوافق مع المكون
                    'availableQuantity' => $inventory ? $inventory->current_quantity : 0, // المخزون المتاح
                    'stock'          => $inventory ? $inventory->current_quantity : 0, // للتوافق مع المكون
                    'strength'       => $item->drug->strength ?? '',
                    'dosage'         => $item->drug->strength ?? '', // للتوافق مع المكون
                    'form'           => $item->drug->form ?? '',
                    'type'           => $item->drug->form ?? '', // للتوافق مع المكون
                    'unit'           => $item->drug->unit ?? 'وحدة',
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

        // منع رفض الطلبات في حالة "قيد الاستلام" أو الحالات المغلقة
        if ($req->status !== 'pending') {
            return response()->json([
                'message' => 'لا يمكن رفض طلب في حالة "قيد الاستلام" أو الحالات المغلقة',
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
        \Log::info('=== Starting shipment confirmation ===', ['id' => $id]);
        
        $user = $request->user();
        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        \Log::info('Validating request data');
        $validated = $request->validate([
            'items'                 => 'required|array|min:1',
            'items.*.id'            => 'required|exists:internal_supply_request_item,id',
            'items.*.sentQuantity'  => 'required|integer|min:0',
            'notes'                 => 'nullable|string|max:1000',
        ]);

        \Log::info('Loading request with items', ['id' => $id]);
        $req = InternalSupplyRequest::with('items.drug')->findOrFail($id);

        // منع التأكيد إذا كان الطلب في حالة "قيد الاستلام" أو الحالات المغلقة
        if (in_array($req->status, ['rejected', 'cancelled', 'fulfilled', 'approved'])) {
            return response()->json(['message' => 'لا يمكن تعديل طلب في حالة "قيد الاستلام" أو الحالات المغلقة'], 409);
        }

        $pharmacyId = $req->pharmacy_id;
        // TODO: اجلب warehouse_id الصحيح من المستخدم/المستشفى
        $warehouseId = 1; // مؤقتاً

        \Log::info('Starting database transaction');
        DB::beginTransaction();

        try {
            \Log::info('Processing items', ['count' => count($validated['items'])]);
            
            // التحقق من جميع الأدوية قبل البدء في المعالجة
            $inventoryChecks = [];
            foreach ($validated['items'] as $index => $itemData) {
                \Log::info("Processing item {$index}", ['item_id' => $itemData['id'], 'qty' => $itemData['sentQuantity']]);
                
                $item = $req->items->firstWhere('id', $itemData['id']);
                if (!$item) {
                    \Log::warning("Item not found", ['item_id' => $itemData['id']]);
                    continue;
                }

                $drugId = $item->drug_id;
                $qty    = (int) $itemData['sentQuantity'];
                if ($qty <= 0) {
                    \Log::info("Skipping item with zero quantity", ['item_id' => $itemData['id']]);
                    continue;
                }

                \Log::info("Checking inventory", ['drug_id' => $drugId, 'warehouse_id' => $warehouseId]);
                
                // التحقق من المخزون بدون lock (أسرع)
                $warehouseInventory = Inventory::where('warehouse_id', $warehouseId)
                    ->where('drug_id', $drugId)
                    ->first();

                if (!$warehouseInventory) {
                    \Log::error("Inventory not found", ['drug_id' => $drugId, 'warehouse_id' => $warehouseId]);
                    DB::rollBack();
                    return response()->json([
                        'message' => "لا يوجد مخزون للمستودع لهذا الدواء (ID: {$drugId})"
                    ], 404);
                }

                if ($warehouseInventory->current_quantity < $qty) {
                    \Log::error("Insufficient inventory", [
                        'drug_id' => $drugId,
                        'available' => $warehouseInventory->current_quantity,
                        'required' => $qty
                    ]);
                    DB::rollBack();
                    $drugName = $item->drug ? $item->drug->name : "ID: {$drugId}";
                    return response()->json([
                        'message' => "الكمية غير متوفرة في المخزن للدواء: {$drugName} (المتاح: {$warehouseInventory->current_quantity}, المطلوب: {$qty})",
                    ], 409);
                }

                $inventoryChecks[] = [
                    'inventory' => $warehouseInventory,
                    'item' => $item,
                    'qty' => $qty
                ];
            }

            \Log::info('Updating inventory and items', ['count' => count($inventoryChecks)]);
            
            // الآن نقوم بالخصم والتحديث
            foreach ($inventoryChecks as $checkIndex => $check) {
                \Log::info("Updating inventory {$checkIndex}", [
                    'drug_id' => $check['item']->drug_id,
                    'qty' => $check['qty']
                ]);
                
                // خصم من المستودع
                $check['inventory']->current_quantity -= $check['qty'];
                $check['inventory']->save();

                // تثبيت الكميات في عناصر الطلب الداخلي
                $check['item']->approved_qty  = $check['qty'];
                $check['item']->fulfilled_qty = 0; // لم تستلم الصيدلية بعد
                $check['item']->save();
            }

            \Log::info('Updating request status');
            
            // تحديث حالة الطلب
            $req->status = 'approved';
            if (!empty($validated['notes'])) {
                $req->notes = trim(($req->notes ? $req->notes . "\n" : '') . 'ملاحظات أمين المخزن: ' . $validated['notes']);
            }
            $req->save();

            \Log::info('Committing transaction');
            DB::commit();
            \Log::info('Transaction committed successfully');

            // تسجيل عملية تجهيز الطلب الداخلي وخصم الكميات (خارج الـ transaction)
            try {
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
            } catch (\Exception $auditError) {
                // لا نفشل العملية إذا فشل الـ logging
                \Log::error('Failed to create audit log', ['error' => $auditError->getMessage()]);
            }

            return response()->json(['message' => 'تم تأكيد تجهيز الطلب الداخلي وخصم الكميات من مخزون المستودع بنجاح']);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Error confirming shipment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'فشل في تأكيد تجهيز الطلب الداخلي',
                'error'   => $e->getMessage(),
            ], 500);
        }
}}

 