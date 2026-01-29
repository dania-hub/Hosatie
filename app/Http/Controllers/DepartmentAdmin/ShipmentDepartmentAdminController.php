<?php

namespace App\Http\Controllers\DepartmentAdmin;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\InternalSupplyRequest;
use App\Models\AuditLog;
use App\Models\Department;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Notification;

use App\Services\StaffNotificationService;

class ShipmentDepartmentAdminController extends BaseApiController
{
    public function __construct(
        private StaffNotificationService $notifications
    ) {}
    /**
     * جلب الملاحظات من audit_log
     */
    private function getNotesFromAuditLog($requestId)
    {
        $notes = [
            'storekeeperNotes' => null,  // ملاحظة عند إنشاء الطلب من pharmacist/department
            'storekeeperNotesSource' => null, // مصدر الملاحظة: 'pharmacist' أو 'department'
            'supplierNotes' => null,     // ملاحظة عند إرسال الشحنة من storekeeper
            'confirmationNotes' => null,  // ملاحظة عند تأكيد الاستلام من pharmacist/department
            'confirmationNotesSource' => null, // مصدر الملاحظة: 'pharmacist' أو 'department'
            'rejectionReason' => null    // سبب الرفض من storekeeper
        ];

        // جلب جميع سجلات audit_log لهذا الطلب
        $auditLogs = AuditLog::where('table_name', 'internal_supply_request')
            ->where('record_id', $requestId)
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($auditLogs as $log) {
            $newValues = json_decode($log->new_values, true);
            if (!$newValues) continue;

            // ملاحظة عند إنشاء الطلب
            if (in_array($log->action, ['إنشاء طلب توريد', 'pharmacist_create_supply_request', 'department_create_supply_request']) 
                && isset($newValues['notes']) && !empty($newValues['notes'])) {
                $notes['storekeeperNotes'] = $newValues['notes'];
                if ($log->action === 'pharmacist_create_supply_request') {
                    $notes['storekeeperNotesSource'] = 'pharmacist';
                } elseif ($log->action === 'department_create_supply_request') {
                    $notes['storekeeperNotesSource'] = 'department';
                }
            }

            // ملاحظة عند إرسال الشحنة من storekeeper
            if ($log->action === 'storekeeper_confirm_internal_request' 
                && isset($newValues['notes']) && !empty($newValues['notes'])) {
                $notes['supplierNotes'] = $newValues['notes'];
            }

            // ملاحظة عند تأكيد الاستلام
            if (in_array($log->action, ['pharmacist_confirm_internal_receipt', 'department_confirm_internal_receipt'])
                && isset($newValues['notes']) && !empty($newValues['notes'])) {
                $notes['confirmationNotes'] = $newValues['notes'];
                if ($log->action === 'pharmacist_confirm_internal_receipt') {
                    $notes['confirmationNotesSource'] = 'pharmacist';
                } elseif ($log->action === 'department_confirm_internal_receipt') {
                    $notes['confirmationNotesSource'] = 'department';
                }
            }

            // سبب الرفض من storekeeper
            if (in_array($log->action, ['رفض طلب توريد داخلي', 'storekeeper_reject_internal_request', 'reject'])
                && isset($newValues['rejectionReason']) && !empty($newValues['rejectionReason'])) {
                $notes['rejectionReason'] = $newValues['rejectionReason'];
            }
        }

        return $notes;
    }

    /**
     * GET /api/department-admin/shipments
     * List incoming shipments for this department
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // البحث عن القسم الذي يديره المستخدم الحالي
        $currentDepartment = null;
        if ($user->type === 'department_head' || $user->type === 'department_admin') {
            // البحث عن القسم الذي يكون head_user_id = user->id
            $currentDepartment = Department::where('head_user_id', $user->id)
                ->where('hospital_id', $user->hospital_id)
                ->first();
        }
        
        \Log::info('Department Admin Shipments - User Info', [
            'user_id' => $user->id,
            'user_type' => $user->type,
            'department_found' => $currentDepartment ? $currentDepartment->id : null,
            'department_name' => $currentDepartment ? $currentDepartment->name : null,
        ]);
        
        // إذا لم يتم العثور على قسم، نستخدم الطريقة القديمة (الطلبات التي طلبها المستخدم الحالي فقط)
        if (!$currentDepartment) {
            \Log::info('No department found, using user-based query', ['user_id' => $user->id]);
            $query = InternalSupplyRequest::with(['items.drug', 'requester'])
                ->where('requested_by', $user->id)
                ->orderBy('created_at', 'desc');
        } else {
            // في حالة وجود قسم للمستخدم، نعرض فقط الطلبات المرتبطة بهذا القسم
            // أي InternalSupplyRequest التي يكون فيها department_id = قسم المستخدم الحالي
            $departmentId = $currentDepartment->id;

            \Log::info('Building department-based query for shipments', [
                'department_id' => $departmentId,
                'user_id' => $user->id,
            ]);

            $query = InternalSupplyRequest::with(['items.drug', 'requester'])
                ->where('department_id', $departmentId)
                ->orderBy('created_at', 'desc');
        }

        $shipments = $query->get()->map(function ($shipment) {
            return [
                'id' => $shipment->id,
                'shipmentNumber' => 'REQ-' . $shipment->id,
                'requestDate' => $shipment->created_at->toIso8601String(),
                'status' => $this->translateStatus($shipment->status),
                'itemCount' => $shipment->items->count(),
                'items' => $shipment->items->map(function($item) {
                    return [
                        'id' => $item->id,
                        'drugName' => $item->drug->name ?? 'غير محدد',
                        'quantity' => $item->requested_qty,
                        'unit' => $item->drug->unit ?? 'وحدة',
                        'units_per_box' => $item->drug->units_per_box ?? 1,
                        'unitsPerBox'   => $item->drug->units_per_box ?? 1,
                    ];
                }),
                'notes' => $shipment->notes,
                'received' => $shipment->status === 'fulfilled',
                'confirmationDetails' => $shipment->status === 'fulfilled' ? [
                    'confirmedAt' => $shipment->updated_at->format('Y-m-d H:i')
                ] : null,
            ];
        });

        return $this->sendSuccess($shipments, 'تم جلب الشحنات الواردة بنجاح.');
    }
    
    /**
     * ترجمة حالة الطلب
     */
    private function translateStatus($status)
    {
        $translations = [
            'pending' => 'قيد الإنتظار',
            'approved' =>  'قيد الاستلام',
            'rejected' => 'مرفوضة',
            'fulfilled' => 'تم الإستلام',
            'cancelled' => 'ملغاة',
        ];

        return $translations[$status] ?? $status;
    }

    /**
     * GET /api/department-admin/shipments/{id}
     * Show details of one shipment
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $shipment = InternalSupplyRequest::with(['items.drug', 'requester', 'pharmacy', 'department'])
            ->find($id);

        if (!$shipment) {
            return $this->sendError('الشحنة غير موجودة.', [], 404);
        }

        // التحقق من أن المستخدم الحالي لديه صلاحية لعرض الطلب (ينتمي لنفس القسم)
        $currentDepartment = null;
        if ($user->type === 'department_head' || $user->type === 'department_admin') {
            $currentDepartment = Department::where('head_user_id', $user->id)
                ->where('hospital_id', $user->hospital_id)
                ->first();
        }

        if ($currentDepartment) {
            // التحقق من أن الطلب ينتمي لنفس القسم
            $requestDepartmentId = null;
            $auditLog = AuditLog::where('table_name', 'internal_supply_request')
                ->where('record_id', $shipment->id)
                ->where('action', 'department_create_supply_request')
                ->first();
            
            if ($auditLog) {
                $newValues = json_decode($auditLog->new_values, true);
                if ($newValues && isset($newValues['department_id'])) {
                    $requestDepartmentId = $newValues['department_id'];
                }
            }
            $requestDepartmentId = $requestDepartmentId ?? $shipment->department_id;

            // إذا كان الطلب لا ينتمي لنفس القسم، نرفض الوصول
            if ($requestDepartmentId && $requestDepartmentId != $currentDepartment->id) {
                return $this->sendError('ليس لديك صلاحية لعرض هذه الشحنة.', [], 403);
            }
        } else {
            // إذا لم يكن المستخدم مدير قسم، نتحقق من أنه هو من أنشأ الطلب
            if ($shipment->requested_by != $user->id) {
                return $this->sendError('ليس لديك صلاحية لعرض هذه الشحنة.', [], 403);
            }
        }

        // جلب الملاحظات من audit_log
        $notes = $this->getNotesFromAuditLog($shipment->id);

        $shipmentData = [
            'id' => $shipment->id,
            'shipmentNumber' => 'REQ-' . $shipment->id,
            'status' => $this->translateStatus($shipment->status),
            'requestDate' => $shipment->created_at->toIso8601String(),
            'storekeeperNotes' => $notes['storekeeperNotes'],
            'storekeeperNotesSource' => $notes['storekeeperNotesSource'],
            'supplierNotes' => $notes['supplierNotes'],
            'confirmationNotes' => $notes['confirmationNotes'],
            'confirmationNotesSource' => $notes['confirmationNotesSource'],
            'rejectionReason' => $notes['rejectionReason'], // جلب سبب الرفض من audit_log
            'items' => $shipment->items->map(function($item) {
                // الكمية المرسلة من storekeeper هي approved_qty
                // إذا كانت null، نستخدم requested_qty كقيمة افتراضية (لكن هذا يعني أن الطلب لم يُرسل بعد)
                $sentQuantity = $item->approved_qty ?? null;
                
                return [
                    'id' => $item->id,
                    'drugId' => $item->drug_id,
                    'name' => $item->drug->name ?? 'غير محدد',
                    'drugName' => $item->drug->name ?? 'غير محدد',
                    'quantity' => $item->requested_qty,
                    'requestedQty' => $item->requested_qty,
                    'requested_qty' => $item->requested_qty,
                    'approvedQty' => $item->approved_qty,
                    'approved_qty' => $item->approved_qty,
                    'fulfilledQty' => $item->fulfilled_qty,
                    'fulfilled_qty' => $item->fulfilled_qty,
                    'sentQuantity' => $sentQuantity, // الكمية المرسلة من storekeeper
                    'receivedQuantity' => $item->fulfilled_qty, // الكمية المستلمة فقط
                    'unit' => $item->drug->unit ?? 'علبة',
                    'units_per_box' => $item->drug->units_per_box ?? 1,
                    'unitsPerBox'   => $item->drug->units_per_box ?? 1,
                    'genericName' => $item->drug->generic_name ?? null,
                    'strength' => $item->drug->strength ?? null,
                    'dosage' => $item->drug->strength ?? null,
                    'form' => $item->drug->form ?? null,
                    'type' => $item->drug->form ?? null,
                    'batch_number' => $item->batch_number,
                    'expiry_date' => $item->expiry_date,
                ];
            }),
            'notes' => $shipment->notes,
            'requester' => $shipment->requester ? $shipment->requester->full_name : 'غير محدد',
            'confirmationDetails' => $shipment->status === 'fulfilled' ? [
                'confirmedAt' => $shipment->updated_at->format('Y-m-d H:i')
            ] : null,
        ];

        return $this->sendSuccess($shipmentData, 'تم جلب تفاصيل الشحنة.');
    }

    /**
     * POST /api/department-admin/shipments/{id}/confirm
     * Confirm receipt and update stock
     */
    public function confirm(Request $request, $id)
    {
        $user = $request->user(); // Move this up

        $shipment = InternalSupplyRequest::with('items.drug')->find($id);
        
        if (!$shipment) {
            return $this->sendError('الشحنة غير موجودة.', [], 404);
        }
        
        if ($shipment->status === 'fulfilled') {
            return $this->sendError('تم استلام هذه الشحنة مسبقاً.', [], 400);
        }

        // التحقق من أن المستخدم الحالي لديه صلاحية لتأكيد الطلب (ينتمي لنفس القسم)
        $currentDepartment = null;
        if ($user->type === 'department_head' || $user->type === 'department_admin') {
            $currentDepartment = Department::where('head_user_id', $user->id)
                ->where('hospital_id', $user->hospital_id)
                ->first();
        }

        if ($currentDepartment) {
            // التحقق من أن الطلب ينتمي لنفس القسم
            $requestDepartmentId = null;
            $auditLog = AuditLog::where('table_name', 'internal_supply_request')
                ->where('record_id', $shipment->id)
                ->where('action', 'department_create_supply_request')
                ->first();
            
            if ($auditLog) {
                $newValues = json_decode($auditLog->new_values, true);
                if ($newValues && isset($newValues['department_id'])) {
                    $requestDepartmentId = $newValues['department_id'];
                }
            }
            $requestDepartmentId = $requestDepartmentId ?? $shipment->department_id;

            // إذا كان الطلب لا ينتمي لنفس القسم، نرفض الوصول
            if ($requestDepartmentId && $requestDepartmentId != $currentDepartment->id) {
                return $this->sendError('ليس لديك صلاحية لتأكيد هذه الشحنة.', [], 403);
            }
        } else {
            // إذا لم يكن المستخدم مدير قسم، نتحقق من أنه هو من أنشأ الطلب
            if ($shipment->requested_by != $user->id) {
                return $this->sendError('ليس لديك صلاحية لتأكيد هذه الشحنة.', [], 403);
            }
        }
        
        // تحديث حالة الطلب إلى fulfilled
        $shipment->status = 'fulfilled';
        $shipment->save();
        
        // الحصول على الكميات المستلمة من الطلب وتحديث fulfilled_qty
        $receivedItems = $request->input('receivedItems', []);
        $receivedItemsMap = [];
        foreach ($receivedItems as $receivedItem) {
            $itemId = $receivedItem['id'] ?? null;
            $receivedQtyInBoxes = $receivedItem['receivedQuantity'] ?? null;
            if ($itemId !== null && $receivedQtyInBoxes !== null) {
                // تحويل ID إلى integer للتأكد من المطابقة الصحيحة
                $receivedItemsMap[(int)$itemId] = (float)$receivedQtyInBoxes;
            }
        }
        
        $shortageItems = [];

        // تحقق من وجود نقص قبل المتابعة لإلزامية الملاحظات
        $hasShortageBefore = false;
        foreach ($shipment->items as $item) {
            $sentQty = $item->approved_qty ?? $item->requested_qty ?? 0;
            $receivedQty = isset($receivedItemsMap[(int)$item->id]) ? $receivedItemsMap[(int)$item->id] : $sentQty;
            if ($receivedQty < $sentQty) {
                $hasShortageBefore = true;
                break;
            }
        }

        if ($hasShortageBefore && empty(trim($request->input('notes', '')))) {
            return $this->sendError('يجب إدخال ملاحظات لتوضيح سبب النقص في الكمية المستلمة.');
        }

        foreach ($shipment->items as $item) {
            $qtyToSet = null;
            
            // التحقق من الكمية المستلمة المرسلة من الواجهة
            if (isset($receivedItemsMap[(int)$item->id])) {
                $qtyToSet = $receivedItemsMap[(int)$item->id];
            }
            // إذا لم توجد كمية مستلمة في الطلب، نستخدم approved_qty
            else {
                $qtyToSet = $item->approved_qty ?? $item->requested_qty ?? 0;
            }
            
            // تحديث fulfilled_qty بالكمية المستلمة الفعلية
            $item->fulfilled_qty = $qtyToSet;
            $item->save();

            // التحقق من وجود نقص
            // المقارنة مع الكمية التي أرسلها أمين المستودع (approved_qty) - بالفعل حبات
            $sentQtyPills = $item->approved_qty ?? $item->requested_qty ?? 0;
            $sentQty = $item->approved_qty ?? $item->requested_qty ?? 0;

            if ($item->fulfilled_qty < $sentQty) {
                $shortageItems[] = [
                    'name'     => $item->drug->name ?? 'غير معروف',
                    'sent'     => $sentQty,
                    'received' => $item->fulfilled_qty,
                    'unit'     => $item->drug->unit ?? 'قطعة'
                ];
            }
        }
        
        // إرسال إشعار بالنقص إذا وجد
        if (!empty($shortageItems)) {
            try {
                $entityName = $currentDepartment ? $currentDepartment->name : 'القسم';
                $this->notifications->notifyAdminShipmentDamage($shipment, $entityName);
            } catch (\Exception $e) {
                \Log::error("Failed to send shortage notification", ['error' => $e->getMessage()]);
            }
        } else {
            \Log::info("No shortage detected in shipment", ['shipment_id' => $shipment->id]);
        }
        
        // تسجيل العملية في audit_log (مع اسم القسم ليعرض حتى بعد حذف القسم)
        $user = $request->user();
        $notes = $request->input('notes', '');
        $departmentNameForLog = null;
        if ($currentDepartment) {
            $departmentNameForLog = $currentDepartment->name;
        } elseif ($shipment->department) {
            $departmentNameForLog = $shipment->department->name;
        } else {
            $createLog = AuditLog::where('table_name', 'internal_supply_request')
                ->where('record_id', $shipment->id)
                ->where('action', 'department_create_supply_request')
                ->first();
            if ($createLog && $createLog->new_values) {
                $nv = json_decode($createLog->new_values, true);
                $departmentNameForLog = $nv['department_name'] ?? null;
            }
        }
        
        AuditLog::create([
            'user_id' => $user->id,
            'hospital_id' => $user->hospital_id,
            'action' => 'department_confirm_internal_receipt',
            'table_name' => 'internal_supply_request',
            'record_id' => $shipment->id,
            'old_values' => json_encode(['status' => 'approved']),
            'new_values' => json_encode([
                'request_id' => $shipment->id,
                'status' => 'fulfilled',
                'confirmed_at' => $shipment->updated_at->format('Y-m-d H:i'),
                'notes' => $notes,
                'department_name' => $departmentNameForLog,
            ]),
            'ip_address' => $request->ip(),
        ]);
        
        // تحديث مخزون القسم
        $departmentId = $currentDepartment ? $currentDepartment->id : null;
        if ($departmentId) {
            foreach ($shipment->items as $item) {
                if ($item->fulfilled_qty > 0) {
                    // البحث عن مخزون هذا الدواء في هذا القسم - مع مراعاة الدُفعة وتاريخ الصلاحية
                    $inventory = \App\Models\Inventory::firstOrNew([
                        'drug_id'      => $item->drug_id,
                        'department_id' => $departmentId,
                        'batch_number' => $item->batch_number,
                        'expiry_date'  => $item->expiry_date,
                    ]);

                    if (!$inventory->exists) {
                        $inventory->warehouse_id = null;
                        $inventory->pharmacy_id = null;
                        $inventory->current_quantity = 0;
                        $inventory->minimum_level = 10; // قيمة افتراضية للقسم
                    }

                    $inventory->current_quantity += $item->fulfilled_qty;
                    $inventory->save();
                    
                    // التنبيه في حالة انخفاض المخزون
                    try {
                        $this->notifications->checkAndNotifyLowStock($inventory);
                    } catch (\Exception $e) {
                        \Log::error('Department stock alert notification failed', ['error' => $e->getMessage()]);
                    }
                }
            }
        }
        
        // إعادة تحميل البيانات المحدثة من قاعدة البيانات
        $shipment = InternalSupplyRequest::with('items.drug')->findOrFail($id);
        
        return $this->sendSuccess([
            'id' => $shipment->id,
            'status' => $this->translateStatus($shipment->status),
            'confirmationDetails' => [
                'confirmedAt' => $shipment->updated_at->format('Y-m-d H:i')
            ],
            'items' => $shipment->items->map(function($item) {
                $upb = $item->drug->units_per_box ?? 1;
                return [
                    'id' => $item->id,
                    'drugId' => $item->drug_id,
                    'name' => $item->drug->name ?? 'غير معروف',
                    'quantity' => $item->requested_qty,
                    'requestedQty' => $item->requested_qty,
                    'requested_qty' => $item->requested_qty,
                    'approvedQty' => $item->approved_qty,
                    'approved_qty' => $item->approved_qty,
                    'fulfilledQty' => $item->fulfilled_qty,
                    'fulfilled_qty' => $item->fulfilled_qty,
                    'receivedQuantity' => $item->fulfilled_qty,
                    'unit' => $item->drug->unit ?? 'علبة',
                    'units_per_box' => $item->drug->units_per_box ?? 1,
                    'unitsPerBox'   => $item->drug->units_per_box ?? 1,
                ];
            })
        ], 'تم تأكيد استلام الشحنة بنجاح.');
    }
}
