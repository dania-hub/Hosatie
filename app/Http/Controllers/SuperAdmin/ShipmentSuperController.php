<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use App\Models\InternalSupplyRequest;
use App\Models\Inventory;
use App\Models\Warehouse;
use App\Models\AuditLog;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Services\StaffNotificationService;

class ShipmentSuperController extends BaseApiController
{
    public function __construct(
        private StaffNotificationService $notifications
    ) {}

    /**
     * جلب الملاحظات وسبب الرفض من audit_log
     */
    private function getNotesFromAuditLog($requestId): array
    {
        $notes = [
            'creationNotes' => null,
            'storekeeperNotes' => null,
            'storekeeperNotesSource' => null,
            'supplierNotes' => null,
            'confirmationNotes' => null,
            'confirmationNotesSource' => null,
            'adminConfirmationNotes' => null,
            'rejectionReason' => null,
            'rejectedAt' => null,
        ];

        $auditLogs = AuditLog::where('table_name', 'internal_supply_request')
            ->where('record_id', $requestId)
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($auditLogs as $log) {
            $newValues = json_decode($log->new_values, true);
            if (!$newValues) continue;

            // ملاحظة إنشاء الطلب من المورد (supplier_create_internal_supply_request)
            if ($log->action === 'supplier_create_internal_supply_request'
                && isset($newValues['notes']) && (is_string($newValues['notes']) ? trim($newValues['notes']) !== '' : $newValues['notes'])) {
                $notes['creationNotes'] = is_string($newValues['notes']) ? trim($newValues['notes']) : $newValues['notes'];
            }

            if (in_array($log->action, ['إنشاء طلب توريد', 'pharmacist_create_supply_request', 'department_create_supply_request'])
                && isset($newValues['notes']) && !empty($newValues['notes'])) {
                $notes['storekeeperNotes'] = $newValues['notes'];
                if ($log->action === 'pharmacist_create_supply_request') {
                    $notes['storekeeperNotesSource'] = 'pharmacist';
                } elseif ($log->action === 'department_create_supply_request') {
                    $notes['storekeeperNotesSource'] = 'department';
                }
            }

            if ($log->action === 'storekeeper_confirm_internal_request'
                && isset($newValues['notes']) && !empty($newValues['notes'])) {
                $notes['supplierNotes'] = $newValues['notes'];
            }

            if (in_array($log->action, ['pharmacist_confirm_internal_receipt', 'department_confirm_internal_receipt', 'supplier_confirm_internal_receipt'])
                && isset($newValues['notes']) && (is_string($newValues['notes']) ? trim($newValues['notes']) !== '' : !empty($newValues['notes']))) {
                $notes['confirmationNotes'] = is_string($newValues['notes']) ? trim($newValues['notes']) : $newValues['notes'];
                if ($log->action === 'pharmacist_confirm_internal_receipt') {
                    $notes['confirmationNotesSource'] = 'pharmacist';
                } elseif ($log->action === 'department_confirm_internal_receipt') {
                    $notes['confirmationNotesSource'] = 'department';
                } elseif ($log->action === 'supplier_confirm_internal_receipt') {
                    $notes['confirmationNotesSource'] = 'supplier';
                }
            }

            // ملاحظة تأكيد الإرسال من مدير النظام (super_admin_confirm_internal_supply_request)
            if ($log->action === 'super_admin_confirm_internal_supply_request'
                && isset($newValues['notes']) && $newValues['notes'] !== null) {
                $n = $newValues['notes'];
                if (is_string($n) && trim($n) !== '') {
                    $notes['adminConfirmationNotes'] = trim($n);
                }
            }

            $rejectActions = ['رفض طلب توريد داخلي', 'storekeeper_reject_internal_request', 'reject', 'super_admin_reject_internal_supply_request'];
            if (in_array($log->action, $rejectActions)) {
                $notes['rejectionReason'] = $newValues['rejectionReason'] ?? $newValues['rejection_reason'] ?? $notes['rejectionReason'];
                $notes['rejectedAt'] = $newValues['rejectedAt'] ?? $log->created_at?->format('Y-m-d H:i:s');
            }
        }

        return $notes;
    }

    private function getRequestingDepartmentName(InternalSupplyRequest $req): string
    {
        $req->loadMissing(['supplier', 'requester.department', 'pharmacy', 'department']);

        // عندما يوجد supplier_id، الجهة الطالبة هي اسم المورد
        if ($req->supplier_id && $req->supplier) {
            return $req->supplier->name;
        }

        $requestingDepartmentName = 'غير محدد';

        $creationLog = AuditLog::where('table_name', 'internal_supply_request')
            ->where('record_id', $req->id)
            ->whereIn('action', ['department_create_supply_request', 'pharmacist_create_supply_request', 'إنشاء طلب توريد'])
            ->orderBy('created_at', 'asc')
            ->first();

        if ($creationLog && $creationLog->new_values) {
            $newValues = json_decode($creationLog->new_values, true);
            if (isset($newValues['department_name']) && !empty($newValues['department_name'])) {
                $requestingDepartmentName = $newValues['department_name'];
            } elseif (isset($newValues['pharmacy_name']) && !empty($newValues['pharmacy_name'])) {
                $requestingDepartmentName = $newValues['pharmacy_name'];
            }
        }

        if ($requestingDepartmentName === 'غير محدد' && $req->requester) {
            if (in_array($req->requester->type, ['department_head', 'department_admin'])) {
                $department = Department::where('head_user_id', $req->requester->id)->first();
                if ($department) {
                    $requestingDepartmentName = $department->name;
                } elseif ($req->requester->department) {
                    $requestingDepartmentName = $req->requester->department->name;
                } elseif ($req->requester->department_id) {
                    $d = Department::find($req->requester->department_id);
                    if ($d) $requestingDepartmentName = $d->name;
                }
            } elseif ($req->requester->type === 'pharmacist' && $req->pharmacy) {
                $requestingDepartmentName = $req->pharmacy->name;
            }
        }

        if ($requestingDepartmentName === 'غير محدد' && $req->pharmacy) {
            $requestingDepartmentName = $req->pharmacy->name;
        }
        if ($requestingDepartmentName === 'غير محدد' && $req->department) {
            $requestingDepartmentName = $req->department->name;
        }

        return $requestingDepartmentName;
    }

    private function mapStatusToArabic(string $status): string
    {
        return match ($status) {
            'pending' => 'جديد',
            'approved' => 'قيد الاستلام',
            'fulfilled' => 'تم الإستلام',
            'rejected' => 'مرفوضة',
            'cancelled' => 'ملغاة',
            default => $status,
        };
    }

    /**
     * عرض قائمة طلبات التوريد الداخلية (internal_supply_requests)
     */
    public function index(Request $request)
    {
        try {
            $query = InternalSupplyRequest::with(['supplier', 'pharmacy', 'requester.department', 'department', 'items.drug'])
                ->whereNotNull('supplier_id');

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $shipments = $query->latest()->get()->map(function ($req) {
                $notes = $this->getNotesFromAuditLog($req->id);
                $deptName = $this->getRequestingDepartmentName($req);
                return [
                    'id' => $req->id,
                    'shipmentNumber' => 'INT-' . $req->id,
                    'requestingDepartment' => $deptName,
                    'department' => $deptName,
                    'requestDate' => $req->created_at->format('Y-m-d'),
                    'createdAt' => $req->created_at,
                    'status' => $this->mapStatusToArabic($req->status),
                    'requestStatus' => $this->mapStatusToArabic($req->status),
                    'rejectionReason' => $notes['rejectionReason'],
                    'rejectedAt' => $notes['rejectedAt'],
                    'items' => $req->items->map(function ($item) {
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

            return $this->sendSuccess($shipments, 'تم جلب قائمة طلبات التوريد الداخلية بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Shipments Index Error');
        }
    }

    /**
     * عرض تفاصيل طلب التوريد الداخلي
     */
    public function show($id)
    {
        try {
            $req = InternalSupplyRequest::with(['supplier', 'pharmacy.hospital', 'requester.department', 'department', 'items.drug'])
                ->whereNotNull('supplier_id')
                ->findOrFail($id);

            $notes = $this->getNotesFromAuditLog($req->id);
            $deptName = $this->getRequestingDepartmentName($req);

            $hospitalId = $req->pharmacy?->hospital_id ?? $req->department?->hospital_id;
            $warehouse = $hospitalId ? Warehouse::where('hospital_id', $hospitalId)->first() : null;
            $warehouseId = $warehouse?->id;

            $items = $req->items->map(function ($item) use ($warehouseId, $req) {
                $stock = 0;
                if ($item->drug_id && $warehouseId) {
                    $stock = (int) Inventory::where('drug_id', $item->drug_id)->where('warehouse_id', $warehouseId)->sum('current_quantity');
                } elseif ($item->drug_id) {
                    $stock = (int) Inventory::where('drug_id', $item->drug_id)->sum('current_quantity');
                }
                $suggested = $req->status === 'pending' ? min((int) $item->requested_qty, $stock) : (int) ($item->approved_qty ?? 0);
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
                    'suggestedQuantity' => $suggested,
                    'unit' => $item->drug->unit ?? 'وحدة',
                    'dosage' => $item->drug->strength ?? '',
                    'strength' => $item->drug->strength ?? '',
                    'type' => $item->drug->form ?? '',
                    'form' => $item->drug->form ?? '',
                    'units_per_box' => $item->drug->units_per_box ?? 1,
                ];
            });

            return $this->sendSuccess([
                'id' => $req->id,
                'shipmentNumber' => 'INT-' . $req->id,
                'department' => $deptName,
                'date' => $req->created_at->format('Y-m-d'),
                'status' => $this->mapStatusToArabic($req->status),
                'rejectionReason' => $notes['rejectionReason'],
                'rejectedAt' => $notes['rejectedAt'],
                'notes' => $notes['creationNotes'] ?? $notes['storekeeperNotes'] ?? $notes['supplierNotes'],
                'creationNotes' => $notes['creationNotes'],
                'storekeeperNotes' => $notes['storekeeperNotes'],
                'storekeeperNotesSource' => $notes['storekeeperNotesSource'],
                'supplierNotes' => $notes['supplierNotes'],
                'confirmationNotes' => $notes['confirmationNotes'],
                'confirmationNotesSource' => $notes['confirmationNotesSource'],
                'adminConfirmationNotes' => $notes['adminConfirmationNotes'],
                'items' => $items,
                'confirmationDetails' => null,
            ], 'تم جلب تفاصيل الطلب بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Shipment Show Error');
        }
    }

    /**
     * قبول طلب التوريد الداخلي
     * PUT /api/super-admin/shipments/{id}/approve
     */
    public function approve(Request $request, $id)
    {
        try {
            $req = InternalSupplyRequest::with(['items', 'pharmacy', 'requester'])
                ->whereNotNull('supplier_id')
                ->findOrFail($id);

            if ($req->status !== 'pending') {
                return $this->sendError('الطلب ليس في حالة انتظار، لا يمكن تعديل حالته', null, 400);
            }

            $req->status = 'approved';
            $req->handeled_by = $request->user()->id;
            $req->handeled_at = now();
            $req->save();

            $hospitalId = $req->pharmacy?->hospital_id ?? $req->department?->hospital_id;

            try {
                AuditLog::create([
                    'user_id' => $request->user()->id,
                    'hospital_id' => $hospitalId,
                    'action' => 'super_admin_approve_internal_supply_request',
                    'table_name' => 'internal_supply_request',
                    'record_id' => $req->id,
                    'new_values' => json_encode(['status' => 'approved']),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                \Log::warning('Audit Log Error: ' . $e->getMessage());
            }

            try {
                $user = $req->requester ?? \App\Models\User::find($req->requested_by);
                if ($user) {
                    $this->notifications->notifyRequesterShipmentApproved($user, $req);
                }
            } catch (\Exception $e) {
                \Log::error('Notification error', ['error' => $e->getMessage()]);
            }

            return $this->sendSuccess($req, 'تم قبول الطلب بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Approve Error');
        }
    }

    /**
     * رفض طلب التوريد الداخلي
     * PUT /api/super-admin/shipments/{id}/reject
     */
    public function reject(Request $request, $id)
    {
        try {
            $req = InternalSupplyRequest::with(['items', 'pharmacy', 'requester'])
                ->whereNotNull('supplier_id')
                ->findOrFail($id);

            if ($req->status !== 'pending') {
                return $this->sendError('الطلب ليس في حالة انتظار، لا يمكن تعديل حالته', null, 400);
            }

            $rejectionReason = $request->input('rejection_reason') ?: $request->input('rejectionReason') ?: $request->input('notes', '');

            $req->status = 'rejected';
            $req->handeled_by = $request->user()->id;
            $req->handeled_at = now();
            $req->save();

            $hospitalId = $req->pharmacy?->hospital_id ?? $req->department?->hospital_id;

            try {
                AuditLog::create([
                    'user_id' => $request->user()->id,
                    'hospital_id' => $hospitalId,
                    'action' => 'super_admin_reject_internal_supply_request',
                    'table_name' => 'internal_supply_request',
                    'record_id' => $req->id,
                    'new_values' => json_encode([
                        'status' => 'rejected',
                        'rejectionReason' => $rejectionReason,
                        'rejectedAt' => now()->format('Y-m-d H:i:s'),
                        'handeled_at' => $req->handeled_at,
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                \Log::warning('Audit Log Error: ' . $e->getMessage());
            }

            try {
                $user = $req->requester ?? \App\Models\User::find($req->requested_by);
                if ($user) {
                    $this->notifications->notifyRequesterShipmentRejected($user, $req, $rejectionReason);
                }
            } catch (\Exception $e) {
                \Log::error('Notification error', ['error' => $e->getMessage()]);
            }

            return $this->sendSuccess($req, 'تم رفض الطلب بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Reject Error');
        }
    }

    /**
     * تأكيد إرسال طلب التوريد الداخلي (تحديث approved_qty وخصم المخزون)
     * PUT /api/super-admin/shipments/{id}/confirm
     */
    public function confirm(Request $request, $id)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:internal_supply_request_items,id',
            'items.*.sentQuantity' => 'nullable|numeric|min:0',
            'items.*.approved_qty' => 'nullable|numeric|min:0',
            'items.*.batch_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $req = InternalSupplyRequest::with(['items.drug', 'pharmacy.hospital', 'department'])
                ->whereNotNull('supplier_id')
                ->findOrFail($id);

            if (in_array($req->status, ['rejected', 'cancelled', 'fulfilled', 'approved'])) {
                DB::rollBack();
                return $this->sendError('لا يمكن تعديل طلب في حالة "قيد الاستلام" أو الحالات المغلقة', null, 409);
            }

            $hospitalId = $req->pharmacy?->hospital_id ?? $req->department?->hospital_id;
            $warehouse = $hospitalId ? Warehouse::where('hospital_id', $hospitalId)->first() : null;
            if (!$warehouse) {
                $warehouse = Warehouse::first();
            }
            if (!$warehouse) {
                DB::rollBack();
                return $this->sendError('لا يمكن تحديد المستودع لخصم الكميات', null, 400);
            }
            $warehouseId = $warehouse->id;

            foreach ($validated['items'] as $itemData) {
                $item = $req->items->firstWhere('id', $itemData['id']);
                if (!$item) continue;

                $qtyNeeded = (float)($itemData['sentQuantity'] ?? $itemData['approved_qty'] ?? 0);
                $batchNumber = $itemData['batch_number'] ?? null;

                if ($qtyNeeded > 0) {
                    $drugId = $item->drug_id;
                    $inventoryQuery = Inventory::where('warehouse_id', $warehouseId)
                        ->where('drug_id', $drugId)
                        ->where('current_quantity', '>', 0);

                    if ($batchNumber) {
                        $inventoryQuery->where('batch_number', $batchNumber);
                        $inventories = $inventoryQuery->get();
                    } else {
                        $inventories = $inventoryQuery->orderBy('expiry_date', 'asc')->orderBy('created_at', 'asc')->get();
                    }

                    if ($inventories->isEmpty()) {
                        DB::rollBack();
                        $drugName = $item->drug ? $item->drug->name : "ID: {$drugId}";
                        return $this->sendError("لا يوجد مخزون للمستودع لهذا الدواء: {$drugName}", null, 404);
                    }

                    $totalAvailable = $inventories->sum('current_quantity');
                    if ($totalAvailable < $qtyNeeded) {
                        DB::rollBack();
                        $drugName = $item->drug ? $item->drug->name : "ID: {$drugId}";
                        return $this->sendError("الكمية غير متوفرة في المخزن للدواء: {$drugName} (المتاح: {$totalAvailable}, المطلوب: {$qtyNeeded})", null, 409);
                    }

                    $batchesUsed = [];
                    $remainingToDeduct = $qtyNeeded;

                    foreach ($inventories as $inv) {
                        if ($remainingToDeduct <= 0) break;
                        $deducted = min($inv->current_quantity, $remainingToDeduct);
                        $inv->current_quantity -= $deducted;
                        $inv->save();
                        try {
                            $this->notifications->checkAndNotifyLowStock($inv);
                        } catch (\Exception $e) {}
                        $remainingToDeduct -= $deducted;
                        $batchesUsed[] = [
                            'batch_number' => $inv->batch_number,
                            'expiry_date' => $inv->expiry_date,
                            'quantity' => $deducted,
                        ];
                    }

                    if (count($batchesUsed) > 0) {
                        $firstBatch = array_shift($batchesUsed);
                        $item->approved_qty = $firstBatch['quantity'];
                        $item->fulfilled_qty = 0;
                        $item->batch_number = $firstBatch['batch_number'];
                        $item->expiry_date = $firstBatch['expiry_date'];
                        $item->save();

                        foreach ($batchesUsed as $batch) {
                            $newItem = $item->replicate();
                            $newItem->request_id = $req->id;
                            $newItem->requested_qty = 0;
                            $newItem->approved_qty = $batch['quantity'];
                            $newItem->fulfilled_qty = 0;
                            $newItem->batch_number = $batch['batch_number'];
                            $newItem->expiry_date = $batch['expiry_date'];
                            $newItem->save();
                        }
                    } else {
                        $item->approved_qty = 0;
                        $item->save();
                    }
                } else {
                    $item->approved_qty = 0;
                    $item->fulfilled_qty = 0;
                    $item->save();
                }
            }

            $req->status = 'approved';
            $req->handeled_by = $request->user()->id;
            $req->handeled_at = now();
            $req->save();

            $hospitalId = $req->pharmacy?->hospital_id ?? $req->department?->hospital_id;
            try {
                AuditLog::create([
                    'user_id' => $request->user()->id,
                    'hospital_id' => $hospitalId,
                    'action' => 'super_admin_confirm_internal_supply_request',
                    'table_name' => 'internal_supply_request',
                    'record_id' => $req->id,
                    'new_values' => json_encode([
                        'status' => 'approved',
                        'items_count' => count($validated['items']),
                        'notes' => $validated['notes'] ?? null,
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                \Log::warning('Audit Log Error: ' . $e->getMessage());
            }

            try {
                $user = $req->requester ?? \App\Models\User::find($req->requested_by);
                if ($user) {
                    $this->notifications->notifyRequesterShipmentApproved($user, $req);
                }
            } catch (\Exception $e) {
                \Log::error('Notification error', ['error' => $e->getMessage()]);
            }

            DB::commit();
            return $this->sendSuccess($req, 'تم تأكيد إرسال الطلب وخصم الكميات من مخزون المستودع بنجاح');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleException($e, 'Super Admin Shipment Confirm Error');
        }
    }
}
