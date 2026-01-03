<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\BaseApiController;
use App\Models\ExternalSupplyRequest;
use App\Models\Inventory;
use App\Http\Requests\Supplier\ConfirmShipmentRequest;
use App\Http\Requests\Supplier\RejectShipmentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShipmentSupplierController extends BaseApiController
{
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
                ->whereIn('status', ['approved', 'fulfilled', 'rejected']) // جميع الحالات المعتمدة والمكتملة والمرفوضة
                ->orderBy('created_at', 'desc')
                ->get()
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
                'items.drug:id,name,category,strength,unit,form',
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
                    $availableQuantity = 0;
                    $inventory = Inventory::where('drug_id', $item->drug_id)
                        ->where('supplier_id', $user->supplier_id)
                        ->first();
                    
                    if ($inventory) {
                        $availableQuantity = (int) $inventory->current_quantity;
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
                        'quantity' => $item->requested_qty ?? $item->requested_quantity ?? 0,
                        'requestedQuantity' => $item->requested_qty ?? $item->requested_quantity ?? 0,
                        'requested_qty' => $item->requested_qty ?? 0,
                        'approvedQuantity' => $item->approved_qty ?? $item->approved_quantity ?? 0,
                        'approved_qty' => $item->approved_qty ?? 0,
                        'fulfilled_qty' => $item->fulfilled_qty ?? null,
                        'availableQuantity' => $availableQuantity, // الكمية المتوفرة الفعلية من مخزون المورد
                        'sentQuantity' => $sentQty,
                        'receivedQuantity' => $receivedQty,
                        'unit' => $item->drug->unit ?? 'وحدة',
                        'dosage' => $item->drug->strength ?? null,
                        'strength' => $item->drug->strength ?? null,
                        'form' => $item->drug->form ?? null,
                        'type' => $item->drug->form ?? null,
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
    public function confirm(ConfirmShipmentRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $shipment = ExternalSupplyRequest::with('items')
                ->where('supplier_id', $user->supplier_id)
                ->findOrFail($id);

            // يجب أن تكون الحالة 'approved' (معتمدة من HospitalAdmin)
            if ($shipment->status !== 'approved') {
                return $this->sendError('لا يمكن تأكيد هذه الشحنة. يجب أن تكون معتمدة من مدير المستشفى أولاً.', null, 400);
            }

            // التحقق من البيانات المرسلة
            $data = $request->validated();
            
            // ملاحظة: العلاقة الصحيحة للكميات:
            // - requested_qty: الكمية المطلوبة من StoreKeeper
            // - approved_qty: الكمية المعتمدة من Supplier (هنا)
            // - fulfilled_qty: الكمية الفعلية المرسلة من Supplier (هنا)
            
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $item = $shipment->items->firstWhere('id', $itemData['id'] ?? null);
                    if ($item) {
                        // الكمية المعتمدة من Supplier
                        $approvedQty = $itemData['approved_qty'] ?? null;
                        if ($approvedQty === null) {
                            // إذا لم يتم إرسال approved_qty، نستخدم fulfilled_qty أو sentQuantity أو requested_qty
                            $approvedQty = $itemData['fulfilled_qty'] ?? 
                                          $itemData['sentQuantity'] ?? 
                                          $item->requested_qty;
                        }
                        
                        // الكمية الفعلية المرسلة من Supplier
                        $fulfilledQty = $itemData['fulfilled_qty'] ?? 
                                       $itemData['sentQuantity'] ?? 
                                       $approvedQty ?? 
                                       $item->requested_qty;
                        
                        // حفظ الكميات
                        $item->approved_qty = max(0, (float)$approvedQty); // الكمية المعتمدة من Supplier
                        $item->fulfilled_qty = max(0, (float)$fulfilledQty); // الكمية الفعلية المرسلة من Supplier
                        $item->save();
                    }
                }
            } else {
                // إذا لم يتم إرسال items، نستخدم requested_qty كقيمة افتراضية
                foreach ($shipment->items as $item) {
                    $defaultQty = $item->requested_qty;
                    $item->approved_qty = $defaultQty; // الكمية المعتمدة من Supplier
                    $item->fulfilled_qty = $defaultQty; // الكمية الفعلية المرسلة من Supplier
                    $item->save();
                }
            }

            // تحديث الحالة إلى 'fulfilled' (تم الإرسال)
            // الآن يمكن لـ StoreKeeper تأكيد الاستلام
            $shipment->status = 'fulfilled';
            $shipment->save();

            // حفظ الملاحظات في audit_log إذا كانت موجودة
            $notes = $data['notes'] ?? null;
            if ($notes) {
                try {
                    \App\Models\AuditLog::create([
                        'user_id' => $user->id,
                        'hospital_id' => $shipment->hospital_id,
                        'action' => 'supplier_confirm_external_supply_request',
                        'table_name' => 'external_supply_request',
                        'record_id' => $shipment->id,
                        'old_values' => json_encode(['status' => 'approved']),
                        'new_values' => json_encode([
                            'status' => 'fulfilled',
                            'notes' => $notes
                        ]),
                        'ip_address' => $request->ip(),
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('Failed to log confirmation notes', ['error' => $e->getMessage()]);
                }
            }

            DB::commit();

            return $this->sendSuccess([
                'id' => $shipment->id,
                'status' => $this->translateStatus($shipment->status),
            ], 'تم تأكيد الشحنة وإرسالها بنجاح. يمكن لمسؤول المخزن تأكيد الاستلام.');
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
        ];

        return $statuses[$status] ?? $status;
    }
}
