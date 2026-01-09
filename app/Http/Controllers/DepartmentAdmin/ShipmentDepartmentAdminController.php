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
            // البحث عن جميع الطلبات المرتبطة بنفس القسم
            // نستخدم نهجاً يعتمد على جلب جميع المستخدمين الذين كانوا مديرين لهذا القسم
            $departmentId = $currentDepartment->id;
            
            // البحث عن جميع المستخدمين الذين كانوا مديرين لهذا القسم
            $departmentManagerIds = [];
            
            // 1. إضافة المدير الحالي
            $departmentManagerIds[] = $user->id;
            
            // 2. جلب جميع المستخدمين الذين كانوا مديرين لهذا القسم من audit_log
            $departmentLogs = AuditLog::where('table_name', 'departments')
                ->where('record_id', $departmentId)
                ->where(function($q) {
                    $q->where('action', 'إضافة قسم')
                      ->orWhere('action', 'تعديل قسم')
                      ->orWhere('action', 'تعديل بيانات القسم')
                      ->orWhere('action', 'store')
                      ->orWhere('action', 'update');
                })
                ->get();
            
            foreach ($departmentLogs as $log) {
                $newValues = json_decode($log->new_values, true);
                if ($newValues && isset($newValues['head_user_id']) && $newValues['head_user_id']) {
                    $departmentManagerIds[] = $newValues['head_user_id'];
                }
                if ($newValues && isset($newValues['managerId']) && $newValues['managerId']) {
                    $departmentManagerIds[] = $newValues['managerId'];
                }
                $oldValues = json_decode($log->old_values, true);
                if ($oldValues && isset($oldValues['head_user_id']) && $oldValues['head_user_id']) {
                    $departmentManagerIds[] = $oldValues['head_user_id'];
                }
                if ($oldValues && isset($oldValues['managerId']) && $oldValues['managerId']) {
                    $departmentManagerIds[] = $oldValues['managerId'];
                }
            }
            
            // 3. جلب جميع المستخدمين الذين كانوا مديرين لهذا القسم من جدول departments (للحصول على المدير الحالي أيضاً)
            $currentManager = Department::where('id', $departmentId)
                ->where('hospital_id', $user->hospital_id)
                ->value('head_user_id');
            if ($currentManager) {
                $departmentManagerIds[] = $currentManager;
            }
            
            // إزالة التكرارات والقيم الفارغة
            $departmentManagerIds = array_filter(array_unique($departmentManagerIds));
            
            \Log::info('Department managers found', [
                'department_id' => $departmentId,
                'manager_ids' => $departmentManagerIds,
                'count' => count($departmentManagerIds),
            ]);
            
            // جلب جميع الطلبات التي أنشأها أي من هؤلاء المديرين
            if (!empty($departmentManagerIds)) {
                $query = InternalSupplyRequest::with(['items.drug', 'requester'])
                    ->whereIn('requested_by', $departmentManagerIds)
                    ->orderBy('created_at', 'desc');
                
                \Log::info('Query built for department requests', [
                    'department_id' => $departmentId,
                    'manager_ids' => $departmentManagerIds,
                    'query_ready' => true,
                ]);
            } else {
                // إذا لم نجد أي مديرين، نرجع قائمة فارغة
                \Log::warning('No managers found for department', ['department_id' => $departmentId]);
                $query = InternalSupplyRequest::with(['items.drug', 'requester'])
                    ->where('id', 0) // استعلام فارغ
                    ->orderBy('created_at', 'desc');
            }
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
        $shipment = InternalSupplyRequest::with(['items.drug', 'requester', 'pharmacy'])
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
                    'approvedQty' => $item->approved_qty ?? null,
                    'approved_qty' => $item->approved_qty ?? null,
                    'fulfilledQty' => $item->fulfilled_qty ?? null,
                    'fulfilled_qty' => $item->fulfilled_qty ?? null,
                    'sentQuantity' => $sentQuantity, // الكمية المرسلة من storekeeper
                    'receivedQuantity' => $item->fulfilled_qty ?? null, // الكمية المستلمة فقط
                    'unit' => $item->drug->unit ?? 'وحدة',
                    'genericName' => $item->drug->generic_name ?? null,
                    'strength' => $item->drug->strength ?? null,
                    'dosage' => $item->drug->strength ?? null,
                    'form' => $item->drug->form ?? null,
                    'type' => $item->drug->form ?? null
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
            $receivedQty = $receivedItem['receivedQuantity'] ?? null;
            if ($itemId !== null && $receivedQty !== null) {
                // تحويل ID إلى integer للتأكد من المطابقة الصحيحة
                $receivedItemsMap[(int)$itemId] = (float)$receivedQty;
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

        // تحديث fulfilled_qty لكل عنصر
        foreach ($shipment->items as $item) {
            // أولوية: الكمية المستلمة من الطلب > approved_qty > requested_qty
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
            // المقارنة مع الكمية التي أرسلها أمين المستودع (approved_qty)
            $sentQty = $item->approved_qty ?? $item->requested_qty ?? 0;

            \Log::info("Checking item for shortage", [
                'item_id' => $item->id,
                'received' => $qtyToSet,
                'sent' => $sentQty,
                'is_shortage' => ($qtyToSet < $sentQty)
            ]);

            if ($qtyToSet < $sentQty) {
                $shortageItems[] = [
                    'name'     => $item->drug->name ?? 'غير معروف',
                    'sent'     => $sentQty,
                    'received' => $qtyToSet,
                    'unit'     => $item->drug->unit ?? 'وحدة'
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
        
        // تسجيل العملية في audit_log
        $user = $request->user();
        $notes = $request->input('notes', '');
        
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
                'notes' => $notes, // ملاحظة department عند تأكيد الاستلام
            ]),
            'ip_address' => $request->ip(),
        ]);
        
        // يمكن إضافة منطق تحديث المخزون هنا لاحقاً
        
        // إعادة تحميل البيانات المحدثة من قاعدة البيانات
        $shipment = InternalSupplyRequest::with('items.drug')->findOrFail($id);
        
        return $this->sendSuccess([
            'id' => $shipment->id,
            'status' => $this->translateStatus($shipment->status),
            'confirmationDetails' => [
                'confirmedAt' => $shipment->updated_at->format('Y-m-d H:i')
            ],
            'items' => $shipment->items->map(function($item) {
                return [
                    'id' => $item->id,
                    'drugId' => $item->drug_id,
                    'name' => $item->drug->name ?? 'Unknown',
                    'quantity' => $item->requested_qty,
                    'requestedQty' => $item->requested_qty,
                    'requested_qty' => $item->requested_qty,
                    'approvedQty' => $item->approved_qty ?? null,
                    'approved_qty' => $item->approved_qty ?? null,
                    'fulfilledQty' => $item->fulfilled_qty ?? null,
                    'fulfilled_qty' => $item->fulfilled_qty ?? null,
                    'receivedQuantity' => $item->fulfilled_qty ?? null, // الكمية المستلمة فقط - لا نستخدم approved_qty كبديل
                    'unit' => $item->drug->unit ?? 'علبة'
                ];
            })
        ], 'تم تأكيد استلام الشحنة بنجاح.');
    }
}
