<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use App\Models\ExternalSupplyRequest;
use Illuminate\Http\Request;

class ShipmentSuperController extends BaseApiController
{
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

            // تحديث الحالة
            $shipment->update(['status' => 'fulfilled']);

            // هنا يمكنك إضافة منطق تحديث المخزون إذا لزم الأمر
            
            // Return with translated status for consistency
            $shipment->status = 'تم الإستلام';

            return $this->sendSuccess($shipment, 'تم تأكيد استلام الشحنة بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Shipment Confirm Error');
        }
    }
}
