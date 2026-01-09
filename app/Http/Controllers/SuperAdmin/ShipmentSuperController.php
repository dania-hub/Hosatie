<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use App\Models\ExternalSupplyRequest;
use Illuminate\Http\Request;
use App\Services\StaffNotificationService; // Added

class ShipmentSuperController extends BaseApiController
{
    public function __construct(
        private StaffNotificationService $notifications
    ) {}

    /**
     * عرض قائمة الشحنات (طلبات التوريد الخارجية)
     */
    public function index(Request $request)
    {
        try {
            // جلب البيانات مع العلاقات
            $query = ExternalSupplyRequest::with(['supplier', 'items.drug']);

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
                        'approved' => 'تم الإرسال', 
                        'fulfilled' => 'تم الإستلام',
                        'rejected' => 'مرفوض',
                        default => $shipment->status,
                    },
                    'confirmedAt' => $shipment->updated_at, // افتراضاً
                    'items' => $shipment->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->drug ? $item->drug->name : 'غير معروف',
                            'quantity' => $item->quantity,
                            'receivedQuantity' => $item->received_quantity ?? 0,
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
     * تأكيد استلام الشحنة
     */
    public function confirm(Request $request, $id)
    {
        try {
            $shipment = ExternalSupplyRequest::findOrFail($id);
            
            // تحقق من أن الشحنة لم يتم استلامها مسبقاً
            if ($shipment->status === 'fulfilled' || $shipment->status === 'تم الإستلام') {
                return $this->sendError('تم استلام هذه الشحنة مسبقاً', null, 400);
            }

            // تحقق من وجود نقص
            $hasShortage = false;
            foreach ($shipment->items as $item) {
                if (($item->received_quantity ?? 0) < ($item->quantity ?? 0)) {
                    $hasShortage = true;
                    break;
                }
            }

            if ($hasShortage && empty(trim($request->input('notes', '')))) {
                return $this->sendError('يجب إدخال ملاحظات لتوضيح سبب النقص في الكمية المستلمة.', null, 400);
            }

            // تحديث الحالة
            $shipment->update([
                'status' => 'fulfilled',
                'notes' => $request->input('notes') // Save notes if provided
            ]);

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

            // هنا يمكنك إضافة منطق تحديث المخزون إذا لزم الأمر

            // إشعار المورد
            try {
                $statusArabic = 'تم الإستلام';
                $this->notifications->notifySupplierAboutSuperAdminResponse($shipment, $statusArabic, $request->input('notes'));
            } catch (\Exception $e) {
                \Log::error('Failed to notify supplier', ['error' => $e->getMessage()]);
            }
            
            // Return with translated status for consistency
            $shipment->status = 'تم الإستلام';

            return $this->sendSuccess($shipment, 'تم تأكيد استلام الشحنة بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Shipment Confirm Error');
        }
    }
}
