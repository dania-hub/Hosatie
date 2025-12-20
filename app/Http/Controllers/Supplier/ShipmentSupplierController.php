<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\BaseApiController;
use App\Models\ExternalSupplyRequest;
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

            // جلب الشحنات الخاصة بهذا المورد
            $shipments = ExternalSupplyRequest::with([
                'hospital:id,name,city',
                'requester:id,full_name',
                'approver:id,full_name',
                'items.drug:id,name'
            ])
                ->where('supplier_id', $user->supplier_id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($shipment) {
                    return [
                        'id' => $shipment->id,
                        'hospitalName' => $shipment->hospital->name ?? 'غير محدد',
                        'hospitalCode' => $shipment->hospital->code ?? '',
                        'hospitalCity' => $shipment->hospital->city ?? '',
                        'requestedBy' => $shipment->requester->full_name ?? 'غير محدد',
                        'status' => $this->translateStatus($shipment->status),
                        'statusOriginal' => $shipment->status,
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
                'items.drug:id,name,category',
            ])
                ->where('supplier_id', $user->supplier_id)
                ->findOrFail($id);

            $data = [
                'id' => $shipment->id,
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
                'items' => $shipment->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'drugId' => $item->drug_id,
                        'drugName' => $item->drug->name ?? 'غير محدد',
                        'drugCode' => $item->drug->code ?? '',
                        'category' => $item->drug
                            ? (is_object($item->drug->category)
                                ? ($item->drug->category->name ?? $item->drug->category)
                                : ($item->drug->category ?? 'غير محدد'))
                            : 'غير محدد',
                        'requestedQuantity' => $item->requested_quantity,
                        'approvedQuantity' => $item->approved_quantity,
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
     * تأكيد الشحنة
     * POST /api/supplier/shipments/{id}/confirm
     */
    public function confirm(ConfirmShipmentRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $shipment = ExternalSupplyRequest::where('supplier_id', $user->supplier_id)
                ->findOrFail($id);

            if ($shipment->status !== 'pending') {
                return $this->sendError('لا يمكن تأكيد هذه الشحنة', null, 400);
            }

            // تحديث الحالة
            $shipment->update([
                'status' => 'approved',
                'approved_by' => $user->id,
            ]);

            DB::commit();

            return $this->sendSuccess([
                'id' => $shipment->id,
                'status' => $this->translateStatus($shipment->status),
            ], 'تم تأكيد الشحنة بنجاح');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleException($e, 'Supplier Confirm Shipment Error');
        }
    }

    /**
     * رفض الشحنة
     * POST /api/supplier/shipments/{id}/reject
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

            if ($shipment->status !== 'pending') {
                return $this->sendError('لا يمكن رفض هذه الشحنة', null, 400);
            }

            // تحديث الحالة
            $shipment->update([
                'status' => 'rejected',
                'rejection_reason' => $request->input('reason'),
            ]);

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
            'approved' => 'تم الموافقة',
            'fulfilled' => 'تم التنفيذ',
            'rejected' => 'مرفوض',
        ];

        return $statuses[$status] ?? $status;
    }
}
