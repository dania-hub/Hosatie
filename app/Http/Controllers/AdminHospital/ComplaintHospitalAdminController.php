<?php

namespace App\Http\Controllers\AdminHospital;
use App\Services\PatientNotificationService;
use App\Http\Controllers\BaseApiController;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\PatientTransferRequest;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

            // فلترة الشكاوى: فقط الشكاوى التي patient.hospital_id = hospital_id للمدير
            $complaints = Complaint::with('patient')
                ->whereNotNull('patient_id') // التأكد من أن patient_id ليس null
                ->whereHas('patient', function($query) use ($hospitalId) {
                    $query->where('hospital_id', $hospitalId);
                })
                ->latest()
                ->get();

            // فلترة طلبات النقل: فقط الطلبات التي patient.hospital_id = hospital_id للمدير
            $transferRequests = PatientTransferRequest::with(['patient', 'fromHospital', 'toHospital'])
                ->whereNotNull('patient_id') // التأكد من أن patient_id ليس null
                ->whereHas('patient', function($query) use ($hospitalId) {
                    $query->where('hospital_id', $hospitalId); // فقط الطلبات للمرضى التابعين لمستشفى المدير
                })
                ->latest()
                ->get();

            \Illuminate\Support\Facades\Log::debug('Filtered data count', [
                'complaints_count' => $complaints->count(),
                'transfer_requests_count' => $transferRequests->count(),
                'hospital_id' => $hospitalId
            ]);

            // دمج الشكاوى
            $mappedComplaints = $complaints->map(function ($c) {
                return [
                    'id'          => $c->id,
                    'fileNumber'  => 'FILE-' . $c->patient_id,
                    'patientName' => $c->patient?->full_name ?? 'غير معروف',
                    'requestType' => 'شكوى',
                    'content'     => $c->message,
                    'status'      => $c->status,
                    'createdAt'   => $c->created_at?->toIso8601String(),
                    'type'        => 'complaint', // للتمييز بين الشكاوى وطلبات النقل
                ];
            });

            // دمج طلبات النقل
            $mappedTransferRequests = $transferRequests->map(function ($r) {
                return [
                    'id'          => $r->id,
                    'fileNumber'  => 'FILE-' . $r->patient_id,
                    'patientName' => $r->patient?->full_name ?? 'غير معروف',
                    'requestType' => 'النقل',
                    'content'     => $r->reason ?? 'طلب نقل مريض',
                    'status'      => $this->translateTransferStatus($r->status),
                    'createdAt'   => $r->created_at?->toIso8601String(),
                    'type'        => 'transfer', // للتمييز بين الشكاوى وطلبات النقل
                    'toHospitalId' => $r->to_hospital_id,
                    'fromHospitalId' => $r->from_hospital_id,
                    'toHospitalName' => $r->toHospital?->name ?? 'غير محدد',
                    'fromHospitalName' => $r->fromHospital?->name ?? 'غير محدد',
                ];
            });

            // دمج القائمتين وترتيبهما حسب التاريخ
            $allRequests = $mappedComplaints->concat($mappedTransferRequests)
                ->sortByDesc('createdAt')
                ->values();

            \Illuminate\Support\Facades\Log::debug('Mapped all requests', [
                'total_count' => $allRequests->count(),
                'first_item' => $allRequests->first()
            ]);

            return $this->sendSuccess($allRequests, 'تم جلب قائمة الطلبات بنجاح.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Get Complaints Error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->sendError('فشل في جلب قائمة الشكاوى.', [], 500);
        }
    }

    // 2) شكوى واحدة أو طلب نقل
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

            // محاولة جلب الشكوى أولاً
            $complaint = Complaint::with('patient')
                ->whereHas('patient', function($query) use ($hospitalId) {
                    $query->where('hospital_id', $hospitalId);
                })
                ->find($id);

            if ($complaint) {
                return $this->sendSuccess([
                    'id'          => $complaint->id,
                    'fileNumber'  => 'FILE-' . $complaint->patient_id,
                    'patientName' => $complaint->patient?->full_name ?? 'غير معروف',
                    'requestType' => 'شكوى',
                    'content'     => $complaint->message,
                    'status'      => $complaint->status,
                    'reply'       => $complaint->reply_message,
                    'createdAt'   => $complaint->created_at?->toIso8601String(),
                    'repliedAt'   => $complaint->replied_at?->toIso8601String(),
                    'type'        => 'complaint',
                ], 'تم جلب تفاصيل الشكوى بنجاح.');
            }

            // إذا لم تكن شكوى، جرب طلب النقل للمرضى التابعين لمستشفى المدير
            $transferRequest = PatientTransferRequest::with(['patient', 'fromHospital', 'toHospital'])
                ->whereNotNull('patient_id') // التأكد من أن patient_id ليس null
                ->whereHas('patient', function($query) use ($hospitalId) {
                    $query->where('hospital_id', $hospitalId);
                })
                ->find($id);

            if (!$transferRequest) {
                return $this->sendError('الطلب غير موجود أو لا ينتمي إلى مستشفاك.', [], 404);
            }

            return $this->sendSuccess([
                'id'          => $transferRequest->id,
                'fileNumber'  => 'FILE-' . $transferRequest->patient_id,
                'patientName' => $transferRequest->patient?->full_name ?? 'غير معروف',
                'requestType' => 'النقل',
                'content'     => $transferRequest->reason ?? 'طلب نقل مريض',
                'status'      => $this->translateTransferStatus($transferRequest->status),
                'createdAt'   => $transferRequest->created_at?->toIso8601String(),
                'type'        => 'transfer',
                'toHospitalId' => $transferRequest->to_hospital_id,
                'fromHospitalId' => $transferRequest->from_hospital_id,
                'toHospitalName' => $transferRequest->toHospital?->name ?? 'غير محدد',
                'fromHospitalName' => $transferRequest->fromHospital?->name ?? 'غير محدد',
                'patientId'   => $transferRequest->patient_id,
            ], 'تم جلب تفاصيل طلب النقل بنجاح.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Get Request Details Error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->sendError('فشل في جلب تفاصيل الطلب.', [], 500);
        }
    }

    // 3) الرد على الشكوى أو الموافقة على طلب النقل
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

            // التحقق من البيانات - response مطلوب للشكاوى فقط
            $request->validate([
                'response' => 'nullable|string',
                'notes'    => 'nullable|string',
            ]);

            // محاولة جلب الشكوى أولاً
            $complaint = Complaint::with('patient')
                ->whereHas('patient', function($query) use ($hospitalId) {
                    $query->where('hospital_id', $hospitalId);
                })
                ->find($id);

            if ($complaint) {
                // للشكاوى: response مطلوب
                if (!$request->response || !trim($request->response)) {
                    return $this->sendError('نص الرد مطلوب للشكاوى.', [], 422);
                }
                
                $complaint->update([
                    'reply_message' => $request->response,
                    'status'        => 'تمت المراجعة',
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
            }

            // إذا لم تكن شكوى، جرب طلب النقل (الصادر من هذا المستشفى)
            // جلب طلب النقل للمرضى التابعين لمستشفى المدير
            $transferRequest = PatientTransferRequest::with(['patient', 'fromHospital', 'toHospital'])
                ->whereNotNull('patient_id') // التأكد من أن patient_id ليس null
                ->whereHas('patient', function($query) use ($hospitalId) {
                    $query->where('hospital_id', $hospitalId);
                })
                ->find($id);

            if (!$transferRequest) {
                return $this->sendError('الطلب غير موجود أو لا ينتمي إلى مستشفاك.', [], 404);
            }

            // المستشفى المرسل لا يمكنه الموافقة على طلبه - فقط المستشفى المستقبل يمكنه ذلك
            // يمكن للمستشفى المرسل فقط إلغاء طلبه إذا كان في حالة pending
            if ($transferRequest->status !== 'pending') {
                return $this->sendError('لا يمكن تعديل هذا الطلب لأنه تم معالجته مسبقاً.', [], 400);
            }

            // إلغاء الطلب (لأن المستشفى المرسل لا يمكنه الموافقة على طلبه)
            $transferRequest->update([
                'status' => 'rejected',
                'rejected_by' => $user->id,
                'rejected_at' => Carbon::now(),
                'rejection_reason' => $request->response ?? 'تم إلغاء الطلب من المستشفى المرسل',
            ]);

            $transferRequest->load('patient');
            $this->notifications->notifyTransferRejected($transferRequest->patient, $transferRequest);

            return $this->sendSuccess([
                'id' => $transferRequest->id,
                'status' => $this->translateTransferStatus($transferRequest->status),
                'rejectedAt' => $transferRequest->rejected_at?->toIso8601String(),
            ], 'تم إلغاء طلب النقل بنجاح.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('التحقق من البيانات فشل.', $e->errors(), 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Respond to Request Error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->sendError('فشل في معالجة الطلب.', [], 500);
        }
    }

    // 4) رفض الشكوى أو طلب النقل
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

            // محاولة جلب الشكوى أولاً
            $complaint = Complaint::with('patient')
                ->whereHas('patient', function($query) use ($hospitalId) {
                    $query->where('hospital_id', $hospitalId);
                })
                ->find($id);

            if ($complaint) {
                $complaint->update([
                    'reply_message' => $request->rejectionReason,
                    'status'        => 'تمت المراجعة',
                    'replied_by'    => $user->id,
                    'replied_at'    => Carbon::now(),
                ]);

                return $this->sendSuccess([
                    'id' => $complaint->id,
                    'status' => $complaint->status,
                    'repliedAt' => $complaint->replied_at?->toIso8601String(),
                ], 'تم رفض الشكوى بنجاح.');
            }

            // إذا لم تكن شكوى، جرب طلب النقل للمرضى التابعين لمستشفى المدير
            $transferRequest = PatientTransferRequest::with(['patient', 'fromHospital', 'toHospital'])
                ->whereNotNull('patient_id') // التأكد من أن patient_id ليس null
                ->whereHas('patient', function($query) use ($hospitalId) {
                    $query->where('hospital_id', $hospitalId);
                })
                ->find($id);

            if (!$transferRequest) {
                return $this->sendError('الطلب غير موجود أو لا ينتمي إلى مستشفاك.', [], 404);
            }

            if ($transferRequest->status !== 'pending') {
                return $this->sendError('لا يمكن تعديل هذا الطلب لأنه تم معالجته مسبقاً.', [], 400);
            }

            // رفض/إلغاء طلب النقل من المستشفى المرسل
            $transferRequest->update([
                'status' => 'rejected',
                'rejected_by' => $user->id,
                'rejected_at' => Carbon::now(),
                'rejection_reason' => $request->rejectionReason,
            ]);

            $transferRequest->load('patient');
            $this->notifications->notifyTransferRejected($transferRequest->patient, $transferRequest);

            return $this->sendSuccess([
                'id' => $transferRequest->id,
                'status' => $this->translateTransferStatus($transferRequest->status),
                'rejectedAt' => $transferRequest->rejected_at?->toIso8601String(),
            ], 'تم إلغاء طلب النقل بنجاح.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('التحقق من البيانات فشل.', $e->errors(), 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Reject Request Error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->sendError('فشل في رفض الطلب.', [], 500);
        }
    }

    // دالة مساعدة لترجمة حالة طلب النقل
    private function translateTransferStatus(?string $status): string
    {
        return match ($status) {
            'approved' => 'تم الرد',
            'pending'  => 'قيد المراجعة',
            'rejected' => 'مرفوض',
            default    => $status ?? 'غير محدد',
        };
    }
}
