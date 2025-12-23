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
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
            }

            $hospitalId = $user->hospital_id;
            
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            \Illuminate\Support\Facades\Log::debug('Fetching complaints', [
                'hospital_id' => $hospitalId,
                'user_id' => $user->id
            ]);

            // جلب جميع الشكاوى مع معلومات المريض
            $allComplaints = Complaint::with('patient')->latest()->get();
            
            \Illuminate\Support\Facades\Log::debug('All complaints count', [
                'total' => $allComplaints->count(),
                'complaints' => $allComplaints->map(function($c) {
                    return [
                        'id' => $c->id,
                        'patient_id' => $c->patient_id,
                        'patient_hospital_id' => $c->patient?->hospital_id,
                        'patient_name' => $c->patient?->full_name
                    ];
                })->toArray()
            ]);

            // فلترة الشكاوى حسب hospital_id للمريض
            $complaints = Complaint::with('patient')
                ->whereHas('patient', function($query) use ($hospitalId) {
                    $query->where('hospital_id', $hospitalId);
                })
                ->latest()
                ->get();

            \Illuminate\Support\Facades\Log::debug('Filtered complaints count', [
                'count' => $complaints->count(),
                'hospital_id' => $hospitalId
            ]);

            $mappedComplaints = $complaints->map(function ($c) {
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

            \Illuminate\Support\Facades\Log::debug('Mapped complaints', [
                'count' => $mappedComplaints->count(),
                'first_item' => $mappedComplaints->first()
            ]);

            return $this->sendSuccess($mappedComplaints, 'تم جلب قائمة الشكاوى بنجاح.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Get Complaints Error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->sendError('فشل في جلب قائمة الشكاوى.', [], 500);
        }
    }

    // 2) شكوى واحدة
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
            }

            $hospitalId = $user->hospital_id;
            
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            $c = Complaint::with('patient')
                ->whereHas('patient', function($query) use ($hospitalId) {
                    $query->where('hospital_id', $hospitalId);
                })
                ->find($id);

            if (!$c) {
                return $this->sendError('الشكوى غير موجودة أو لا تنتمي إلى مستشفاك.', [], 404);
            }

            return $this->sendSuccess([
                'id'          => $c->id,
                'fileNumber'  => 'FILE-' . $c->patient_id,
                'patientName' => $c->patient?->full_name ?? 'غير معروف',
                'requestType' => 'شكوى',
                'content'     => $c->message,
                'status'      => $c->status,
                'reply'       => $c->reply_message,
                'createdAt'   => $c->created_at?->toIso8601String(),
                'repliedAt'   => $c->replied_at?->toIso8601String(),
            ], 'تم جلب تفاصيل الشكوى بنجاح.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Get Complaint Details Error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->sendError('فشل في جلب تفاصيل الشكوى.', [], 500);
        }
    }

    // 3) الرد على الشكوى
    public function respond(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
            }

            $hospitalId = $user->hospital_id;
            
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            $request->validate([
                'response' => 'required|string',
                'notes'    => 'nullable|string',
            ]);

            $complaint = Complaint::with('patient')
                ->whereHas('patient', function($query) use ($hospitalId) {
                    $query->where('hospital_id', $hospitalId);
                })
                ->find($id);

            if (!$complaint) {
                return $this->sendError('الشكوى غير موجودة أو لا تنتمي إلى مستشفاك.', [], 404);
            }

            $complaint->update([
                'reply_message' => $request->response,
                'status'        => 'تمت المراجعة', // استخدام الحالة من الـ enum في الـ migration
                'replied_by'    => $user->id,
                'replied_at'    => Carbon::now(),
            ]);

            $complaint->load('patient');
            $this->notifications->notifyComplaintReplied($complaint->patient, $complaint);

            return $this->sendSuccess([
                'id' => $complaint->id,
                'status' => $complaint->status,
                'repliedAt' => $complaint->replied_at?->toIso8601String(),
            ], 'تم إرسال الرد بنجاح.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('التحقق من البيانات فشل.', $e->errors(), 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Respond to Complaint Error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->sendError('فشل في إرسال الرد.', [], 500);
        }
    }

    // 4) رفض الشكوى
    public function reject(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
            }

            $hospitalId = $user->hospital_id;
            
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            $request->validate([
                'rejectionReason' => 'required|string',
                'notes'           => 'nullable|string',
            ]);

            $complaint = Complaint::with('patient')
                ->whereHas('patient', function($query) use ($hospitalId) {
                    $query->where('hospital_id', $hospitalId);
                })
                ->find($id);

            if (!$complaint) {
                return $this->sendError('الشكوى غير موجودة أو لا تنتمي إلى مستشفاك.', [], 404);
            }

            $complaint->update([
                'reply_message' => $request->rejectionReason,
                'status'        => 'تمت المراجعة', // استخدام الحالة من الـ enum في الـ migration
                'replied_by'    => $user->id,
                'replied_at'    => Carbon::now(),
            ]);

            return $this->sendSuccess([
                'id' => $complaint->id,
                'status' => $complaint->status,
                'repliedAt' => $complaint->replied_at?->toIso8601String(),
            ], 'تم رفض الشكوى بنجاح.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('التحقق من البيانات فشل.', $e->errors(), 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Reject Complaint Error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->sendError('فشل في رفض الشكوى.', [], 500);
        }
    }
}
