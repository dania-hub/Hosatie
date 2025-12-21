<?php

namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use App\Models\ExternalSupplyRequest;
use App\Models\ExternalSupplyRequestItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExternalShipmentAdminHospitalController extends BaseApiController
{
    // 1) قائمة الشحنات (تُستخدم في الواجهتين)
    public function index(Request $request)
    {
        $hospitalId = $request->user()->hospital_id;

        $requests = ExternalSupplyRequest::with('supplier')
            ->where('hospital_id', $hospitalId)
            ->latest()
            ->get()
            ->map(function ($r) {
                return [
                    'id'                  => $r->id,
                    'shipmentNumber'      => 'EXT-' . $r->id,
                    'requestDate'         => optional($r->created_at)->toIso8601String(),
                    'createdAt'           => optional($r->created_at)->toIso8601String(),
                    'status'              => $this->mapStatusToArabic($r->status),
                    'requestingDepartment'=> $r->supplier?->name ?? 'المستشفى',
                    'department'          => $r->supplier?->name ?? 'المستشفى',
                ];
            });

        return response()->json($requests);
    }

    // 2) تفاصيل شحنة + البنود
    public function show(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;

        $r = ExternalSupplyRequest::with(['supplier', 'items.drug'])
            ->where('hospital_id', $hospitalId)
            ->findOrFail($id);

        $items = $r->items->map(function ($item) {
            return [
                'id'        => $item->id,
                'drugId'    => $item->drug_id,
                'drugName'  => $item->drug?->name ?? 'دواء غير معروف',
                'requested' => $item->requested_qty,
                'approved'  => $item->approved_qty,
                'fulfilled' => $item->fulfilled_qty,
                'sent'      => $item->fulfilled_qty,
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
    public function confirm(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;

        $data = $request->validate([
            'items'        => 'required|array',
            'items.*.id'   => 'required|integer|exists:external_supply_request_item,id',
            'items.*.sent' => 'required|numeric|min:0',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $r = ExternalSupplyRequest::where('hospital_id', $hospitalId)->findOrFail($id);

        if (in_array($r->status, ['fulfilled', 'rejected'])) {
            return response()->json(['message' => 'لا يمكن تعديل طلب تم إغلاقه مسبقاً'], 409);
        }

        DB::transaction(function () use ($r, $data) {
            foreach ($data['items'] as $itemData) {
                ExternalSupplyRequestItem::where('id', $itemData['id'])
                    ->where('request_id', $r->id)
                    ->update([
                        'fulfilled_qty' => $itemData['sent'],
                    ]);
            }

            $r->status = 'fulfilled';
            $r->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'تم تأكيد استلام الشحنة بنجاح',
        ]);
    }

    // 4) رفض الشحنة (واجهة المدير: /shipments/{id}/reject)
    public function reject(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;

        $data = $request->validate([
            'rejectionReason' => 'required|string|max:1000',
        ]);

        $r = ExternalSupplyRequest::where('hospital_id', $hospitalId)->findOrFail($id);

        if (in_array($r->status, ['fulfilled', 'rejected'])) {
            return response()->json(['message' => 'لا يمكن تعديل طلب تم إغلاقه مسبقاً'], 409);
        }

        $r->status           = 'rejected';
        $r->rejection_reason = $data['rejectionReason'];
        $r->save();

        return response()->json([
            'success' => true,
            'message' => 'تم رفض الشحنة بنجاح',
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
            'approved'  => 'قيد التجهيز',
            'fulfilled' => 'تم الإستلام',
            'rejected'  => 'مرفوضة',
            default     => $status,
        };
    }
}
