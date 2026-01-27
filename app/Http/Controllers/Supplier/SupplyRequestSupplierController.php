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
use App\Services\StaffNotificationService; // Added

class SupplyRequestSupplierController extends BaseApiController
{
    public function __construct(
        private StaffNotificationService $notifications
    ) {}

    /**
     * عرض قائمة طلبات التوريد التي أنشأها المورد
     * GET /api/supplier/supply-requests
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            // جلب الطلبات الداخلية التي أنشأها المورد (InternalSupplyRequest)
            $requests = InternalSupplyRequest::with([
                'requester:id,full_name,type,supplier_id',
                'approver:id,full_name',
                'items.drug:id,name'
            ])
                ->where('requested_by', $user->id) // Supplier Admin User ID
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($request) {
                    return [
                        'id' => $request->id,
                        'hospitalName' => 'الإدارة العامة', // Internal requests go to Admin/Warehouse
                        'hospitalCode' => 'ADMIN',
                        'status' => $this->translateStatus($request->status),
                        'statusOriginal' => $request->status,
                        'itemsCount' => $request->items->count(),
                        'createdAt' => $request->created_at->format('Y/m/d'),
                        'approvedBy' => $request->approver?->full_name ?? 'مدير النظام',
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
                'requester:id,full_name,email,phone,type,supplier_id',
                'items.drug:id,name,category,form,strength,unit,units_per_box',
            ])
                ->where('requested_by', $user->id)
                ->findOrFail($id);

            // جلب ملاحظة تأكيد الاستلام من AuditLog
            $confirmationNotes = null;
            $confirmedAt = null;
            if ($supplyRequest->status === 'fulfilled' || $supplyRequest->status === 'delivered') {
                $auditLog = AuditLog::where('table_name', 'internal_supply_request')
                    ->where('record_id', $supplyRequest->id)
                    ->where('action', 'supplier_confirm_receipt_internal')
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if ($auditLog) {
                    $newValues = json_decode($auditLog->new_values, true);
                    $confirmationNotes = $newValues['notes'] ?? null;
                    $confirmedAt = $auditLog->created_at ? $auditLog->created_at->format('Y-m-d H:i:s') : null;
                }
            }

            $data = [
                'id' => $supplyRequest->id,
                'hospital' => [
                    'id' => null,
                    'name' => 'الإدارة العامة',
                    'city' => '',
                    'address' => '',
                    'phone' => '',
                ],
                'requestedBy' => [
                    'name' => $supplyRequest->requester->full_name ?? 'غير محدد',
                    'email' => $supplyRequest->requester->email ?? '',
                    'phone' => $supplyRequest->requester->phone ?? '',
                ],
                'department' => 'المورد',
                'status' => $this->translateStatus($supplyRequest->status),
                'statusOriginal' => $supplyRequest->status,
                'items' => $supplyRequest->items->map(function ($item) use ($user) {
                    $drug = $item->drug;
                    $requestedQty = (int) ($item->requested_qty ?? 0);
                    $approvedQty = $item->approved_qty !== null ? (int) $item->approved_qty : null;
                    $fulfilledQty = $item->fulfilled_qty !== null ? (int) $item->fulfilled_qty : null;
                    
                    return [
                        'id' => $item->id,
                        'drugId' => $item->drug_id,
                        'name' => $drug->name ?? 'غير محدد',
                        'drugName' => $drug->name ?? 'غير محدد',
                        'category' => $drug
                            ? (is_object($drug->category)
                                ? ($drug->category->name ?? $drug->category)
                                : ($drug->category ?? 'غير محدد'))
                            : 'غير محدد',
                        'requestedQuantity' => (int) $requestedQty,
                        'requested_qty' => (int) $requestedQty,
                        'requestedQty' => (int) $requestedQty,
                        'approvedQuantity' => $approvedQty,
                        'approved_qty' => $approvedQty,
                        'approvedQty' => $approvedQty,
                        'fulfilled_qty' => $fulfilledQty,
                        'receivedQuantity' => $fulfilledQty,
                        'quantity' => (int) $requestedQty,
                        'strength' => $drug->strength ?? null,
                        'dosage' => $drug->strength ?? null,
                        'form' => $drug->form ?? null,
                        'type' => $drug->form ?? 'Tablet',
                        'unit' => $drug->unit ?? 'وحدة',
                        'units_per_box' => $drug->units_per_box ?? 1,
                        'expiryDates' => [], // Batch/Expiry not directly linked to item yet
                    ];
                }),
                'createdAt' => $supplyRequest->created_at->format('Y/m/d H:i'),
                'notes' => [], // No notes relation standard?
                'rejectionReason' => $supplyRequest->rejection_reason ?? null,
                'rejectedAt' => ($supplyRequest->status === 'rejected' && $supplyRequest->handeled_at) 
                    ? $supplyRequest->handeled_at->format('Y-m-d H:i:s') 
                    : null,
                'confirmationNotes' => $confirmationNotes,
                'confirmation' => ($confirmedAt) ? [
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
     * إنشاء طلب توريد داخلي جديد (للمورد -> الإدارة)
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

            // إنشاء طلب التوريد الداخلي
            // pharmacy_id is null for using Supplier logic as requester
            $supplyRequest = InternalSupplyRequest::create([
                'pharmacy_id' => null,
                'supplier_id' => $user->supplier_id,
                'department_id' => null,
                'requested_by' => $user->id,
                'status' => 'pending',
            ]);
            
            // إضافة الأدوية المطلوبة
            $items = $request->input('items', []);

            $grouped = [];
            foreach ($items as $item) {
                $drugId = $item['drug_id'] ?? null;
                $qty = isset($item['quantity']) ? (int) $item['quantity'] : 0;
                if (!$drugId || $qty <= 0) {
                    continue;
                }
                if (!isset($grouped[$drugId])) {
                    $grouped[$drugId] = 0;
                }
                $grouped[$drugId] += $qty;
            }

            foreach ($grouped as $drugId => $totalQty) {
                $drug = Drug::find($drugId);
                if (!$drug) {
                    throw new \Exception("الدواء رقم #{$drugId} غير موجود.");
                }

                 // Check drug status if needed (Assuming similar business rules)
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

            // تسجيل العملية في AuditLog
            try {
                AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => null,
                    'action' => 'supplier_create_internal_supply_request',
                    'table_name' => 'internal_supply_request',
                    'record_id' => $supplyRequest->id,
                    'new_values' => json_encode([
                        'request_id' => $supplyRequest->id,
                        'requested_by' => $user->id,
                        'items_count' => count($grouped),
                        'notes' => $request->input('notes'),
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                \Log::warning('AuditLog Error', ['error' => $e->getMessage()]);
            }

            try {
                $this->notifications->notifySuperAdminNewInternalRequest($supplyRequest);
            } catch (\Exception $e) {
                \Log::error('Notification Error', ['error' => $e->getMessage()]);
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
                ->where('requested_by', $user->id)
                ->findOrFail($id);

            // التحقق من أن الطلب في حالة قيد الاستلام (fulfilled) أو approved
            if (!in_array($supplyRequest->status, ['fulfilled', 'approved'], true)) {
                return $this->sendError('لا يمكن تأكيد استلام هذا الطلب. يجب أن يكون في حالة قيد الاستلام.', null, 400);
            }

            $receivedItems = $request->input('items', []);
            $notes = $request->input('notes', '');

            foreach ($receivedItems as $receivedItem) {
                $itemId = $receivedItem['id'] ?? null;
                $receivedQty = $receivedItem['receivedQuantity'] ?? 0;
                $expiryDates = $receivedItem['expiryDates'] ?? [];

                // Compatibility fallback
                if (empty($expiryDates)) {
                    $batchNumber = $receivedItem['batchNumber'] ?? null;
                    $expiryDate = $receivedItem['expiryDate'] ?? null;
                    if ($batchNumber || $expiryDate) {
                        $expiryDates = [[
                            'batchNumber' => $batchNumber,
                            'expiryDate' => $expiryDate,
                            'quantity' => $receivedQty
                        ]];
                    }
                }

                if (!$itemId || $receivedQty <= 0) {
                    continue;
                }

                $item = $supplyRequest->items->firstWhere('id', $itemId);
                if (!$item) {
                    continue;
                }

                foreach ($expiryDates as $expiryEntry) {
                    $batchNumber = $expiryEntry['batchNumber'] ?? null;
                    $expiryDate = $expiryEntry['expiryDate'] ?? null;
                    $qty = $expiryEntry['quantity'] ?? 0;
                    
                    if ($qty <= 0) {
                        continue;
                    }

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
                        [
                            'current_quantity' => 0,
                        ]
                    );

                    $inventory->current_quantity += $qty;
                    $inventory->save();
                }

                // InternalSupplyRequestItem's fulfilled_qty might have been set by Admin.
                // If it was not set, we can update it here.
                if ($item->fulfilled_qty === null || $item->fulfilled_qty == 0) {
                     $item->fulfilled_qty = $receivedQty;
                     $item->save();
                }
            }

            // تحديث حالة الطلب إلى delivered (if supported) or just log
            // Since enum doesn't support 'delivered', we keep it 'fulfilled'
            // or we updated it elsewhere. 
            // Previous analysis: enum('pending','approved','rejected','fulfilled','cancelled')
            
            // Log confirmation
            try {
                AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => null,
                    'action' => 'supplier_confirm_receipt_internal',
                    'table_name' => 'internal_supply_request',
                    'record_id' => $supplyRequest->id,
                    'new_values' => json_encode([
                        'status' => 'fulfilled (confirmed)',
                        'notes' => $notes,
                        'items' => $receivedItems
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                // Log error
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

            // جلب المستشفيات التابعة لهذا المورد أو جميع المستشفيات النشطة
            $hospitals = Hospital::where('status', 'active')
                ->where(function ($query) use ($user) {
                    $query->where('supplier_id', $user->supplier_id)
                        ->orWhereNull('supplier_id');
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

    /**
     * ترجمة حالة الطلب
     */
    private function translateStatus($status)
    {
        $statuses = [
            'pending' => 'قيد الانتظار',
            'approved' => 'قيد الاستلام',
            'fulfilled' => 'قيد الاستلام',
            'delivered' => 'تم الاستلام',
            'rejected' => 'مرفوض',
            'cancelled' => 'مرفوض',
        ];

        return $statuses[$status] ?? $status;
    }
}
