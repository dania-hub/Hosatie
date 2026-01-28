<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\BaseApiController;
use App\Models\Drug;
use App\Models\InternalSupplyRequest;
use App\Models\InternalSupplyRequestItem;
use App\Models\Hospital;
use App\Models\AuditLog;
use App\Models\Inventory;
use App\Http\Requests\Supplier\CreateSupplyRequestRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\StaffNotificationService;

class SupplyRequestSupplierController extends BaseApiController
{
    public function __construct(
        private StaffNotificationService $notifications
    ) {}

    /**
     * جلب الملاحظات وسبب الرفض من audit_log
     */
    private function getNotesFromAuditLog(int $requestId): array
    {
        $notes = [
            'storekeeperNotes' => null,
            'supplierNotes' => null,
            'creationNotes' => null,
            'confirmationNotes' => null,
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

            if (in_array($log->action, ['supplier_create_internal_supply_request']) && !empty($newValues['notes'] ?? null)) {
                $notes['creationNotes'] = $newValues['notes'];
            }
            if (in_array($log->action, ['storekeeper_confirm_internal_request']) && !empty($newValues['notes'] ?? null)) {
                $notes['supplierNotes'] = $newValues['notes'];
            }

            if (in_array($log->action, ['supplier_confirm_internal_receipt']) && !empty($newValues['notes'] ?? null)) {
                $notes['confirmationNotes'] = $newValues['notes'];
            }

            // ملاحظة تأكيد الإرسال من المدير العام (super_admin_confirm_internal_supply_request)
            if ($log->action === 'super_admin_confirm_internal_supply_request' && isset($newValues['notes']) && $newValues['notes'] !== null) {
                $n = $newValues['notes'];
                if (is_string($n) && trim($n) !== '') {
                    $notes['adminConfirmationNotes'] = trim($n);
                }
            }

            if (in_array($log->action, ['رفض طلب توريد داخلي', 'storekeeper_reject_internal_request', 'super_admin_reject_internal_supply_request'])) {
                $notes['rejectionReason'] = $newValues['rejectionReason'] ?? $newValues['rejection_reason'] ?? $notes['rejectionReason'];
                $notes['rejectedAt'] = $newValues['rejectedAt'] ?? $log->created_at?->format('Y-m-d H:i:s');
            }
        }

        return $notes;
    }

    /**
     * عرض قائمة طلبات التوريد الداخلية التي أنشأها المورد
     * GET /api/supplier/supply-requests
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $requests = InternalSupplyRequest::with([
                'pharmacy.hospital:id,name,city,code',
                'department.hospital:id,name,city,code',
                'requester:id,full_name,type,supplier_id',
                'approver:id,full_name',
                'items.drug:id,name',
            ])
                ->where('supplier_id', $user->supplier_id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($req) {
                    $hospital = $req->pharmacy?->hospital ?? $req->department?->hospital;
                    return [
                        'id' => $req->id,
                        'shipmentNumber' => 'INT-' . $req->id,
                        'hospitalName' => $hospital->name ?? 'غير محدد',
                        'hospitalCode' => $hospital->code ?? '',
                        'status' => $this->translateStatus($req->status),
                        'statusOriginal' => $req->status,
                        'itemsCount' => $req->items->count(),
                        'createdAt' => $req->created_at->format('Y/m/d'),
                        'requestDate' => $req->created_at->format('Y/m/d'),
                        'approvedBy' => $req->approver?->full_name ?? '—',
                        'received' => $req->status === 'fulfilled',
                    ];
                });

            return $this->sendSuccess($requests, 'تم جلب طلبات التوريد بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Supply Requests Index Error');
        }
    }

    /**
     * عرض تفاصيل طلب توريد محدد
     * GET /api/supplier/supply-requests/{id}
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $supplyRequest = InternalSupplyRequest::with([
                'pharmacy.hospital',
                'department.hospital',
                'requester:id,full_name,email,phone,type,supplier_id',
                'items.drug:id,name,category,form,strength,unit,units_per_box',
            ])
                ->where('supplier_id', $user->supplier_id)
                ->findOrFail($id);

            $notes = $this->getNotesFromAuditLog($supplyRequest->id);
            $hospital = $supplyRequest->pharmacy?->hospital ?? $supplyRequest->department?->hospital;

            $confirmationNotes = $notes['confirmationNotes'];
            $confirmedAt = null;
            if ($supplyRequest->status === 'fulfilled') {
                $auditLog = AuditLog::where('table_name', 'internal_supply_request')
                    ->where('record_id', $supplyRequest->id)
                    ->where('action', 'supplier_confirm_internal_receipt')
                    ->orderBy('created_at', 'desc')
                    ->first();
                if ($auditLog) {
                    $newValues = json_decode($auditLog->new_values, true);
                    $confirmationNotes = $confirmationNotes ?? ($newValues['notes'] ?? null);
                    $confirmedAt = $auditLog->created_at?->format('Y-m-d H:i:s');
                }
            }

            $data = [
                'id' => $supplyRequest->id,
                'hospital' => $hospital ? [
                    'id' => $hospital->id,
                    'name' => $hospital->name,
                    'city' => $hospital->city ?? '',
                    'address' => $hospital->address ?? '',
                    'phone' => $hospital->phone ?? '',
                ] : ['id' => null, 'name' => 'غير محدد', 'city' => '', 'address' => '', 'phone' => ''],
                'requestedBy' => [
                    'name' => $supplyRequest->requester->full_name ?? 'غير محدد',
                    'email' => $supplyRequest->requester->email ?? '',
                    'phone' => $supplyRequest->requester->phone ?? '',
                ],
                'department' => $hospital?->name ?? 'غير محدد',
                'status' => $this->translateStatus($supplyRequest->status),
                'statusOriginal' => $supplyRequest->status,
                'items' => $supplyRequest->items->map(function ($item) use ($user) {
                    $drug = $item->drug;
                    $requestedQty = (int) ($item->requested_qty ?? 0);
                    $approvedQty = $item->approved_qty !== null ? (int) $item->approved_qty : null;
                    $fulfilledQty = $item->fulfilled_qty !== null ? (int) $item->fulfilled_qty : null;

                    $expiryDates = [];
                    if ($fulfilledQty > 0 && $user->supplier_id && $item->batch_number) {
                        $inventories = Inventory::where('drug_id', $item->drug_id)
                            ->where('supplier_id', $user->supplier_id)
                            ->where('batch_number', $item->batch_number)
                            ->where('current_quantity', '>', 0)
                            ->orderBy('expiry_date', 'asc')
                            ->get();
                        $totalQuantity = 0;
                        foreach ($inventories as $inv) {
                            if ($inv->expiry_date && $totalQuantity < $fulfilledQty) {
                                $remainingNeeded = $fulfilledQty - $totalQuantity;
                                $qtyToAdd = min($inv->current_quantity, $remainingNeeded);
                                if ($qtyToAdd > 0) {
                                    $expiryDates[] = [
                                        'batchNumber' => $inv->batch_number,
                                        'expiryDate' => $inv->expiry_date,
                                        'quantity' => $qtyToAdd,
                                    ];
                                    $totalQuantity += $qtyToAdd;
                                }
                            }
                        }
                    }

                    return [
                        'id' => $item->id,
                        'drugId' => $item->drug_id,
                        'name' => $drug->name ?? 'غير محدد',
                        'drugName' => $drug->name ?? 'غير محدد',
                        'category' => $drug
                            ? (is_object($drug->category ?? null)
                                ? ($drug->category->name ?? $drug->category)
                                : ($drug->category ?? 'غير محدد'))
                            : 'غير محدد',
                        'requestedQuantity' => $requestedQty,
                        'requested_qty' => $requestedQty,
                        'requestedQty' => $requestedQty,
                        'approvedQuantity' => $approvedQty,
                        'approved_qty' => $approvedQty,
                        'approvedQty' => $approvedQty,
                        'fulfilled_qty' => $fulfilledQty,
                        'fulfilledQty' => $fulfilledQty,
                        'receivedQuantity' => $fulfilledQty,
                        'quantity' => $requestedQty,
                        'strength' => $drug->strength ?? null,
                        'dosage' => $drug->strength ?? null,
                        'form' => $drug->form ?? null,
                        'type' => $drug->form ?? 'Tablet',
                        'unit' => $drug->unit ?? 'وحدة',
                        'units_per_box' => $drug->units_per_box ?? 1,
                        'expiryDates' => $expiryDates,
                        'batchNumber' => $item->batch_number,
                        'batch_number' => $item->batch_number,
                        'expiryDate' => $item->expiry_date,
                        'expiry_date' => $item->expiry_date,
                    ];
                }),
                'createdAt' => $supplyRequest->created_at->format('Y/m/d H:i'),
                'notes' => $notes['creationNotes'] ?? $notes['storekeeperNotes'] ?? $notes['supplierNotes'],
                'creationNotes' => $notes['creationNotes'],
                'storekeeperNotes' => $notes['storekeeperNotes'],
                'supplierNotes' => $notes['supplierNotes'],
                'adminConfirmationNotes' => $notes['adminConfirmationNotes'],
                'rejectionReason' => $notes['rejectionReason'],
                'rejectedAt' => $notes['rejectedAt'],
                'confirmationNotes' => $confirmationNotes,
                'confirmation' => $supplyRequest->status === 'fulfilled' ? [
                    'confirmedAt' => $confirmedAt,
                    'confirmationNotes' => $confirmationNotes,
                ] : null,
            ];

            return $this->sendSuccess($data, 'تم جلب تفاصيل الطلب بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Supply Request Show Error');
        }
    }

    /**
     * إنشاء طلب توريد داخلي جديد
     * POST /api/supplier/supply-requests
     */
    public function store(CreateSupplyRequestRequest $request)
    {
        DB::beginTransaction();
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $hospital = Hospital::findOrFail($request->input('hospital_id'));

            // عدم تسجيل pharmacy_id لأن الطلب من المورد (يعتمد على supplier_id فقط)
            $supplyRequest = InternalSupplyRequest::create([
                'pharmacy_id' => null,
                'supplier_id' => $user->supplier_id,
                'requested_by' => $user->id,
                'status' => 'pending',
            ]);

            $items = $request->input('items', []);
            $grouped = [];
            foreach ($items as $item) {
                $drugId = $item['drug_id'] ?? null;
                $qty = isset($item['quantity']) ? (int) $item['quantity'] : 0;
                if (!$drugId || $qty <= 0) continue;
                $grouped[$drugId] = ($grouped[$drugId] ?? 0) + $qty;
            }

            foreach ($grouped as $drugId => $totalQty) {
                $drug = Drug::find($drugId);
                if (!$drug) {
                    throw new \Exception("الدواء رقم #{$drugId} غير موجود.");
                }
                if ($drug->status === Drug::STATUS_ARCHIVED) {
                    throw new \Exception("لا يمكن طلب الدواء '{$drug->name}' لأنه مؤرشف وغير مدعوم.");
                }
                if ($drug->status === Drug::STATUS_PHASING_OUT) {
                    throw new \Exception("لا يمكن إنشاء طلب جديد للدواء '{$drug->name}' لأنه في مرحلة الإيقاف التدريجي.");
                }

                InternalSupplyRequestItem::create([
                    'request_id' => $supplyRequest->id,
                    'drug_id' => $drugId,
                    'requested_qty' => $totalQty,
                ]);
            }

            try {
                AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => $hospital->id,
                    'action' => 'supplier_create_internal_supply_request',
                    'table_name' => 'internal_supply_request',
                    'record_id' => $supplyRequest->id,
                    'old_values' => null,
                    'new_values' => json_encode([
                        'request_id' => $supplyRequest->id,
                        'hospital_id' => $hospital->id,
                        'hospital_name' => $hospital->name,
                        'supplier_id' => $user->supplier_id,
                        'status' => 'pending',
                        'notes' => $request->input('notes'),
                        'items_count' => count($grouped),
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                \Log::warning('Failed to create audit log for supplier internal supply request', ['error' => $e->getMessage()]);
            }

            try {
                $this->notifications->notifySuperAdminNewInternalRequest($supplyRequest, $request->input('notes'));
            } catch (\Exception $e) {
                \Log::error('Failed to notify super admin about new internal request from supplier', ['error' => $e->getMessage()]);
            }

            DB::commit();

            return $this->sendSuccess([
                'id' => $supplyRequest->id,
                'status' => $this->translateStatus($supplyRequest->status),
            ], 'تم إنشاء طلب التوريد بنجاح', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleException($e, 'Supplier Create Supply Request Error');
        }
    }

    /**
     * تأكيد استلام المورد للطلب
     * POST /api/supplier/supply-requests/{id}/confirm-receipt
     */
    public function confirmReceipt(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $supplyRequest = InternalSupplyRequest::with('items.drug')
                ->where('supplier_id', $user->supplier_id)
                ->findOrFail($id);

            if (!in_array($supplyRequest->status, ['fulfilled', 'approved'], true)) {
                return $this->sendError('لا يمكن تأكيد استلام هذا الطلب. يجب أن يكون في حالة قيد الاستلام.', null, 400);
            }

            $receivedItems = $request->input('items', []);
            $notes = $request->input('notes', '');

            foreach ($receivedItems as $receivedItem) {
                $itemId = $receivedItem['id'] ?? null;
                $receivedQty = $receivedItem['receivedQuantity'] ?? 0;
                $expiryDates = $receivedItem['expiryDates'] ?? [];

                if (empty($expiryDates)) {
                    $batchNumber = $receivedItem['batchNumber'] ?? null;
                    $expiryDate = $receivedItem['expiryDate'] ?? null;
                    if ($batchNumber || $expiryDate) {
                        $expiryDates = [['batchNumber' => $batchNumber, 'expiryDate' => $expiryDate, 'quantity' => $receivedQty]];
                    }
                }

                if (!$itemId || $receivedQty <= 0) continue;

                $item = $supplyRequest->items->firstWhere('id', $itemId);
                if (!$item) continue;

                foreach ($expiryDates as $expiryEntry) {
                    $batchNumber = $expiryEntry['batchNumber'] ?? null;
                    $expiryDate = $expiryEntry['expiryDate'] ?? null;
                    $qty = $expiryEntry['quantity'] ?? 0;
                    if ($qty <= 0) continue;

                    $inventory = Inventory::firstOrCreate(
                        [
                            'drug_id' => $item->drug_id,
                            'supplier_id' => $user->supplier_id,
                            'warehouse_id' => null,
                            'pharmacy_id' => null,
                            'department_id' => null,
                            'batch_number' => $batchNumber,
                            'expiry_date' => $expiryDate,
                        ],
                        ['current_quantity' => 0]
                    );
                    $inventory->current_quantity += $qty;
                    $inventory->save();
                }

                $item->fulfilled_qty = $receivedQty;
                if (!empty($expiryDates) && isset($expiryDates[0])) {
                    $item->batch_number = $expiryDates[0]['batchNumber'] ?? null;
                    $item->expiry_date = $expiryDates[0]['expiryDate'] ?? null;
                }
                $item->save();
            }

            $previousStatus = $supplyRequest->status;
            $supplyRequest->status = 'fulfilled';
            $supplyRequest->save();

            $hospitalId = $supplyRequest->pharmacy?->hospital_id ?? $supplyRequest->department?->hospital_id;

            try {
                AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => $hospitalId,
                    'action' => 'supplier_confirm_internal_receipt',
                    'table_name' => 'internal_supply_request',
                    'record_id' => $supplyRequest->id,
                    'old_values' => json_encode(['status' => $previousStatus]),
                    'new_values' => json_encode([
                        'status' => 'fulfilled',
                        'notes' => $notes,
                        'items' => $receivedItems,
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                \Log::warning('Failed to create audit log for supplier internal receipt confirmation', ['error' => $e->getMessage()]);
            }

            DB::commit();

            return $this->sendSuccess([
                'id' => $supplyRequest->id,
                'status' => $this->translateStatus($supplyRequest->status),
            ], 'تم تأكيد استلام الطلب بنجاح وإضافة الكميات إلى المخزون');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleException($e, 'Supplier Confirm Receipt Error');
        }
    }

    /**
     * جلب قائمة المستشفيات المتاحة
     * GET /api/supplier/hospitals
     */
    public function hospitals(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $hospitals = Hospital::where('status', 'active')
                ->where(function ($query) use ($user) {
                    $query->where('supplier_id', $user->supplier_id)->orWhereNull('supplier_id');
                })
                ->orderBy('name')
                ->get()
                ->map(function ($hospital) {
                    return [
                        'id' => $hospital->id,
                        'name' => $hospital->name,
                        'code' => $hospital->code,
                        'city' => $hospital->city,
                        'type' => $hospital->type,
                    ];
                });

            return $this->sendSuccess($hospitals, 'تم جلب قائمة المستشفيات بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Hospitals Error');
        }
    }

    private function translateStatus($status)
    {
        $statuses = [
            'pending' => 'قيد الانتظار',
            'approved' => 'قيد الاستلام',
            'fulfilled' => 'تم الاستلام',
            'delivered' => 'تم الاستلام',
            'rejected' => 'مرفوض',
            'cancelled' => 'ملغى',
        ];
        return $statuses[$status] ?? $status;
    }
}
