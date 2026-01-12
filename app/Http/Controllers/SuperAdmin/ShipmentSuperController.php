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
                            'quantity' => $item->requested_qty,
                            'requested_qty' => $item->requested_qty, // Ensure frontend receives this
                            'receivedQuantity' => $item->fulfilled_qty ?? 0,
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
     */
    public function show($id)
    {
        try {
            $shipment = ExternalSupplyRequest::with(['supplier', 'items.drug'])->findOrFail($id);

            return $this->sendSuccess([
                'id' => $shipment->id,
                'shipmentNumber' => 'EXT-' . $shipment->id,
                'department' => $shipment->supplier ? $shipment->supplier->name : 'غير محدد',
                'date' => $shipment->created_at->format('Y-m-d'),
                'status' => $shipment->status,
                'notes' => $shipment->notes,
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
                    ];
                }),
            ], 'تم جلب تفاصيل الشحنة بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Shipment Show Error');
        }
    }

    /**
     * تأكيد استلام الشحنة وتحديث المخزون
     */
    public function confirm(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $shipment = ExternalSupplyRequest::with(['items', 'hospital.warehouse'])->findOrFail($id);
            
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
            $shipment->update([
                'status' => 'fulfilled',
                'notes' => $request->input('notes')
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
