<?php

namespace App\Http\Controllers\AdminHospital;
use App\Services\PatientNotificationService;
use App\Http\Controllers\BaseApiController;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ComplaintHospitalAdminController extends BaseApiController
{ public function __construct(
        private PatientNotificationService $notifications
    ) {}
    // 1) قائمة الطلبات/الشكاوى
    public function index(Request $request)
    {
        $complaints = Complaint::with('patient')
            ->latest()
            ->get()
            ->map(function ($c) {
                return [
                    'id'          => $c->id,
                    'fileNumber'  => 'FILE-' . $c->patient_id,
                    'patientName' => $c->patient?->full_name ?? 'غير معروف',
                    'requestType' => 'شكوى',
                    'content'     => $c->message,
                    'status'      => $c->status,
                    'createdAt'   => $c->created_at?->toIso8601String(),
                ];
            });

        return response()->json($complaints);
    }

    // 2) شكوى واحدة
    public function show($id)
    {
        $c = Complaint::with('patient')->findOrFail($id);

        return response()->json([
            'id'          => $c->id,
            'fileNumber'  => 'FILE-' . $c->patient_id,
            'patientName' => $c->patient?->full_name ?? 'غير معروف',
            'requestType' => 'شكوى',
            'content'     => $c->message,
            'status'      => $c->status,
            'reply'       => $c->reply_message,
            'createdAt'   => $c->created_at?->toIso8601String(),
            'repliedAt'   => $c->replied_at?->toIso8601String(),
        ]);
    }

    // 3) الرد على الشكوى
    public function respond(Request $request, $id)
    {
        $request->validate([
            'response' => 'required|string',
            'notes'    => 'nullable|string',
        ]);

        $complaint = Complaint::findOrFail($id);

        $complaint->update([
            'reply_message' => $request->response,
            'status'        => 'تم الرد',
            'replied_by'    => $request->user()->id ?? null,
            'replied_at'    => Carbon::now(),
        ]);
  $this->notifications->notifyComplaintReplied($complaint->patient, $complaint);

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الرد بنجاح',
        ]);
    }

    // 4) رفض الشكوى
    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejectionReason' => 'required|string',
            'notes'           => 'nullable|string',
        ]);

        $complaint = Complaint::findOrFail($id);

        $complaint->update([
            'reply_message' => $request->rejectionReason,
            'status'        => 'مرفوض',
            'replied_by'    => $request->user()->id ?? null,
            'replied_at'    => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم رفض الطلب بنجاح',
        ]);
    }
}
