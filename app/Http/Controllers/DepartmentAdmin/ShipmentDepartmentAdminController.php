<?php

namespace App\Http\Controllers\DepartmentAdmin;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\InternalSupplyRequest;
use App\Models\AuditLog;
use Carbon\Carbon;

class ShipmentDepartmentAdminController extends BaseApiController
{
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
            'confirmationNotesSource' => null // مصدر الملاحظة: 'pharmacist' أو 'department'
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
        
        // جلب الطلبات المرتبطة بالمستشفى أو التي طلبها المستخدم الحالي
        $query = InternalSupplyRequest::with(['items.drug', 'requester'])
            ->where(function($q) use ($user) {
                // الطلبات التي طلبها المستخدم الحالي
                $q->where('requested_by', $user->id);
                
                // أو الطلبات المرتبطة بصيدلية في نفس المستشفى
                if ($user->hospital_id) {
                    $q->orWhereHas('pharmacy', function($pharmacyQuery) use ($user) {
                        $pharmacyQuery->where('hospital_id', $user->hospital_id);
                    });
                }
            })
            ->orderBy('created_at', 'desc');

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
    public function show($id)
    {
        $shipment = InternalSupplyRequest::with(['items.drug', 'requester', 'pharmacy'])
            ->find($id);

        if (!$shipment) {
            return $this->sendError('الشحنة غير موجودة.', [], 404);
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
        $shipment = InternalSupplyRequest::with('items.drug')->find($id);
        
        if (!$shipment) {
            return $this->sendError('الشحنة غير موجودة.', [], 404);
        }
        
        if ($shipment->status === 'fulfilled') {
            return $this->sendError('تم استلام هذه الشحنة مسبقاً.', [], 400);
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
            // ملاحظة:
            // - requested_qty: الكمية المطلوبة الأصلية (لا يتم تعديلها)
            // - approved_qty: الكمية المرسلة من storekeeper (لا يتم تعديلها)
            // - fulfilled_qty: الكمية المستلمة الفعلية من department (يتم تحديثها هنا)
            $item->fulfilled_qty = $qtyToSet;
            $item->save();
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
