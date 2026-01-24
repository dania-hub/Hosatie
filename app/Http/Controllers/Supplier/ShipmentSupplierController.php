<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\BaseApiController;
use App\Models\ExternalSupplyRequest;
use App\Models\Inventory;
use App\Http\Requests\Supplier\ConfirmShipmentRequest;
use App\Http\Requests\Supplier\RejectShipmentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Services\StaffNotificationService;

class ShipmentSupplierController extends BaseApiController
{
    public function __construct(
        private StaffNotificationService $notifications
    ) {}
    /**
     * عرض قائمة الشحنات للمورد
     * GET /api/supplier/shipments
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            // التأكد من أن المستخدم مورد
            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            // التأكد من أن المستخدم مرتبط بمورد
            if (!$user->supplier_id) {
                return $this->sendError('المستخدم غير مرتبط بمورد', null, 400);
            }

            // جلب جميع الشحنات (approved, fulfilled, rejected)
            // هذه الطلبات جاءت من StoreKeeper وتم اعتمادها من HospitalAdmin
            // وتم تعيين supplier_id لها عند القبول من قبل HospitalAdmin
            // البحث عن الطلبات التي لها supplier_id مطابق لـ supplier_id الخاص بالمستخدم المورد
            // أو الطلبات من المستشفيات المرتبطة بنفس المورد (في حالة عدم تطابق supplier_id مباشرة)
            $shipments = ExternalSupplyRequest::with([
                'hospital:id,name,city,supplier_id',
                'requester:id,full_name',
                'approver:id,full_name',
                'items:id,request_id,drug_id,requested_qty,approved_qty,fulfilled_qty,updated_at'
            ])
                ->where(function($query) use ($user) {
                    // البحث عن الطلبات التي لها supplier_id مطابق لـ supplier_id الخاص بالمستخدم المورد
                    $query->where('supplier_id', $user->supplier_id)
                          // أو البحث عن الطلبات من المستشفيات المرتبطة بنفس المورد
                          ->orWhereHas('hospital', function($q) use ($user) {
                              $q->where('supplier_id', $user->supplier_id);
                          });
                })
                ->where('status', '!=', 'pending') // استبعاد الشحنات التي حالتها pending
                ->orderBy('created_at', 'desc')
                ->get()
                ->filter(function($shipment) {
                    // استبعاد الشحنات المرفوضة من قبل مدير المستشفى
                    // نعرض فقط الشحنات المرفوضة من قبل المورد نفسه
                    if ($shipment->status !== 'rejected') {
                        return true; // نعرض جميع الشحنات غير المرفوضة
                    }
                    
                    // إذا كانت الحالة rejected، نتحقق من آخر سجل رفض
                    $lastRejectionLog = \App\Models\AuditLog::where('table_name', 'external_supply_request')
                        ->where('record_id', $shipment->id)
                        ->where(function($q) {
                            $q->where('action', 'supplier_reject_external_supply_request')
                              ->orWhere('action', 'hospital_admin_reject_external_supply_request');
                        })
                        ->orderBy('created_at', 'desc')
                        ->first();
                    
                    // نعرض فقط إذا كان آخر رفض من المورد
                    return $lastRejectionLog && $lastRejectionLog->action === 'supplier_reject_external_supply_request';
                })
                ->values()
                ->map(function ($shipment) {
                    // التحقق من تأكيد الاستلام من قبل مسؤول المخزن
                    $isDelivered = false;
                    if ($shipment->status === 'fulfilled') {
                        $requestUpdatedAt = $shipment->updated_at;
                        // التحقق من أن items تم تحديثها بعد تحديث الطلب (يعني تم تأكيد الاستلام)
                        $itemsUpdatedAfterDelivery = $shipment->items->some(function($item) use ($requestUpdatedAt) {
                            if (!$item->updated_at) return false;
                            $diffInSeconds = $item->updated_at->diffInSeconds($requestUpdatedAt);
                            return $item->updated_at->gt($requestUpdatedAt) && $diffInSeconds > 1;
                        });
                        
                        if (!$itemsUpdatedAfterDelivery) {
                            $itemsUpdatedAfterDelivery = $shipment->items->every(function($item) use ($requestUpdatedAt) {
                                return $item->updated_at && $item->updated_at->gt($requestUpdatedAt);
                            });
                        }
                        
                        $isDelivered = $itemsUpdatedAfterDelivery;
                    }
                    
                    return [
                        'id' => $shipment->id,
                        'hospitalName' => $shipment->hospital->name ?? 'غير محدد',
                        'hospitalCode' => $shipment->hospital->code ?? '',
                        'hospitalCity' => $shipment->hospital->city ?? '',
                        'requestedBy' => $shipment->requester->full_name ?? 'غير محدد',
                        'approvedBy' => $shipment->approver?->full_name ?? 'مدير المستشفى',
                        'status' => $isDelivered ? 'تم الاستلام' : $this->translateStatus($shipment->status),
                        'statusOriginal' => $isDelivered ? 'delivered' : $shipment->status,
                        'isDelivered' => $isDelivered,
                        'itemsCount' => $shipment->items->count() ?? 0,
                        'createdAt' => $shipment->created_at->format('Y/m/d'),
                        'updatedAt' => $shipment->updated_at->format('Y/m/d'),
                    ];
                });

            return $this->sendSuccess($shipments, 'تم جلب قائمة الشحنات بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Shipments Index Error');
        }
    }

    /**
     * عرض تفاصيل شحنة محددة
     * GET /api/supplier/shipments/{id}
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $shipment = ExternalSupplyRequest::with([
                'hospital:id,name,city,address,phone',
                'requester:id,full_name,email,phone',
                'approver:id,full_name',
                // `category` is stored as a string on `drug` table in this project.
                'items.drug:id,name,category,strength,unit,form,units_per_box',
            ])
                ->where('supplier_id', $user->supplier_id)
                ->findOrFail($id);

            // جلب سبب الرفض والملاحظات من audit_log
            $rejectionReason = null;
            $rejectedAt = null;
            $notes = null;
            
            if ($shipment->status === 'rejected') {
                $rejectionAuditLog = \App\Models\AuditLog::where('table_name', 'external_supply_request')
                    ->where('record_id', $shipment->id)
                    ->where(function($query) {
                        $query->where('action', 'like', '%reject%')
                              ->orWhere('action', 'like', '%رفض%');
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if ($rejectionAuditLog) {
                    $rejectedAt = $rejectionAuditLog->created_at->toIso8601String();
                    // محاولة استخراج سبب الرفض من new_values أو old_values
                    if ($rejectionAuditLog->new_values) {
                        $newValues = json_decode($rejectionAuditLog->new_values, true);
                        if (isset($newValues['rejectionReason'])) {
                            $rejectionReason = $newValues['rejectionReason'];
                        } elseif (isset($newValues['reason'])) {
                            $rejectionReason = $newValues['reason'];
                        }
                    }
                    if (!$rejectionReason && $rejectionAuditLog->old_values) {
                        $oldValues = json_decode($rejectionAuditLog->old_values, true);
                        if (isset($oldValues['rejectionReason'])) {
                            $rejectionReason = $oldValues['rejectionReason'];
                        } elseif (isset($oldValues['reason'])) {
                            $rejectionReason = $oldValues['reason'];
                        }
                    }
                }
            }
            
            // جلب ملاحظة storekeeper (الملاحظة الأصلية عند الإنشاء)
            $storekeeperNotes = null;
            $storekeeperNotesAuditLog = \App\Models\AuditLog::where('table_name', 'external_supply_request')
                ->where('record_id', $shipment->id)
                ->where('action', 'create_external_supply_request')
                ->orderBy('created_at', 'asc')
                ->first();
            
            if ($storekeeperNotesAuditLog && $storekeeperNotesAuditLog->new_values) {
                $newValues = json_decode($storekeeperNotesAuditLog->new_values, true);
                if (isset($newValues['notes']) && !empty($newValues['notes'])) {
                    $storekeeperNotes = $newValues['notes'];
                }
            }
            
            // جلب ملاحظة supplier (عند القبول/الإرسال)
            $supplierNotes = null;
            $supplierNotesAuditLog = \App\Models\AuditLog::where('table_name', 'external_supply_request')
                ->where('record_id', $shipment->id)
                ->where('action', 'supplier_confirm_external_supply_request')
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($supplierNotesAuditLog && $supplierNotesAuditLog->new_values) {
                $newValues = json_decode($supplierNotesAuditLog->new_values, true);
                if (isset($newValues['notes']) && !empty($newValues['notes'])) {
                    $supplierNotes = $newValues['notes'];
                }
            }
            
            // للتوافق مع الكود القديم، نستخدم ملاحظة supplier إذا كانت موجودة، وإلا ملاحظة storekeeper
            $notes = $supplierNotes ?? $storekeeperNotes;

            // إعداد confirmationDetails إذا تم تأكيد الاستلام
            $confirmationDetails = null;
            $isDelivered = false;
            $originalSentQuantities = [];
            $actualReceivedQuantities = [];
            
            // جلب الكميات المرسلة والمستلمة من audit_log في جميع الحالات (ليس فقط عند isDelivered)
            $confirmationNotes = null;
            $auditLog = \App\Models\AuditLog::where('table_name', 'external_supply_request')
                ->where('record_id', $shipment->id)
                ->where('action', 'storekeeper_confirm_external_delivery')
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($auditLog) {
                // جلب الكميات المرسلة الأصلية من old_values
                if ($auditLog->old_values) {
                    $oldValues = json_decode($auditLog->old_values, true);
                    if (isset($oldValues['items']) && is_array($oldValues['items'])) {
                        foreach ($oldValues['items'] as $auditItem) {
                            if (isset($auditItem['item_id']) && isset($auditItem['sentQuantity'])) {
                                $originalSentQuantities[$auditItem['item_id']] = $auditItem['sentQuantity'];
                            }
                        }
                    }
                }
                
                // جلب الكميات المستلمة الفعلية من new_values
                if ($auditLog->new_values) {
                    $newValues = json_decode($auditLog->new_values, true);
                    if (isset($newValues['items']) && is_array($newValues['items'])) {
                        foreach ($newValues['items'] as $auditItem) {
                            if (isset($auditItem['id']) && isset($auditItem['receivedQuantity'])) {
                                $actualReceivedQuantities[$auditItem['id']] = $auditItem['receivedQuantity'];
                            }
                        }
                    }
                    // جلب ملاحظة تأكيد الاستلام
                    if (isset($newValues['confirmationNotes']) && !empty($newValues['confirmationNotes'])) {
                        $confirmationNotes = $newValues['confirmationNotes'];
                    }
                }
            }
            
            // التحقق من أن الطلب تم استلامه (fulfilled + تم تأكيد الاستلام من storekeeper)
            if ($shipment->status === 'fulfilled') {
                $requestUpdatedAt = $shipment->updated_at;
                // التحقق من أن items تم تحديثها بعد تحديث الطلب (يعني تم تأكيد الاستلام)
                $itemsUpdatedAfterDelivery = $shipment->items->some(function($item) use ($requestUpdatedAt) {
                    if (!$item->updated_at) return false;
                    $diffInSeconds = $item->updated_at->diffInSeconds($requestUpdatedAt);
                    return $item->updated_at->gt($requestUpdatedAt) && $diffInSeconds > 1;
                });
                
                if (!$itemsUpdatedAfterDelivery) {
                    $itemsUpdatedAfterDelivery = $shipment->items->every(function($item) use ($requestUpdatedAt) {
                        return $item->updated_at && $item->updated_at->gt($requestUpdatedAt);
                    });
                }
                
                $isDelivered = $itemsUpdatedAfterDelivery;
            }
            
            if ($isDelivered) {
                // جلب تاريخ الاستلام من audit_log
                $deliveryDate = null;
                if ($auditLog && $auditLog->created_at) {
                    $deliveryDate = $auditLog->created_at->toIso8601String();
                } else {
                    $deliveryDate = $shipment->updated_at->toIso8601String();
                }
                
                $confirmationDetails = [
                    'confirmedAt' => $deliveryDate,
                    'confirmationNotes' => $confirmationNotes ?? null,
                    'receivedItems' => $shipment->items->map(function($item) use ($originalSentQuantities, $actualReceivedQuantities) {
                        $sentQty = $originalSentQuantities[$item->id] ?? $item->fulfilled_qty ?? $item->approved_qty ?? 0;
                        $receivedQty = $actualReceivedQuantities[$item->id] ?? $item->fulfilled_qty ?? 0;
                        
                        return [
                            'id' => $item->id,
                            'name' => $item->drug->name ?? 'غير محدد',
                            'sentQuantity' => $sentQty,
                            'receivedQuantity' => $receivedQty,
                            'unit' => $item->drug->unit ?? 'وحدة'
                        ];
                    })->toArray()
                ];
            }

            $data = [
                'id' => $shipment->id,
                'shipmentNumber' => 'EXT-' . $shipment->id,
                'date' => $shipment->created_at->toIso8601String(),
                'createdAt' => $shipment->created_at->toIso8601String(),
                'hospital' => [
                    'id' => $shipment->hospital->id,
                    'name' => $shipment->hospital->name,
                    'code' => $shipment->hospital->code,
                    'city' => $shipment->hospital->city,
                    'address' => $shipment->hospital->address,
                    'phone' => $shipment->hospital->phone,
                ],
                'requestedBy' => [
                    'name' => $shipment->requester->full_name ?? 'غير محدد',
                    'email' => $shipment->requester->email ?? '',
                    'phone' => $shipment->requester->phone ?? '',
                ],
                'approvedBy' => $shipment->approver ? $shipment->approver->full_name : null,
                'status' => $this->translateStatus($shipment->status),
                'statusOriginal' => $shipment->status,
                'department' => $shipment->hospital->name ?? 'مستشفى غير محدد',
                'rejectionReason' => $rejectionReason,
                'rejectedAt' => $rejectedAt,
                'notes' => $notes,
                'storekeeperNotes' => $storekeeperNotes,
                'supplierNotes' => $supplierNotes,
                'confirmationDetails' => $confirmationDetails,
                'items' => $shipment->items->map(function ($item) use ($originalSentQuantities, $actualReceivedQuantities, $user) {
                    // جلب الكميات المرسلة والمستلمة من confirmationDetails إذا كانت موجودة
                    $sentQty = $originalSentQuantities[$item->id] ?? $item->fulfilled_qty ?? $item->approved_qty ?? 0;
                    $receivedQty = $actualReceivedQuantities[$item->id] ?? null;
                    
                    // التحقق من وجود الدواء في مخزون المورد والحصول على الكمية المتوفرة الفعلية
                    // جمع جميع الكميات المتوفرة من جميع الدفعات
                    $availableQuantity = Inventory::where('drug_id', $item->drug_id)
                        ->where('supplier_id', $user->supplier_id)
                        ->whereNull('warehouse_id')
                        ->whereNull('pharmacy_id')
                        ->whereNull('department_id')
                        ->sum('current_quantity');
                    
                    $availableQuantity = (int) $availableQuantity;
                    
                    // جلب تواريخ انتهاء الصلاحية من Inventory للكمية المرسلة
                    $expiryDates = [];
                    if ($sentQty > 0 && $user->supplier_id) {
                        // البحث عن الكميات في Inventory التي تطابق batch_number من item
                        if ($item->batch_number) {
                            $inventories = Inventory::where('drug_id', $item->drug_id)
                                ->where('supplier_id', $user->supplier_id)
                                ->where('batch_number', $item->batch_number)
                                ->where('current_quantity', '>', 0)
                                ->orderBy('expiry_date', 'asc')
                                ->get();
                            
                            $totalQuantity = 0;
                            foreach ($inventories as $inv) {
                                if ($inv->expiry_date && $totalQuantity < $sentQty) {
                                    $remainingNeeded = $sentQty - $totalQuantity;
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
                        
                        // إذا لم نجد كميات تطابق batch_number، نستخدم expiry_date و batch_number من item مباشرة
                        if (empty($expiryDates) && ($item->expiry_date || $item->batch_number)) {
                            $expiryDates[] = [
                                'batchNumber' => $item->batch_number,
                                'expiryDate' => $item->expiry_date,
                                'quantity' => $sentQty
                            ];
                        }
                    }


                    return [
                        'id' => $item->id,
                        'drugId' => $item->drug_id,
                        'name' => $item->drug->name ?? 'غير محدد',
                        'drugName' => $item->drug->name ?? 'غير محدد',
                        'drugCode' => $item->drug->code ?? '',
                        'category' => $item->drug
                            ? (is_object($item->drug->category)
                                ? ($item->drug->category->name ?? $item->drug->category)
                                : ($item->drug->category ?? 'غير محدد'))
                            : 'غير محدد',
                        'quantity' => ($item->requested_qty ?? $item->requested_quantity ?? 0),
                        'requestedQuantity' => ($item->requested_qty ?? $item->requested_quantity ?? 0),
                        'requested_qty' => ($item->requested_qty ?? 0),
                        'approvedQuantity' => ($item->approved_qty ?? $item->approved_quantity ?? 0),
                        'approved_qty' => ($item->approved_qty ?? 0),
                        'fulfilled_qty' => $item->fulfilled_qty,
                        'availableQuantity' => $availableQuantity, // الكمية المتوفرة الفعلية من مخزون المورد
                        'sentQuantity' => $sentQty,
                        'receivedQuantity' => $receivedQty,
                        'unit' => $item->drug->unit ?? 'قرص',
                        'units_per_box' => $item->drug->units_per_box ?? 1,
                        'dosage' => $item->drug->strength ?? null,
                        'strength' => $item->drug->strength ?? null,
                        'form' => $item->drug->form ?? null,
                        'type' => $item->drug->form ?? null,
                        'expiryDates' => $expiryDates,
                        'batchNumber' => $item->batch_number,
                        'batch_number' => $item->batch_number,
                        'expiryDate' => $item->expiry_date,
                        'expiry_date' => $item->expiry_date,
                    ];
                }),
                'createdAt' => $shipment->created_at->format('Y/m/d H:i'),
                'updatedAt' => $shipment->updated_at->format('Y/m/d H:i'),
            ];

            return $this->sendSuccess($data, 'تم جلب تفاصيل الشحنة بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Shipment Show Error');
        }
    }

    /**
     * تأكيد الشحنة وإرسالها
     * POST /api/supplier/shipments/{id}/confirm
     * عند القبول، يحدد Supplier:
     * - approved_qty: الكمية المعتمدة من Supplier
     * - fulfilled_qty: الكمية الفعلية المرسلة من Supplier
     * ثم يغير الحالة إلى 'fulfilled' ليتمكن StoreKeeper من تأكيد الاستلام
     */
    /**
     * تأكيد الشحنة وإرسالها
     * POST /api/supplier/shipments/{id}/confirm
     * عند القبول، يحدد Supplier:
     * - approved_qty: الكمية المعتمدة من Supplier
     * - fulfilled_qty: الكمية الفعلية المرسلة من Supplier
     * ثم يغير الحالة إلى 'fulfilled' ليتمكن StoreKeeper من تأكيد الاستلام
     */
    public function confirm(ConfirmShipmentRequest $request, $id)
    {
        \Log::info('=== Starting supplier shipment confirmation ===', ['id' => $id]);
        DB::beginTransaction();
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }


            $shipment = ExternalSupplyRequest::with('items.drug')
                ->where('supplier_id', $user->supplier_id)
                ->findOrFail($id);

            // التحقق من أن الطلب ليس في حالة مغلقة
            if (in_array($shipment->status, ['fulfilled', 'rejected', 'cancelled', 'delivered'])) {
                return $this->sendError('لا يمكن تعديل طلب تم إغلاقه مسبقاً', null, 409);
            }

            // التحقق من البيانات المرسلة
            $data = $request->validated();
            
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $item = $shipment->items->firstWhere('id', $itemData['id'] ?? null);
                    
                    // Fallback search logic
                    if (!$item) {
                        $searchId = $itemData['id'] ?? null;
                        $item = $shipment->items->firstWhere('drug_id', $searchId);
                        
                        if (!$item && (isset($itemData['drugId']) || isset($itemData['drug_id']))) {
                            $dId = $itemData['drugId'] ?? $itemData['drug_id'];
                            $item = $shipment->items->firstWhere('drug_id', $dId);
                        }
                    }

                    if (!$item) continue;

                    $qtyToFulfill = $itemData['receivedQuantity'] ??
                                     $itemData['fulfilled_qty'] ?? 
                                     $itemData['sentQuantity'] ?? 
                                     $itemData['approved_qty'] ?? 
                                     $item->requested_qty;
                    
                    $qtyToFulfill = max(0, (float)$qtyToFulfill);
                    
                    if ($qtyToFulfill > 0) {
                        // 1. التحقق من توفر المخزون الإجمالي للمورد
                        $supplierInventories = Inventory::where('drug_id', $item->drug_id)
                            ->where('supplier_id', $user->supplier_id)
                            ->whereNull('warehouse_id')
                            ->whereNull('pharmacy_id')
                            ->whereNull('department_id')
                            ->where('current_quantity', '>', 0)
                            ->orderBy('expiry_date', 'asc') // FIFO: الأقدم صلاحية أولاً
                            ->orderBy('created_at', 'asc')
                            ->get();

                        $totalAvailable = $supplierInventories->sum('current_quantity');

                        // إذا كانت الكمية المطلوبة أكبر من المتوفر، نرفض العملية
                        if ($totalAvailable < $qtyToFulfill) {
                            DB::rollBack();
                            $drugName = $item->drug ? $item->drug->name : 'Unknown Drug';
                            return $this->sendError("الكمية غير متوفرة في مخزون المورد للدواء: {$drugName}. المتاح: {$totalAvailable}, المطلوب: {$qtyToFulfill}", null, 409);
                        }

                        // 2. توزيع الكمية على الدفعات (Splitting Logic)
                        $remainingToDeduct = $qtyToFulfill;
                        $batchesUsed = [];

                        foreach ($supplierInventories as $inventory) {
                            if ($remainingToDeduct <= 0) break;

                            $deducted = min($inventory->current_quantity, $remainingToDeduct);
                            
                            // خصم الكمية من المخزون
                            $inventory->current_quantity -= $deducted;
                            $inventory->save();

                            $remainingToDeduct -= $deducted;

                            // تسجيل الدفعة المستخدمة
                            $batchesUsed[] = [
                                'batch_number' => $inventory->batch_number,
                                'expiry_date' => $inventory->expiry_date,
                                'quantity' => $deducted
                            ];
                        }

                        // 3. تحديث عنصر الشحنة (Shipment Item)
                        if (count($batchesUsed) > 0) {
                            // الدفعة الأولى تحدث السطر الأصلي
                            $firstBatch = array_shift($batchesUsed);
                            
                            // نضبط approved_qty (الكمية المعتمدة) لتكون نفس الكمية المرسلة لهذه الدفعة، حفاظاً على التناسق
                            // ملاحظة: approved_qty الأصلية من HospitalAdmin قد تكون مختلفة، لكن هنا المورد يحدد ما تم إرساله
                            // يمكنك اختيار إبقاء approved_qty الأصلية في السطر الأول فقط إذا أردت
                            
                            $item->fulfilled_qty = $firstBatch['quantity'];
                            $item->batch_number = $firstBatch['batch_number'];
                            $item->expiry_date = $firstBatch['expiry_date'];
                            $item->save();
                            
                            // إنشاء سجلات جديدة لباقي الدفعات
                            foreach ($batchesUsed as $batch) {
                                $newItem = $item->replicate();
                                $newItem->request_id = $shipment->id;
                                $newItem->drug_id = $item->drug_id;
                                $newItem->requested_qty = 0; // سطر فرعي
                                $newItem->approved_qty = 0; // سطر فرعي
                                $newItem->fulfilled_qty = $batch['quantity'];
                                $newItem->batch_number = $batch['batch_number'];
                                $newItem->expiry_date = $batch['expiry_date'];
                                $newItem->save();
                            }
                        }
                    } else {
                        // إذا كانت الكمية 0
                        $item->fulfilled_qty = 0;
                        $item->save();
                    }
                }
            }

            // تحديث الحالة إلى 'fulfilled' (تم التنفيذ/الارسال) لكي تظهر لمسؤول المخزن للاستلام
            $oldStatus = $shipment->status;
            $shipment->status = 'fulfilled';
            $shipment->save();

            // حفظ الملاحظات وتسجيل العملية في audit_log (دائماً، مع أو بدون ملاحظات)
            $notes = $data['notes'] ?? null;
            try {
                // إعداد البيانات للـ audit log
                $newValues = [
                    'status' => 'fulfilled',
                ];
                
                // إضافة معلومات الكميات المرسلة
                $sentItems = [];
                foreach ($shipment->items as $item) {
                    if ($item->fulfilled_qty > 0) {
                        $sentItems[] = [
                            'item_id' => $item->id,
                            'drug_id' => $item->drug_id,
                            'drug_name' => $item->drug->name ?? 'غير محدد',
                            'sentQuantity' => $item->fulfilled_qty,
                            'batch_number' => $item->batch_number,
                            'expiry_date' => $item->expiry_date,
                        ];
                    }
                }
                $newValues['items'] = $sentItems;
                
                if ($notes) {
                    $newValues['notes'] = $notes;
                }
                
                \App\Models\AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => $shipment->hospital_id,
                    'action' => 'supplier_confirm_external_supply_request',
                    'table_name' => 'external_supply_request',
                    'record_id' => $shipment->id,
                    'old_values' => json_encode(['status' => $oldStatus]),
                    'new_values' => json_encode($newValues),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                \Log::warning('Failed to log confirmation', ['error' => $e->getMessage()]);
            }

            try {
                // $this->notifications->notifyWarehouseSupplierAccepted($shipment);
            } catch (\Exception $e) {
                \Log::error('Failed to notify warehouse manager about supplier acceptance', ['error' => $e->getMessage()]);
            }

            DB::commit();

            return $this->sendSuccess([
                'id' => $shipment->id,
                'status' => $this->translateStatus($shipment->status),
            ], 'تم تأكيد الشحنة وإرسالها بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleException($e, 'Supplier Confirm Shipment Error');
        }
    }

    /**
     * رفض الشحنة
     * POST /api/supplier/shipments/{id}/reject
     * عند الرفض، يظهر الرفض لـ StoreKeeper ولا يمكن تأكيد الاستلام
     */
    public function reject(RejectShipmentRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $shipment = ExternalSupplyRequest::where('supplier_id', $user->supplier_id)
                ->findOrFail($id);

            // يجب أن تكون الحالة 'approved' (معتمدة من HospitalAdmin)
            if ($shipment->status !== 'approved') {
                return $this->sendError('لا يمكن رفض هذه الشحنة. يجب أن تكون معتمدة من مدير المستشفى أولاً.', null, 400);
            }

            // حفظ سبب الرفض قبل تحديث الحالة
            $rejectionReason = $request->input('reason') ?? $request->input('rejectionReason') ?? '';
            
            // تحديث الحالة إلى 'rejected'
            $oldStatus = $shipment->status;
            $shipment->status = 'rejected';
            $shipment->save();
            
            // حفظ سبب الرفض في audit_log
            try {
                $auditLog = \App\Models\AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => $shipment->hospital_id,
                    'action' => 'supplier_reject_external_supply_request',
                    'table_name' => 'external_supply_request',
                    'record_id' => $shipment->id,
                    'old_values' => json_encode(['status' => $oldStatus]),
                    'new_values' => json_encode([
                        'status' => 'rejected',
                        'rejectionReason' => $rejectionReason,
                        'reason' => $rejectionReason, // للتوافق
                        'rejected_by' => $user->id
                    ]),
                    'ip_address' => $request->ip(),
                ]);
                \Log::info('Audit log created for rejection', [
                    'audit_log_id' => $auditLog->id,
                    'rejectionReason' => $rejectionReason
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to log rejection', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            try {
                $this->notifications->notifyWarehouseSupplierRejected($shipment, $rejectionReason);
            } catch (\Exception $e) {
                \Log::error('Failed to notify warehouse manager about supplier rejection', ['error' => $e->getMessage()]);
            }

            DB::commit();

            return $this->sendSuccess([
                'id' => $shipment->id,
                'status' => $this->translateStatus($shipment->status),
            ], 'تم رفض الشحنة بنجاح');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleException($e, 'Supplier Reject Shipment Error');
        }
    }

    /**
     * ترجمة حالة الشحنة للعربية
     */
    private function translateStatus($status)
    {
        $statuses = [
            'pending' => 'قيد الانتظار',
            'approved' => 'جديد',
            'fulfilled' => 'قيد الاستلام',
            'delivered' => 'تم الاستلام',
            'rejected' => 'مرفوض',
            'cancelled' => 'مرفوض',
        ];

        return $statuses[$status] ?? $status;
    }
}
