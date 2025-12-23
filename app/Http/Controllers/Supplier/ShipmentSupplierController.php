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

            // جلب جميع الشحنات (approved, fulfilled, rejected)
            // هذه الطلبات جاءت من StoreKeeper وتم اعتمادها من HospitalAdmin
            $shipments = ExternalSupplyRequest::with([
                'hospital:id,name,city',
                'requester:id,full_name',
                'approver:id,full_name',
                'items.drug:id,name'
            ])
                ->where('supplier_id', $user->supplier_id)
                ->whereIn('status', ['approved', 'fulfilled', 'rejected']) // جميع الحالات المعتمدة والمكتملة والمرفوضة
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($shipment) {
                    return [
                        'id' => $shipment->id,
                        'hospitalName' => $shipment->hospital->name ?? 'غير محدد',
                        'hospitalCode' => $shipment->hospital->code ?? '',
                        'hospitalCity' => $shipment->hospital->city ?? '',
                        'requestedBy' => $shipment->requester->full_name ?? 'غير محدد',
                        'approvedBy' => $shipment->approver?->full_name ?? 'مدير المستشفى',
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
                'items' => $shipment->items->map(function ($item) {
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
                        'unit' => $item->drug->unit ?? 'وحدة',
                        'dosage' => $item->drug->strength ?? null,
                        'strength' => $item->drug->strength ?? null,
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
     * عند القبول، يحدد Supplier الكمية الفعلية المرسلة (fulfilled_qty)
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
            
            // تحديث الكميات المرسلة (fulfilled_qty) لكل عنصر
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $item = $shipment->items->firstWhere('id', $itemData['id'] ?? null);
                    if ($item) {
                        // استخدام fulfilled_qty المرسلة، أو sentQuantity، أو approved_qty كقيمة افتراضية
                        $fulfilledQty = $itemData['fulfilled_qty'] ?? 
                                       $itemData['sentQuantity'] ?? 
                                       $item->approved_qty ?? 
                                       $item->requested_qty;
                        
                        $item->fulfilled_qty = max(0, (float)$fulfilledQty);
                        $item->save();
                    }
                }
            } else {
                // إذا لم يتم إرسال items، نستخدم approved_qty كـ fulfilled_qty
                foreach ($shipment->items as $item) {
                    $item->fulfilled_qty = $item->approved_qty ?? $item->requested_qty;
                    $item->save();
                }
            }

            // تحديث الحالة إلى 'fulfilled' (تم الإرسال)
            // الآن يمكن لـ StoreKeeper تأكيد الاستلام
            $shipment->status = 'fulfilled';
            $shipment->save();

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

            // تحديث الحالة إلى 'rejected'
            // ملاحظة: الجدول لا يحتوي على rejection_reason أو notes أو rejected_by
            // فقط نغير الحالة إلى 'rejected'
            $shipment->status = 'rejected';
            $shipment->save();
            
            // يمكن حفظ سبب الرفض في جدول منفصل أو في logs إذا لزم الأمر
            \Log::info('External Supply Request Rejected', [
                'request_id' => $shipment->id,
                'rejected_by' => $user->id,
                'reason' => $request->input('reason') ?? $request->input('rejectionReason') ?? ''
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
