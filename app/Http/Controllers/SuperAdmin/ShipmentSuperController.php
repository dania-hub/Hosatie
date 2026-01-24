<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use App\Models\ExternalSupplyRequest;
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
     * عرض قائمة الشحنات (طلبات التوريد الخارجية)
     * فقط الطلبات التي أنشأها الموردون وليس المستشفيات
     */
    public function index(Request $request)
    {
        try {
            // جلب البيانات مع العلاقات
            // فقط الطلبات التي أنشأها الموردون (supplier_admin) وليس المستشفيات (warehouse_manager)
            $query = ExternalSupplyRequest::with(['supplier', 'items.drug', 'requester'])
                ->whereHas('requester', function($q) {
                    // فقط الطلبات من الموردين
                    $q->where('type', 'supplier_admin');
                });

            // يمكنك إضافة فلاتر هنا إذا لزم الأمر
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $shipments = $query->latest()->get()->map(function ($shipment) {
                return [
                    'id' => $shipment->id,
                    'shipmentNumber' => 'EXT-' . $shipment->id, // أو أي حقل آخر لرقم الشحنة
                    'requestingDepartment' => $shipment->supplier ? $shipment->supplier->name : 'غير محدد', // أو المستشفى إذا كان هو الطالب
                    'department' => $shipment->supplier ? $shipment->supplier->name : 'غير محدد',
                    'requestDate' => $shipment->created_at->format('Y-m-d'),
                    'createdAt' => $shipment->created_at,
                    'status' => match($shipment->status) {
                        'pending' => 'جديد',
                        'approved' => 'قيد الاستلام', 
                        'fulfilled' => 'تم الإستلام',
                        'rejected' => 'مرفوض',
                        default => $shipment->status,
                    },
                    'rejectionReason' => $shipment->rejection_reason,
                    'rejectedAt' => $shipment->status === 'rejected' && $shipment->handeled_at ? $shipment->handeled_at->format('Y-m-d H:i:s') : null,
                    'confirmedAt' => $shipment->updated_at, // افتراضاً
                    'items' => $shipment->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->drug ? $item->drug->name : 'غير معروف',
                            'quantity' => $item->requested_qty,
                            'requested_qty' => $item->requested_qty, // Ensure frontend receives this
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
     * فقط الطلبات التي أنشأها الموردون
     */
    public function show($id)
    {
        try {
            $shipment = ExternalSupplyRequest::with(['supplier', 'items.drug', 'requester'])
                ->whereHas('requester', function($q) {
                    // فقط الطلبات من الموردين
                    $q->where('type', 'supplier_admin');
                })
                ->findOrFail($id);

            // Extract the initial supplier message for the frontend "Supplier Message" box
            $messages = $shipment->messages ?? [];
            $initialNote = '';
            if (is_array($messages)) {
                $supplierNote = collect($messages)->firstWhere('by', 'supplier_admin');
                $initialNote = $supplierNote['message'] ?? '';
                
                // Fallback: if no typed message found but array exists, try first item
                if (!$initialNote && !empty($messages[0]['message'])) {
                     $initialNote = $messages[0]['message'];
                }
            } else if (is_string($messages)) {
                $initialNote = $messages;
            }

            return $this->sendSuccess([
                'id' => $shipment->id,
                'shipmentNumber' => 'EXT-' . $shipment->id,
                'department' => $shipment->supplier ? $shipment->supplier->name : 'غير محدد',
                'date' => $shipment->created_at->format('Y-m-d'),
                'status' => match($shipment->status) {
                    'pending' => 'جديد',
                    'approved' => 'قيد الاستلام',
                    'fulfilled' => 'تم الإستلام',
                    'rejected' => 'مرفوض',
                    default => $shipment->status,
                },
                'rejectionReason' => $shipment->rejection_reason,
                'rejectedAt' => $shipment->rejected_at ? $shipment->rejected_at->format('Y-m-d H:i:s') : null,
                'notes' => $messages, // Return full thread (array or string fallback)
                'notes_initial' => $initialNote, // Keep mainly for backup/debugging
                'conversation' => $messages, // Return full thread
                'items' => $shipment->items->map(function ($item) {
                    // حساب المخزون المتوفر للدواء (إجمالي في جميع المستودعات كمثال، أو 0 إذا لم ينطبق)
                    // بما أن السوبر أدمن قد لا يكون لديه مخزون خاص، سنعرض إجمالي المخزون المتوفر
                    $stock = 0; 
                    if ($item->drug_id) {
                         $stock = Inventory::where('drug_id', $item->drug_id)->sum('current_quantity');
                    }

                    return [
                        'id' => $item->id,
                        'drug_id' => $item->drug_id,
                        'drugId' => $item->drug_id,
                        'name' => $item->drug ? $item->drug->name : 'غير معروف',
                        'quantity' => $item->requested_qty, // الكمية المطلوبة
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
     * PUT /api/super-admin/shipments/{id}/approve
     * فقط الطلبات التي أنشأها الموردون
     */
    public function approve(Request $request, $id)
    {
        try {
            $shipment = ExternalSupplyRequest::with(['items', 'supplier', 'hospital', 'requester'])
                ->whereHas('requester', function($q) {
                    // فقط الطلبات من الموردين
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

            if ($request->filled('notes')) {
                $shipment->addNote($request->input('notes'), $request->user());
            }

            try {
                \App\Models\AuditLog::create([
                    'user_id' => $request->user()->id,
                    'hospital_id' => $shipment->hospital_id,
                    'action' => 'super_admin_approve_external_supply_request',
                    'table_name' => 'external_supply_requests',
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
     * PUT /api/super-admin/shipments/{id}/reject
     * فقط الطلبات التي أنشأها الموردون
     */
    public function reject(Request $request, $id)
    {
        try {
            $shipment = ExternalSupplyRequest::with(['items', 'supplier', 'hospital', 'requester'])
                ->whereHas('requester', function($q) {
                    // فقط الطلبات من الموردين
                    $q->where('type', 'supplier_admin');
                })
                ->findOrFail($id);

            if ($shipment->status !== 'pending') {
                 return $this->sendError('الطلب ليس في حالة انتظار، لا يمكن تعديل حالته', null, 400);
            }

            $shipment->status = 'rejected';
            $shipment->handeled_by = $request->user()->id;
            $shipment->handeled_at = now();
            $shipment->rejection_reason = $request->input('rejection_reason', $request->input('rejectionReason', $request->input('notes'))); // Use notes as reason if not provided explicitly
            $shipment->save();

            if ($request->filled('notes')) {
                $shipment->addNote($request->input('notes'), $request->user());
            }

            try {
                \App\Models\AuditLog::create([
                    'user_id' => $request->user()->id,
                    'hospital_id' => $shipment->hospital_id,
                    'action' => 'super_admin_reject_external_supply_request',
                    'table_name' => 'external_supply_requests',
                    'record_id' => $shipment->id,
                    'new_values' => json_encode([
                        'status' => 'rejected',
                        'rejection_reason' => $shipment->rejection_reason,
                        'handeled_at' => $shipment->handeled_at
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
     * فقط الطلبات التي أنشأها الموردون
     */
    public function confirm(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $shipment = ExternalSupplyRequest::with(['items', 'hospital.warehouse', 'requester'])
                ->whereHas('requester', function($q) {
                    // فقط الطلبات من الموردين
                    $q->where('type', 'supplier_admin');
                })
                ->findOrFail($id);
            
            // مسار "تأكيد الإرسال": تحديث approved_qty فقط (بدون fulfilled_qty)
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

                if ($request->filled('notes')) {
                    $shipment->addNote($request->input('notes'), $request->user());
                }

                DB::commit();
                return $this->sendSuccess($shipment, 'تم تأكيد إرسال الشحنة بنجاح');
            }

            // تحقق من أن الشحنة لم يتم استلامها مسبقاً
            if ($shipment->status === 'fulfilled' || $shipment->status === 'تم الإستلام') {
                return $this->sendError('تم استلام هذه الشحنة مسبقاً', null, 400);
            }

            // استقبال الكميات المستلمة من الواجهة
            $receivedItems = $request->input('receivedItems', []);
            $receivedItemsMap = [];
            foreach ($receivedItems as $receivedItem) {
                 $itemId = $receivedItem['id'] ?? null;
                 $qty = $receivedItem['receivedQuantity'] ?? null;
                 if ($itemId !== null) {
                     $receivedItemsMap[(int)$itemId] = (float)$qty;
                 }
            }

            // تحقق من وجود نقص
            $hasShortage = false;
            foreach ($shipment->items as $item) {
                // الكمية المتوقعة (الموافق عليها أو المطلوبة)
                $expectedQty = $item->approved_qty ?? $item->requested_qty ?? 0;
                // الكمية المستلمة فعلياً
                $receivedQty = isset($receivedItemsMap[(int)$item->id]) ? $receivedItemsMap[(int)$item->id] : $expectedQty;
                
                if ($receivedQty < $expectedQty) {
                    $hasShortage = true;
                    // break; // لا نوقف الحلقة لنحسب الجميع
                }
            }

            if ($hasShortage && empty(trim($request->input('notes', '')))) {
                DB::rollBack();
                return $this->sendError('يجب إدخال ملاحظات لتوضيح سبب النقص في الكمية المستلمة.', null, 400);
            }

            // العثور على المستودع الخاص بالمستشفى
            $warehouse = $shipment->hospital->warehouse;
            if (!$warehouse) {
                 // محاولة العثور على المستودع يدوياً إذا لم تكن العلاقة محملة أو موجودة
                 $warehouse = Warehouse::where('hospital_id', $shipment->hospital_id)->first();
                 
                 if (!$warehouse) {
                     // إنشاء مستودع افتراضي إذا لم يوجد
                     $warehouse = Warehouse::create([
                         'hospital_id' => $shipment->hospital_id,
                         'name' => 'المستودع الرئيسي',
                         'status' => 'active'
                     ]);
                 }
            }

            // تحديث المخزون
            foreach ($shipment->items as $item) {
                $expectedQty = $item->approved_qty ?? $item->requested_qty ?? 0;
                $receivedQty = isset($receivedItemsMap[(int)$item->id]) ? $receivedItemsMap[(int)$item->id] : $expectedQty;
                
                if ($receivedQty <= 0) continue;

                // تحديث أو إنشاء المخزون في المستودع
                $inventory = Inventory::firstOrNew([
                    'warehouse_id' => $warehouse->id,
                    'drug_id' => $item->drug_id,
                ]);
                
                // التأكد من أن pharmacy_id فارغ لأنه مستودع
                $inventory->pharmacy_id = null; 

                $inventory->current_quantity = ($inventory->current_quantity ?? 0) + $receivedQty;
                $inventory->save();

                // تحديث الكمية المستلمة في البند
                $item->fulfilled_qty = $receivedQty;
                $item->save();
            }

            // تحديث حالة الطلب
            $shipment->status = 'fulfilled';
            $shipment->save();

            if ($request->filled('notes')) {
                $shipment->addNote($request->input('notes'), $request->user());
            }

            // تسجيل في AuditLog
             \App\Models\AuditLog::create([
                'user_id'     => $request->user()->id,
                'hospital_id' => $shipment->hospital_id,
                'action'      => 'super_admin_confirm_external_supply_request',
                'table_name'  => 'external_supply_request',
                'record_id'   => $shipment->id,
                'new_values'  => json_encode([
                    'status' => 'fulfilled',
                    'notes' => $request->input('notes'),
                    'request_id' => $shipment->id,
                    'supplier_id' => $shipment->supplier_id
                ]),
                'old_values' => json_encode(['status' => $shipment->getOriginal('status')]),
                'ip_address'  => $request->ip(),
            ]);

            DB::commit();

            // إشعار المورد
            try {
                $statusArabic = 'تم الإستلام';
                $this->notifications->notifySupplierAboutSuperAdminResponse($shipment, $statusArabic, $request->input('notes'));
            } catch (\Exception $e) {
                \Log::error('Failed to notify supplier', ['error' => $e->getMessage()]);
            }
            
            // Return with translated status for consistency
            $shipment->status = 'تم الإستلام';

            return $this->sendSuccess($shipment, 'تم تأكيد استلام الشحنة وتحديث المخزون بنجاح');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleException($e, 'Super Admin Shipment Confirm Error');
        }
    }
}
