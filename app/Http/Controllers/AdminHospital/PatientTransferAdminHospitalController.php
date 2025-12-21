<?php

namespace App\Http\Controllers\AdminHospital;
use App\Services\PatientNotificationService;
use App\Http\Controllers\BaseApiController;
use App\Models\PatientTransferRequest;
use Illuminate\Http\Request;

class PatientTransferAdminHospitalController extends BaseApiController
{ public function __construct(private PatientNotificationService $notifications) {}
    // قائمة طلبات النقل
    public function index(Request $request)
    {
        $hospitalId = $request->user()->hospital_id;

        $requests = PatientTransferRequest::with(['patient', 'fromHospital'])
            ->where('to_hospital_id', $hospitalId)   // المستشفى المستقبِل يرى الطلبات الموجهة له
            ->latest()
            ->get()
            ->map(function ($r) {
                return [
                    'id'            => $r->id,
                    'requestNumber' => 'TR-' . $r->id,
                    'patient'       => [
                        'id'   => $r->patient?->id,
                        'name' => $r->patient?->full_name,
                    ],
                    'fromHospital'  => [
                        'id'   => $r->fromHospital?->id,
                        'name' => $r->fromHospital?->name,
                    ],
                    'reason'        => $r->reason,
                    'status'        => $this->statusToArabic($r->status),
                    'createdAt'     => optional($r->created_at)->toIso8601String(),
                    'updatedAt'     => optional($r->updated_at)->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => $requests,
        ]);
    }

    // تحديث حالة الطلب (قبول / رفض) مع سبب
    public function updateStatus(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;

        $data = $request->validate([
            'status'          => 'required|in:approved,rejected',
            'response'        => 'nullable|string|max:1000',   // مثلاً ملاحظات
            'notes'           => 'nullable|string|max:1000',
            'rejectionReason' => 'nullable|string|max:1000',
        ]);

        $r = PatientTransferRequest::where('to_hospital_id', $hospitalId)->findOrFail($id);

        $r->status          = $data['status'];
        // لو عندك أعمدة approved_by / rejected_by / rejection_reason يمكنك تفعيلها هنا:
        if ($data['status'] === 'approved') {
            $r->approved_by = $request->user()->id;
            $r->approved_at = now();
              $this->notifications->notifyTransferApproved($r->patient, $r);
     } else {
            $r->rejected_by     = $request->user()->id;
           $r->rejected_at     = now();
            $r->rejection_reason= $data['rejectionReason'] ?? null;
              $this->notifications->notifyTransferRejected($r->patient, $r);
        }

        $r->save();

        return response()->json([
            'success' => true,
            'message' => $data['status'] === 'approved'
                ? 'تم قبول طلب النقل بنجاح'
                : 'تم رفض طلب النقل بنجاح',
            'status'  => $this->statusToArabic($r->status),
        ]);
    }

    private function statusToArabic(?string $status): string
    {
        return match ($status) {
            'approved' => 'تم الرد',
            'pending'  => 'قيد المراجعة',
            'rejected' => 'مرفوض',
            default    => $status ?? 'غير محدد',
        };
    }
}
