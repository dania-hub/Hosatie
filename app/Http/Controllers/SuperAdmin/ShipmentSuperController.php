<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use App\Models\InternalSupplyRequest;
use App\Models\Inventory;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Services\StaffNotificationService; // Added

class ShipmentSuperController extends BaseApiController
{
    public function __construct(
        private StaffNotificationService $notifications
    ) {}

    /**
     * عرض قائمة الشحنات (طلبات التوريد الداخلية من الموردين)
     * فقط الطلبات التي أنشأها الموردون
     */
    public function index(Request $request)
    {
        try {
            // جلب الطلبات الداخلية
            // حيث الطالب هو supplier_admin
            $query = InternalSupplyRequest::with(['requester', 'items.drug']) // requester.supplier might fail if relation not def on User
                ->whereHas('requester', function($q) {
                    $q->where('type', 'supplier_admin');
                });

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $shipments = $query->latest()->get()->map(function ($shipment) {
                // Get Supplier Name from Requester
                // Assuming User model has supplier relation, otherwise check variable
                $supplierName = $shipment->requester->full_name ?? 'غير محدد';
                if ($shipment->requester->relationLoaded('supplier')) {
                     $supplierName = $shipment->requester->supplier->name ?? $supplierName;
                } elseif ($shipment->requester->supplier_id) {
                     // Lazy load or just show name if possible. avoiding N+1 if not eager loaded
                     // But we didn't eager load 'requester.supplier' above to be safe
                }
                
                return [
                    'id' => $shipment->id,
                    'shipmentNumber' => 'INT-SUP-' . $shipment->id,
                    'requestingDepartment' => $supplierName,
                    'department' => $supplierName,
                    'requestDate' => $shipment->created_at->format('Y-m-d'),
                    'createdAt' => $shipment->created_at,
                    'status' => match($shipment->status) {
                        'pending' => 'جديد',
                        'approved' => 'قيد الاستلام', 
                        'fulfilled' => 'تم الإستلام',
                        'rejected' => 'مرفوض',
                        default => $shipment->status,
                    },
                    'rejectionReason' => $shipment->rejection_reason ?? null,
                    'rejectedAt' => $shipment->status === 'rejected' && $shipment->handeled_at ? $shipment->handeled_at->format('Y-m-d H:i:s') : null,
                    'confirmedAt' => $shipment->updated_at,
                    'items' => $shipment->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->drug ? $item->drug->name : 'غير معروف',
                            'quantity' => $item->requested_qty,
                            'requested_qty' => $item->requested_qty,
                            'receivedQuantity' => $item->fulfilled_qty ?? 0,
                            'units_per_box' => $item->drug->units_per_box ?? 1,
                        ];
                    }),
                ];
            });

            return $this->sendSuccess($shipments, 'تم جلب قائمة الشحنات بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Shipments Index Error');
        }
    }

    /**
     * عرض تفاصيل الشحنة
     */
    public function show($id)
    {
        try {
            $shipment = InternalSupplyRequest::with(['requester', 'items.drug'])
                ->whereHas('requester', function($q) {
                    $q->where('type', 'supplier_admin');
                })
                ->findOrFail($id);

            $supplierName = $shipment->requester->full_name ?? 'غير محدد';
            // Try to load supplier info if available
             if ($shipment->requester && $shipment->requester->supplier_id) {
                  $sup = \App\Models\Supplier::find($shipment->requester->supplier_id);
                  if ($sup) $supplierName = $sup->name;
             }

            $messages = []; // No standard messages relation on InternalSupplyRequest
            
            return $this->sendSuccess([
                'id' => $shipment->id,
                'shipmentNumber' => 'INT-SUP-' . $shipment->id,
                'department' => $supplierName,
                'date' => $shipment->created_at->format('Y-m-d'),
                'status' => match($shipment->status) {
                    'pending' => 'جديد',
                    'approved' => 'قيد الاستلام',
                    'fulfilled' => 'تم الإستلام',
                    'rejected' => 'مرفوض',
                    default => $shipment->status,
                },
                'rejectionReason' => $shipment->rejection_reason ?? null,
                'rejectedAt' => ($shipment->status === 'rejected' && $shipment->handeled_at) ? $shipment->handeled_at->format('Y-m-d H:i:s') : null,
                'notes' => $messages, 
                'notes_initial' => '',
                'conversation' => $messages, 
                'items' => $shipment->items->map(function ($item) {
                    $stock = 0; 
                    if ($item->drug_id) {
                         $stock = Inventory::where('drug_id', $item->drug_id)->sum('current_quantity');
                    }

                    return [
                        'id' => $item->id,
                        'drug_id' => $item->drug_id,
                        'drugId' => $item->drug_id,
                        'name' => $item->drug ? $item->drug->name : 'غير معروف',
                        'quantity' => $item->requested_qty,
                        'requested_qty' => $item->requested_qty,
                        'requestedQty' => $item->requested_qty,
                        'originalQuantity' => $item->requested_qty,
                        'approved_qty' => $item->approved_qty,
                        'approvedQty' => $item->approved_qty,
                        'fulfilled_qty' => $item->fulfilled_qty,
                        'fulfilledQty' => $item->fulfilled_qty,
                        'sentQuantity' => $item->fulfilled_qty ?? $item->approved_qty,
                        'stock' => $stock,
                        'availableQuantity' => $stock,
                        'unit' => $item->drug->unit ?? 'وحدة',
                        'dosage' => $item->drug->strength ?? '',
                        'units_per_box' => $item->drug->units_per_box ?? 1,
                    ];
                }),
            ], 'تم جلب تفاصيل الشحنة بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Shipment Show Error');
        }
    }

    /**
     * قبول طلب التوريد
     */
    public function approve(Request $request, $id)
    {
        try {
            $shipment = InternalSupplyRequest::with(['items', 'requester'])
                ->whereHas('requester', function($q) {
                    $q->where('type', 'supplier_admin');
                })
                ->findOrFail($id);

            if ($shipment->status !== 'pending') {
                 return $this->sendError('الطلب ليس في حالة انتظار، لا يمكن تعديل حالته', null, 400);
            }

            $shipment->status = 'approved';
            $shipment->handeled_by = $request->user()->id;
            $shipment->handeled_at = now();
            $shipment->save();

            try {
                \App\Models\AuditLog::create([
                    'user_id' => $request->user()->id,
                    'hospital_id' => null,
                    'action' => 'super_admin_approve_internal_supply_request',
                    'table_name' => 'internal_supply_request',
                    'record_id' => $shipment->id,
                    'new_values' => json_encode(['status' => 'approved']),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                 \Log::warning('Audit Log Error: ' . $e->getMessage());
            }

            try {
                $this->notifications->notifySupplierAboutSuperAdminResponse($shipment, 'قيد الشحن الدولي', $request->input('notes'));
            } catch (\Exception $e) {
                \Log::error('Notification error', ['error' => $e->getMessage()]);
            }

            return $this->sendSuccess($shipment, 'تم قبول الطلب بنجاح');
        } catch (\Exception $e) {
             return $this->handleException($e, 'Super Admin Approve Error');
        }
    }

    /**
     * رفض طلب التوريد
     */
    public function reject(Request $request, $id)
    {
        try {
            $shipment = InternalSupplyRequest::with(['items', 'requester'])
                ->whereHas('requester', function($q) {
                    $q->where('type', 'supplier_admin');
                })
                ->findOrFail($id);

            if ($shipment->status !== 'pending') {
                 return $this->sendError('الطلب ليس في حالة انتظار، لا يمكن تعديل حالته', null, 400);
            }

            $shipment->status = 'rejected';
            $shipment->handeled_by = $request->user()->id;
            $shipment->handeled_at = now();
             $shipment->save();

            try {
                \App\Models\AuditLog::create([
                    'user_id' => $request->user()->id,
                    'hospital_id' => null,
                    'action' => 'super_admin_reject_internal_supply_request',
                    'table_name' => 'internal_supply_request',
                    'record_id' => $shipment->id,
                    'new_values' => json_encode([
                        'status' => 'rejected',
                        'notes' => $request->input('notes')
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                 \Log::warning('Audit Log Error: ' . $e->getMessage());
            }

            try {
                $this->notifications->notifySupplierAboutSuperAdminResponse($shipment, 'مرفوض', $request->input('notes'));
            } catch (\Exception $e) {
                \Log::error('Notification error', ['error' => $e->getMessage()]);
            }

            return $this->sendSuccess($shipment, 'تم رفض الطلب بنجاح');
        } catch (\Exception $e) {
             return $this->handleException($e, 'Super Admin Reject Error');
        }
    }

    /**
     * تأكيد استلام الشحنة وتحديث المخزون
     */
    public function confirm(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $shipment = InternalSupplyRequest::with(['items', 'requester'])
                ->whereHas('requester', function($q) {
                    $q->where('type', 'supplier_admin');
                })
                ->findOrFail($id);
            
            // 1. Confirm sending 
            $sentItems = $request->input('items', []);
            if (!empty($sentItems) && empty($request->input('receivedItems'))) {
                $sentItemsMap = [];
                foreach ($sentItems as $sentItem) {
                    $itemId = $sentItem['id'] ?? null;
                    $qty = $sentItem['approved_qty'] ?? $sentItem['sentQuantity'] ?? $sentItem['sent_quantity'] ?? null;
                    if ($itemId !== null && $qty !== null) {
                        $sentItemsMap[(int)$itemId] = (float)$qty;
                    }
                }

                foreach ($shipment->items as $item) {
                    if (!array_key_exists((int)$item->id, $sentItemsMap)) {
                        continue;
                    }
                    $item->approved_qty = $sentItemsMap[(int)$item->id];
                    $item->save();
                }

                if ($shipment->status === 'pending') {
                    $shipment->status = 'approved';
                    $shipment->handeled_by = $request->user()->id;
                    $shipment->handeled_at = now();
                    $shipment->save();
                }

                try {
                    $this->notifications->notifySupplierAboutSuperAdminResponse($shipment, 'تم الإرسال', $request->input('notes'));
                } catch (\Exception $e) {
                    \Log::error('Failed to notify supplier', ['error' => $e->getMessage()]);
                }

                DB::commit();
                return $this->sendSuccess($shipment, 'تم تأكيد إرسال الشحنة بنجاح');
            }

            // 2. Confirm Receipt
            if ($shipment->status === 'fulfilled') {
                return $this->sendError('تم استلام هذه الشحنة مسبقاً', null, 400);
            }

            $receivedItems = $request->input('receivedItems', []);
            $receivedItemsMap = [];
            foreach ($receivedItems as $receivedItem) {
                 $itemId = $receivedItem['id'] ?? null;
                 $qty = $receivedItem['receivedQuantity'] ?? null;
                 if ($itemId !== null) {
                     $receivedItemsMap[(int)$itemId] = (float)$qty;
                 }
            }

            // Find valid warehouse (Fallback to First Active Warehouse)
            $warehouse = Warehouse::whereNull('hospital_id')->first();
            if (!$warehouse) {
                // Try finding 'Main Warehouse' or first created
                $warehouse = Warehouse::orderBy('id')->first();
            }
            if (!$warehouse) {
                 throw new \Exception('No warehouse found in system to store items.');
            }

            // Update Stock
            foreach ($shipment->items as $item) {
                $expectedQty = $item->approved_qty ?? $item->requested_qty ?? 0;
                $receivedQty = isset($receivedItemsMap[(int)$item->id]) ? $receivedItemsMap[(int)$item->id] : $expectedQty;
                
                if ($receivedQty <= 0) continue;

                $inventory = Inventory::firstOrNew([
                    'warehouse_id' => $warehouse->id,
                    'drug_id' => $item->drug_id,
                ]);
                
                $inventory->pharmacy_id = null; 
                $inventory->current_quantity = ($inventory->current_quantity ?? 0) + $receivedQty;
                $inventory->save();

                $item->fulfilled_qty = $receivedQty;
                $item->save();
            }

            $shipment->status = 'fulfilled';
            $shipment->save();

            \App\Models\AuditLog::create([
                'user_id'     => $request->user()->id,
                'hospital_id' => null,
                'action'      => 'super_admin_confirm_internal_supply_request',
                'table_name'  => 'internal_supply_request',
                'record_id'   => $shipment->id,
                'new_values'  => json_encode([
                    'status' => 'fulfilled',
                    'notes' => $request->input('notes'),
                    'target_warehouse' => $warehouse->id
                ]),
                'ip_address'  => $request->ip(),
            ]);

            DB::commit();

            try {
                $statusArabic = 'تم الإستلام';
                $this->notifications->notifySupplierAboutSuperAdminResponse($shipment, $statusArabic, $request->input('notes'));
            } catch (\Exception $e) {
                \Log::error('Failed to notify supplier', ['error' => $e->getMessage()]);
            }
            
            return $this->sendSuccess($shipment, 'تم تأكيد استلام الشحنة وتحديث المخزون بنجاح');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleException($e, 'Super Admin Shipment Confirm Error');
        }
    }
}
