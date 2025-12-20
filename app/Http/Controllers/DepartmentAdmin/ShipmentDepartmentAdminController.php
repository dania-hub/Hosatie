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
            'pending' => 'قيد المراجعة',
            'approved' => 'موافق عليه',
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

        $shipmentData = [
            'id' => $shipment->id,
            'shipmentNumber' => 'REQ-' . $shipment->id,
            'status' => $this->translateStatus($shipment->status),
            'requestDate' => $shipment->created_at->toIso8601String(),
            'items' => $shipment->items->map(function($item) {
                return [
                    'id' => $item->id,
                    'drugName' => $item->drug->name ?? 'غير محدد',
                    'quantity' => $item->requested_qty,
                    'unit' => $item->drug->unit ?? 'وحدة',
                    'approved_qty' => $item->approved_qty,
                    'fulfilled_qty' => $item->fulfilled_qty,
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
        
        // تسجيل العملية في audit_log
        $user = $request->user();
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'استلام شحنة',
            'table_name' => 'internal_supply_request',
            'record_id' => $shipment->id,
            'old_values' => json_encode(['status' => 'pending']),
            'new_values' => json_encode([
                'request_id' => $shipment->id,
                'status' => 'fulfilled',
                'confirmed_at' => $shipment->updated_at->format('Y-m-d H:i'),
            ]),
            'ip_address' => $request->ip(),
        ]);
        
        // يمكن إضافة منطق تحديث المخزون هنا لاحقاً
        
        return $this->sendSuccess([
            'id' => $shipment->id,
            'status' => $this->translateStatus($shipment->status),
            'confirmationDetails' => [
                'confirmedAt' => $shipment->updated_at->format('Y-m-d H:i')
            ]
        ], 'تم تأكيد استلام الشحنة بنجاح.');
    }
}
