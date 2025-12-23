<?php

namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use App\Models\ExternalSupplyRequest;
use App\Models\ExternalSupplyRequestItem;
use App\Models\Inventory;
use App\Models\Pharmacy;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExternalShipmentAdminHospitalController extends BaseApiController
{
    // 1) قائمة الشحنات - فقط الطلبات من StoreKeeper التي تحتاج موافقة
    public function index(Request $request)
    {
        try {
            $hospitalId = $request->user()->hospital_id;

            if (!$hospitalId) {
                return response()->json(['message' => 'المستخدم غير مرتبط بمستشفى'], 400);
            }

            // جلب جميع الطلبات من StoreKeeper (جميع الحالات)
            $requests = ExternalSupplyRequest::with(['supplier', 'requester'])
                ->where('hospital_id', $hospitalId)
                ->whereHas('requester', function($q) {
                    // فقط الطلبات من StoreKeeper (requested_by هو warehouse_manager)
                    $q->where('type', 'warehouse_manager');
                })
                ->latest()
                ->get()
                ->map(function ($r) {
                    return [
                        'id'                  => $r->id,
                        'shipmentNumber'      => 'EXT-' . $r->id,
                        'requestDate'         => optional($r->created_at)->toIso8601String(),
                        'createdAt'           => optional($r->created_at)->toIso8601String(),
                        'status'              => $this->mapStatusToArabic($r->status),
                        'requestingDepartment'=> $r->requester?->full_name ?? 'مسؤول المخزن',
                        'department'          => $r->requester?->full_name ?? 'مسؤول المخزن',
                    ];
                });

            return response()->json($requests);
        } catch (\Exception $e) {
            \Log::error('Error in ExternalShipmentAdminHospitalController@index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'حدث خطأ في جلب البيانات',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    // 2) تفاصيل شحنة + البنود
    public function show(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;

        $r = ExternalSupplyRequest::with(['supplier', 'items.drug'])
            ->where('hospital_id', $hospitalId)
            ->findOrFail($id);

        // جلب جميع الصيدليات والمستودعات في المستشفى
        $pharmacyIds = Pharmacy::where('hospital_id', $hospitalId)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();

        $warehouseIds = Warehouse::where('hospital_id', $hospitalId)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();

        $items = $r->items->map(function ($item) use ($pharmacyIds, $warehouseIds) {
            // حساب الكمية المتوفرة من جميع الصيدليات والمستودعات
            $availableQuantity = 0;
            
            if (!empty($pharmacyIds) || !empty($warehouseIds)) {
                $inventoryQuery = Inventory::where('drug_id', $item->drug_id);
                
                if (!empty($pharmacyIds) && !empty($warehouseIds)) {
                    $inventoryQuery->where(function($query) use ($pharmacyIds, $warehouseIds) {
                        $query->whereIn('pharmacy_id', $pharmacyIds)
                              ->orWhereIn('warehouse_id', $warehouseIds);
                    });
                } elseif (!empty($pharmacyIds)) {
                    $inventoryQuery->whereIn('pharmacy_id', $pharmacyIds);
                } elseif (!empty($warehouseIds)) {
                    $inventoryQuery->whereIn('warehouse_id', $warehouseIds);
                }
                
                $inventories = $inventoryQuery->get();
                $availableQuantity = $inventories->sum('current_quantity');
            }

            return [
                'id'                => $item->id,
                'drugId'            => $item->drug_id,
                'name'              => $item->drug?->name ?? 'دواء غير معروف',
                'drugName'          => $item->drug?->name ?? 'دواء غير معروف',
                'requestedQuantity' => $item->requested_qty,
                'requested'         => $item->requested_qty,
                'requested_qty'     => $item->requested_qty,
                'approved'          => $item->approved_qty,
                'approved_qty'      => $item->approved_qty,
                'fulfilled'         => $item->fulfilled_qty,
                'fulfilled_qty'     => $item->fulfilled_qty,
                'sent'              => $item->fulfilled_qty,
                'availableQuantity' => $availableQuantity,
                'available_quantity'=> $availableQuantity,
                'unit'              => $item->drug?->unit ?? 'وحدة',
                'dosage'            => $item->drug?->strength ?? null,
                'strength'          => $item->drug?->strength ?? null,
            ];
        });

        return response()->json([
            'id'                  => $r->id,
            'shipmentNumber'      => 'EXT-' . $r->id,
            'requestingDepartment'=> $r->supplier?->name ?? 'المستشفى',
            'department'          => $r->supplier?->name ?? 'المستشفى',
            'status'              => $this->mapStatusToArabic($r->status),
            'requestDate'         => optional($r->created_at)->toIso8601String(),
            'createdAt'           => optional($r->created_at)->toIso8601String(),
            'items'               => $items,
        ]);
    }

    // 3) تأكيد الشحنة (واجهة المدير: /shipments/{id}/confirm)
    // القبول المبدئي: يغير الحالة إلى "approved" ويضع approved_qty = requested_qty
    // بعد القبول، سيظهر الطلب للـ Supplier للموافقة النهائية
    public function confirm(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;
        $adminUser = $request->user();

        $data = $request->validate([
            'items'        => 'required|array',
            'items.*.id'   => 'required|integer|exists:external_supply_request_item,id',
            'items.*.sent' => 'nullable|numeric|min:0', // جعل sent اختياري للقبول المبدئي
            'notes'        => 'nullable|string|max:1000',
        ]);

        $r = ExternalSupplyRequest::with('items')->where('hospital_id', $hospitalId)->findOrFail($id);

        // التحقق من أن الطلب من StoreKeeper
        $requester = User::find($r->requested_by);
        if (!$requester || $requester->type !== 'warehouse_manager') {
            return response()->json(['message' => 'هذا الطلب ليس من مسؤول المخزن'], 400);
        }

        if (in_array($r->status, ['fulfilled', 'rejected'])) {
            return response()->json(['message' => 'لا يمكن تعديل طلب تم إغلاقه مسبقاً'], 409);
        }

        DB::transaction(function () use ($r, $data, $adminUser) {
            foreach ($data['items'] as $itemData) {
                $item = $r->items->firstWhere('id', $itemData['id']);
                
                if ($item) {
                    // عند القبول المبدئي: نضع approved_qty = requested_qty
                    // إذا تم إرسال sent، نستخدمه، وإلا نستخدم requested_qty
                    $approvedQty = $itemData['sent'] ?? $item->requested_qty;
                    
                    ExternalSupplyRequestItem::where('id', $itemData['id'])
                        ->where('request_id', $r->id)
                        ->update([
                            'approved_qty' => $approvedQty,
                            // لا نضع fulfilled_qty هنا، سيتم تحديده لاحقاً من المورد
                        ]);
                }
            }

            // تغيير الحالة إلى "approved" (موافقة مبدئية)
            // الآن سيظهر الطلب للـ Supplier
            $r->status = 'approved';
            $r->approved_by = $adminUser->id; // تسجيل من وافق على الطلب
            $r->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'تم قبول الطلب بنجاح. سيتم إرساله للمورد للموافقة النهائية.',
        ]);
    }

    // 4) رفض الشحنة (واجهة المدير: /shipments/{id}/reject)
    // عند الرفض، لا يذهب الطلب للـ Supplier
    public function reject(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;
        $adminUser = $request->user();

        $data = $request->validate([
            'rejectionReason' => 'required|string|max:1000',
        ]);

        $r = ExternalSupplyRequest::where('hospital_id', $hospitalId)->findOrFail($id);

        // التحقق من أن الطلب من StoreKeeper
        $requester = User::find($r->requested_by);
        if (!$requester || $requester->type !== 'warehouse_manager') {
            return response()->json(['message' => 'هذا الطلب ليس من مسؤول المخزن'], 400);
        }

        if (in_array($r->status, ['fulfilled', 'rejected'])) {
            return response()->json(['message' => 'لا يمكن تعديل طلب تم إغلاقه مسبقاً'], 409);
        }

        // عند الرفض، لا يذهب الطلب للـ Supplier
        // ملاحظة: الجدول لا يحتوي على rejection_reason أو notes أو rejected_by
        // فقط نغير الحالة إلى 'rejected'
        $r->status = 'rejected';
        $r->save();
        
        // يمكن حفظ سبب الرفض في جدول منفصل أو في logs إذا لزم الأمر
        \Log::info('External Supply Request Rejected by Hospital Admin', [
            'request_id' => $r->id,
            'rejected_by' => $adminUser->id,
            'reason' => $data['rejectionReason'] ?? ''
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم رفض الطلب بنجاح. لن يتم إرساله للمورد.',
        ]);
    }

    // 5) تغيير حالة عامة (واجهة القسم: PUT /shipments/{id}/status)
    public function updateStatus(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;

        $data = $request->validate([
            'status' => 'required|in:pending,approved,fulfilled,rejected',
        ]);

        $r = ExternalSupplyRequest::where('hospital_id', $hospitalId)->findOrFail($id);

        $r->status = $data['status'];
        $r->save();

        return response()->json([
            'success' => true,
            'status'  => $this->mapStatusToArabic($r->status),
        ]);
    }

    // 6) تأكيد استلام من جهة القسم (POST /shipments/{id}/confirm-delivery)
    public function confirmDelivery(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;

        $data = $request->validate([
            'confirmationNotes' => 'nullable|string|max:1000',
        ]);

        $r = ExternalSupplyRequest::where('hospital_id', $hospitalId)->findOrFail($id);

        if (in_array($r->status, ['fulfilled', 'rejected'])) {
            return response()->json(['message' => 'لا يمكن تعديل طلب تم إغلاقه مسبقاً'], 409);
        }

        $r->status = 'fulfilled';
        $r->notes  = $data['confirmationNotes'] ?? $r->notes;
        $r->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تأكيد الاستلام بنجاح',
            'status'  => $this->mapStatusToArabic($r->status),
        ]);
    }

    // تحويل status من DB إلى عربي
    private function mapStatusToArabic(string $status): string
    {
        return match ($status) {
            'pending'   => 'قيد الانتظار',
            'approved'  => 'معتمدة مبدئياً', // معتمدة من HospitalAdmin، في انتظار Supplier
            'fulfilled' => 'تم الإرسال', // أرسلها Supplier، في انتظار StoreKeeper
            'rejected'  => 'مرفوضة',
            default     => $status,
        };
    }
}
