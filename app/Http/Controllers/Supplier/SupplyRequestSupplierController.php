<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\BaseApiController;
use App\Models\Drug;
use App\Models\ExternalSupplyRequest;
use App\Models\ExternalSupplyRequestItem;
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

            // جلب الطلبات التي أنشأها المورد فقط (وليس المستشفى)
            // نستخدم whereHas للتحقق من أن المستخدم الذي أنشأ الطلب هو supplier_admin وله نفس supplier_id
            $requests = ExternalSupplyRequest::with([
                'hospital:id,name,city',
                'requester:id,full_name,type,supplier_id',
                'approver:id,full_name',
                'items.drug:id,name'
            ])
                ->whereHas('requester', function($query) use ($user) {
                    $query->where('type', 'supplier_admin')
                          ->where('supplier_id', $user->supplier_id);
                })
                ->where('supplier_id', $user->supplier_id)
                // ->whereIn('status', ['pending', 'approved', 'fulfilled', 'rejected']) // Show all statuses
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($request) {
                    return [
                        'id' => $request->id,
                        'hospitalName' => $request->hospital->name ?? 'غير محدد',
                        'hospitalCode' => $request->hospital->code ?? '',
                        'status' => $this->translateStatus($request->status),
                        'statusOriginal' => $request->status,
                        'itemsCount' => $request->items->count(),
                        'createdAt' => $request->created_at->format('Y/m/d'),
                        'approvedBy' => $request->approver?->full_name ?? 'مدير المستشفى',
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

            $supplyRequest = ExternalSupplyRequest::with([
                'hospital:id,name,city,address,phone',
                'requester:id,full_name,email,phone,type,supplier_id',
                'items.drug:id,name,category,form,strength,unit,units_per_box',
            ])
                ->whereHas('requester', function($query) use ($user) {
                    $query->where('type', 'supplier_admin')
                          ->where('supplier_id', $user->supplier_id);
                })
                ->where('supplier_id', $user->supplier_id)
                ->findOrFail($id);

            // جلب ملاحظة تأكيد الاستلام من AuditLog
            $confirmationNotes = null;
            $confirmedAt = null;
            if ($supplyRequest->status === 'delivered') {
                $auditLog = AuditLog::where('table_name', 'external_supply_request')
                    ->where('record_id', $supplyRequest->id)
                    ->where('action', 'supplier_confirm_receipt')
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
                    'id' => $supplyRequest->hospital->id,
                    'name' => $supplyRequest->hospital->name,
                    'city' => $supplyRequest->hospital->city,
                    'address' => $supplyRequest->hospital->address,
                    'phone' => $supplyRequest->hospital->phone,
                ],
                'requestedBy' => [
                    'name' => $supplyRequest->requester->full_name ?? 'غير محدد',
                    'email' => $supplyRequest->requester->email ?? '',
                    'phone' => $supplyRequest->requester->phone ?? '',
                ],
                'department' => $supplyRequest->hospital->name ?? 'غير محدد',
                'status' => $this->translateStatus($supplyRequest->status),
                'statusOriginal' => $supplyRequest->status,
                'items' => $supplyRequest->items->map(function ($item) use ($user) {
                    $drug = $item->drug;
                    // التأكد من استخراج الكمية بشكل صحيح
                    // الحقل في قاعدة البيانات هو requested_qty
                    $requestedQty = (int) ($item->requested_qty ?? 0);
                    $approvedQty = $item->approved_qty !== null ? (int) $item->approved_qty : null;
                    $fulfilledQty = $item->fulfilled_qty !== null ? (int) $item->fulfilled_qty : null;
                    
                    // جلب تواريخ انتهاء الصلاحية من Inventory للكمية المستلمة المرتبطة بهذا الطلب فقط
                    // نستخدم batch_number من item للبحث عن الكميات الصحيحة
                    // (قد يكون هناك عدة expiry dates لنفس batch_number)
                    $expiryDates = [];
                    if ($fulfilledQty > 0 && $user->supplier_id) {
                        // البحث عن الكميات في Inventory التي تطابق batch_number من item
                        // هذا يضمن أننا نجلب فقط الكميات المرتبطة بهذا الطلب المحدد
                        if ($item->batch_number) {
                            // البحث عن جميع الكميات التي تطابق batch_number من item
                            // (قد يكون هناك عدة expiry dates لنفس batch_number)
                            $inventories = Inventory::where('drug_id', $item->drug_id)
                                ->where('supplier_id', $user->supplier_id)
                                ->where('batch_number', $item->batch_number)
                                ->where('current_quantity', '>', 0)
                                ->orderBy('expiry_date', 'asc')
                                ->get();
                            
                            $totalQuantity = 0;
                            foreach ($inventories as $inv) {
                                if ($inv->expiry_date && $totalQuantity < $fulfilledQty) {
                                    // نحدد الكمية بناءً على fulfilled_qty فقط
                                    $remainingNeeded = $fulfilledQty - $totalQuantity;
                                    $qtyToAdd = min($inv->current_quantity, $remainingNeeded);
                                    
                                    if ($qtyToAdd > 0) {
                                        $expiryDates[] = [
                                            'batchNumber' => $inv->batch_number,
                                            'expiryDate' => $inv->expiry_date,
                                            'quantity' => $qtyToAdd
                                        ];
                                        $totalQuantity += $qtyToAdd;
                                    }
                                }
                            }
                        }
                        
                        // إذا لم نجد كميات تطابق batch_number من item،
                        // نستخدم fulfilled_qty فقط بدون تفاصيل expiry dates
                        // (هذا يعني أن الكمية المستلمة موجودة لكن لا توجد تفاصيل expiry dates محددة)
                    }
                    
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
                        'approvedQuantity' => $approvedQty !== null ? (int) $approvedQty : null,
                        'approved_qty' => $approvedQty !== null ? (int) $approvedQty : null,
                        'approvedQty' => $approvedQty !== null ? (int) $approvedQty : null,
                        'fulfilled_qty' => $fulfilledQty !== null ? (int) $fulfilledQty : null,
                        'fulfilledQty' => $fulfilledQty !== null ? (int) $fulfilledQty : null,
                        'receivedQuantity' => $fulfilledQty !== null ? (int) $fulfilledQty : null,
                        'quantity' => (int) $requestedQty,
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
                'notes' => $supplyRequest->messages, // Return the conversation thread
                'rejectionReason' => $supplyRequest->rejection_reason,
                'rejectedAt' => $supplyRequest->status === 'rejected' && $supplyRequest->handeled_at 
                    ? $supplyRequest->handeled_at->format('Y-m-d H:i:s') 
                    : null,
                'confirmationNotes' => $confirmationNotes,
                'confirmation' => $supplyRequest->status === 'delivered' ? [
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
     * إنشاء طلب توريد جديد
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

            // التحقق من وجود المستشفى
            $hospital = Hospital::findOrFail($request->input('hospital_id'));

            // إنشاء طلب التوريد
            $supplyRequest = ExternalSupplyRequest::create([
                'hospital_id' => $hospital->id,
                'supplier_id' => $user->supplier_id,
                'requested_by' => $user->id,
                'status' => 'pending',
                'priority' => $request->input('priority', 'normal'),
            ]);
            
            // Add initial note if provided
            if ($request->filled('notes')) {
                $supplyRequest->addNote($request->input('notes'), $user);
            }

            // إضافة الأدوية المطلوبة
            $items = $request->input('items', []);

            // تجميع البنود حسب `drug_id` وجمع الكميات لتجنب إدخالات مكررة
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
                // التحقق من حالة الدواء قبل الإضافة
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

                ExternalSupplyRequestItem::create([
                    'request_id' => $supplyRequest->id,
                    'drug_id' => $drugId,
                    'requested_qty' => $totalQty,
                ]);
            }

            // تسجيل العملية في AuditLog
            try {
                AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => $hospital->id,
                    'action' => 'supplier_create_external_supply_request',
                    'table_name' => 'external_supply_request',
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
                \Log::warning('Failed to create audit log for supplier supply request', [
                    'error' => $e->getMessage()
                ]);
            }

            try {
                $this->notifications->notifySuperAdminNewExternalRequest($supplyRequest);
            } catch (\Exception $e) {
                \Log::error('Failed to notify super admin about new external request from supplier', ['error' => $e->getMessage()]);
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

            $supplyRequest = ExternalSupplyRequest::with('items.drug')
                ->whereHas('requester', function($query) use ($user) {
                    $query->where('type', 'supplier_admin')
                          ->where('supplier_id', $user->supplier_id);
                })
                ->where('supplier_id', $user->supplier_id)
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
                
                // دعم قائمة تواريخ انتهاء الصلاحية المتعددة
                $expiryDates = $receivedItem['expiryDates'] ?? [];
                
                // للتوافق مع الكود القديم: إذا لم تكن هناك expiryDates، استخدم batchNumber وexpiryDate القديم
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

                // معالجة كل تاريخ انتهاء صلاحية كسجل مخزون منفصل
                foreach ($expiryDates as $expiryEntry) {
                    $batchNumber = $expiryEntry['batchNumber'] ?? null;
                    $expiryDate = $expiryEntry['expiryDate'] ?? null;
                    $qty = $expiryEntry['quantity'] ?? 0;
                    
                    if ($qty <= 0) {
                        continue;
                    }

                    // إضافة الكمية المستلمة إلى مخزون المورد
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

                // تحديث fulfilled_qty في العنصر (إجمالي الكمية المستلمة)
                $item->fulfilled_qty = $receivedQty;
                // حفظ أول batch وexpiry للتوافق مع الكود القديم
                if (!empty($expiryDates) && isset($expiryDates[0])) {
                    $item->batch_number = $expiryDates[0]['batchNumber'] ?? null;
                    $item->expiry_date = $expiryDates[0]['expiryDate'] ?? null;
                }
                $item->save();
            }

            // تحديث حالة الطلب إلى delivered
            $previousStatus = $supplyRequest->status;
            $supplyRequest->status = 'delivered';
            $supplyRequest->save();

            // تسجيل العملية في AuditLog
            try {
                AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => $supplyRequest->hospital_id,
                    'action' => 'supplier_confirm_receipt',
                    'table_name' => 'external_supply_request',
                    'record_id' => $supplyRequest->id,
                    'old_values' => json_encode(['status' => $previousStatus]),
                    'new_values' => json_encode([
                        'status' => 'delivered',
                        'notes' => $notes,
                        'items' => $receivedItems
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                \Log::warning('Failed to create audit log for supplier receipt confirmation', [
                    'error' => $e->getMessage()
                ]);
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
