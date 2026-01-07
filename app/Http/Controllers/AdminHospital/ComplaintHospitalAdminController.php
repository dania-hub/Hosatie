<?php

namespace App\Http\Controllers\AdminHospital;
use App\Services\PatientNotificationService;
use App\Http\Controllers\BaseApiController;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\PatientTransferRequest;
use App\Models\AuditLog;
use App\Models\User;
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

            // جلب طلبات النقل:
            // 1. الصادرة من هذا المستشفى (from_hospital_id = hospital_id) - للموافقة الأولية
            // 2. الموجهة إلى هذا المستشفى في حالة preapproved (to_hospital_id = hospital_id AND status = preapproved) - للموافقة النهائية
            $transferRequests = PatientTransferRequest::with(['patient', 'fromHospital', 'toHospital'])
                ->where(function($query) use ($hospitalId) {
                    $query->where('from_hospital_id', $hospitalId) // الصادرة من المستشفى
                          ->orWhere(function($q) use ($hospitalId) {
                              $q->where('to_hospital_id', $hospitalId) // الموجهة للمستشفى
                                ->where('status', 'preapproved'); // في حالة preapproved
                          });
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
                // ترجمة حالة "تمت المراجعة" إلى "تم الرد" للشكاوى
                $status = $c->status === 'تمت المراجعة' ? 'تم الرد' : $c->status;
                
                return [
                    'id'          => $c->id,
                    'fileNumber'  => 'FILE-' . $c->patient_id,
                    'patientName' => $c->patient?->full_name ?? 'غير معروف',
                    'requestType' => 'شكوى',
                    'content'     => $c->message,
                    'status'      => $status,
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
                // ترجمة حالة "تمت المراجعة" إلى "تم الرد" للشكاوى
                $status = $complaint->status === 'تمت المراجعة' ? 'تم الرد' : $complaint->status;
                
                // في جدول الشكاوى، reply_message يحتوي على الرد أو سبب الرفض
                // عند الرد: يتم استدعاء respond() و response مطلوب، ويتم حفظه في reply_message
                // عند الرفض: يتم استدعاء reject() و rejectionReason مطلوب، ويتم حفظه في reply_message
                // كلاهما يحفظ في reply_message والحالة تصبح "تمت المراجعة"
                // للتمييز: سنستخدم reply_message لكلاهما
                // سنعرض response إذا كانت الحالة "تم الرد" وكان هناك reply_message (يعتبر رد)
                // سنعرض rejectionReason إذا كان هناك reply_message ولكن الحالة ليست "تم الرد" (يعتبر سبب رفض)
                // لكن في الكود الحالي، عند الرفض أيضاً تصبح الحالة "تمت المراجعة"
                // لذا سنعرض reply_message كـ response إذا كانت الحالة "تم الرد"
                // وسنعرض rejectionReason إذا كان هناك reply_message ولكن response غير موجود (يعني تم الرفض)
                
                // التحقق: إذا كانت الحالة "تم الرد" وكان هناك reply_message، فهو رد
                // إذا كان هناك reply_message ولكن الحالة ليست "تم الرد"، فهو سبب رفض
                // لكن في الكود الحالي، كلاهما يصبح "تم الرد"
                // لذا سنعرض reply_message كـ response دائماً إذا كانت الحالة "تم الرد"
                // وسنعرض rejectionReason فقط إذا كان هناك reply_message ولكن response غير موجود
                
                return $this->sendSuccess([
                    'id'          => $complaint->id,
                    'fileNumber'  => 'FILE-' . $complaint->patient_id,
                    'patientName' => $complaint->patient?->full_name ?? 'غير معروف',
                    'requestType' => 'شكوى',
                    'content'     => $complaint->message,
                    'status'      => $status,
                    'reply'       => $complaint->reply_message,
                    'response'    => $status === 'تم الرد' && $complaint->reply_message ? $complaint->reply_message : null, // الرد عند الحالة "تم الرد"
                    'rejectionReason' => $status === 'تم الرد' && $complaint->reply_message ? null : ($complaint->reply_message ?? null), // سبب الرفض إذا لم يكن response
                    'createdAt'   => $complaint->created_at?->toIso8601String(),
                    'repliedAt'   => $complaint->replied_at?->toIso8601String(),
                    'respondedAt' => $complaint->replied_at?->toIso8601String(),
                    'type'        => 'complaint',
                ], 'تم جلب تفاصيل الشكوى بنجاح.');
            }

            // إذا لم تكن شكوى، جرب طلب النقل
            // الصادر من هذا المستشفى أو الموجه إليه في حالة preapproved
            $transferRequest = PatientTransferRequest::with(['patient', 'fromHospital', 'toHospital'])
                ->where(function($query) use ($hospitalId) {
                    $query->where('from_hospital_id', $hospitalId) // الصادر من المستشفى
                          ->orWhere(function($q) use ($hospitalId) {
                              $q->where('to_hospital_id', $hospitalId) // الموجه للمستشفى
                                ->where('status', 'preapproved'); // في حالة preapproved
                          });
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
                'rejectionReason' => $transferRequest->rejection_reason ?? null,
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

                // تسجيل العملية في audit_log
                try {
                    \App\Models\AuditLog::create([
                        'user_id' => $user->id,
                        'hospital_id' => $hospitalId,
                        'action' => 'الرد على شكوى',
                        'table_name' => 'complaints',
                        'record_id' => $complaint->id,
                        'new_values' => json_encode([
                            'reply_message' => $request->response,
                            'status' => 'تمت المراجعة',
                            'patient_id' => $complaint->patient_id,
                        ]),
                        'ip_address' => $request->ip(),
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to create audit log for complaint reply', ['error' => $e->getMessage()]);
                }

                $complaint->load('patient');
                $this->notifications->notifyComplaintReplied($complaint->patient, $complaint);

                return $this->sendSuccess([
                    'id' => $complaint->id,
                    'status' => $complaint->status,
                    'repliedAt' => $complaint->replied_at?->toIso8601String(),
                ], 'تم إرسال الرد بنجاح.');
            }

            // إذا لم تكن شكوى، جرب طلب النقل
            // المستشفى الحالي (الذي فيه المريض الآن) يوافق أولياً
            $transferRequest = PatientTransferRequest::with(['patient', 'fromHospital', 'toHospital'])
                ->whereNotNull('patient_id')
                ->whereHas('patient', function($query) use ($hospitalId) {
                    $query->where('hospital_id', $hospitalId); // المريض حالياً في المستشفى الحالي
                })
                ->where('status', 'pending') // فقط الطلبات في حالة pending
                ->find($id);

            if ($transferRequest) {
                // الموافقة الأولية من المستشفى الحالي - الحالة تصبح preapproved
                $oldStatus = $transferRequest->status;
                $transferRequest->status = 'preapproved';
                
                // جلب ID المدير الأول من نفس المستشفى
                $firstManager = User::where('type', 'hospital_admin')
                    ->where('hospital_id', $hospitalId)
                    ->where('status', 'active')
                    ->orderBy('id', 'asc')
                    ->first();
                
                if ($firstManager) {
                    // وضع ID المدير الأول في requested_by عند الموافقة المبدئية
                    $transferRequest->requested_by = $firstManager->id;
                }
                
                $transferRequest->handeled_at = Carbon::now();
                // المريض لا يزال في المستشفى الحالي - سيتم نقله لاحقاً من قبل المستشفى المستقبل
                $transferRequest->save();
                
                // تسجيل العملية في audit_log (الموافقة الأولية)
                try {
                    $transferRequest->load(['patient', 'fromHospital', 'toHospital']);
                    
                    $newValues = [
                        'status' => $transferRequest->status,
                        'old_status' => $oldStatus,
                        'patient_id' => $transferRequest->patient_id,
                        'patient_name' => $transferRequest->patient?->full_name ?? null,
                        'from_hospital_id' => $transferRequest->from_hospital_id,
                        'from_hospital_name' => $transferRequest->fromHospital?->name ?? null,
                        'to_hospital_id' => $transferRequest->to_hospital_id,
                        'to_hospital_name' => $transferRequest->toHospital?->name ?? null,
                        'requested_by' => $transferRequest->requested_by,
                        'handeled_at' => $transferRequest->handeled_at?->toIso8601String(),
                    ];

                    AuditLog::create([
                        'user_id' => $user->id,
                        'hospital_id' => $hospitalId,
                        'action' => 'preapprove',
                        'table_name' => 'patient_transfer_requests',
                        'record_id' => $transferRequest->id,
                        'new_values' => json_encode($newValues),
                        'ip_address' => $request->ip(),
                    ]);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to create audit log for transfer request preapproval', [
                        'error' => $e->getMessage(),
                        'request_id' => $transferRequest->id
                    ]);
                }
                
                $transferRequest->load('patient');

                return $this->sendSuccess([
                    'id' => $transferRequest->id,
                    'status' => $this->translateTransferStatus($transferRequest->status),
                    'handeledAt' => $transferRequest->handeled_at?->toIso8601String(),
                ], 'تم قبول طلب النقل. سيتم نقل المريض بعد موافقة المستشفى المستقبل.');
            }

            // إذا لم نجد طلب نقل للمستشفى الحالي، جرب البحث في المستشفى المستقبل
            // المستشفى المستقبل يوافق نهائياً (بعد الموافقة الأولية preapproved)
            $transferRequest = PatientTransferRequest::with(['patient', 'fromHospital', 'toHospital'])
                ->where('to_hospital_id', $hospitalId) // المستشفى المستقبل
                ->where('status', 'preapproved') // فقط الطلبات التي تمت الموافقة الأولية عليها (preapproved)
                ->find($id);
            
            if ($transferRequest) {
                // نقل المريض إلى المستشفى الجديد وتغيير الحالة إلى approved
                $transferRequest->status = 'approved';
                
                // جلب ID المدير الثاني من المستشفى المستقبل
                $secondManager = User::where('type', 'hospital_admin')
                    ->where('hospital_id', $hospitalId) // المستشفى المستقبل
                    ->where('status', 'active')
                    ->orderBy('id', 'asc')
                    ->skip(1) // تخطي المدير الأول
                    ->first();
                
                // إذا لم يوجد مدير ثاني، نستخدم أول مدير متاح
                if (!$secondManager) {
                    $secondManager = User::where('type', 'hospital_admin')
                        ->where('hospital_id', $hospitalId)
                        ->where('status', 'active')
                        ->orderBy('id', 'asc')
                        ->first();
                }
                
                if ($secondManager) {
                    // وضع ID المدير الثاني في handeled_by عند الموافقة النهائية
                    $transferRequest->handeled_by = $secondManager->id;
                }
                
                $transferRequest->handeled_at = Carbon::now();
                $transferRequest->load('patient');
                if ($transferRequest->patient && $transferRequest->to_hospital_id) {
                    $transferRequest->patient->hospital_id = $transferRequest->to_hospital_id;
                    $transferRequest->patient->save();
                }
                $transferRequest->save();
                
                // تسجيل العملية في audit_log
                try {
                    $transferRequest->load(['patient', 'fromHospital', 'toHospital']);
                    
                    $newValues = [
                        'status' => $transferRequest->status,
                        'patient_id' => $transferRequest->patient_id,
                        'patient_name' => $transferRequest->patient?->full_name ?? null,
                        'from_hospital_id' => $transferRequest->from_hospital_id,
                        'from_hospital_name' => $transferRequest->fromHospital?->name ?? null,
                        'to_hospital_id' => $transferRequest->to_hospital_id,
                        'to_hospital_name' => $transferRequest->toHospital?->name ?? null,
                        'handeled_by' => $transferRequest->handeled_by,
                        'handeled_at' => $transferRequest->handeled_at?->toIso8601String(),
                    ];

                    AuditLog::create([
                        'user_id' => $user->id,
                        'hospital_id' => $hospitalId,
                        'action' => 'approve',
                        'table_name' => 'patient_transfer_requests',
                        'record_id' => $transferRequest->id,
                        'new_values' => json_encode($newValues),
                        'ip_address' => $request->ip(),
                    ]);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to create audit log for transfer request approval', [
                        'error' => $e->getMessage(),
                        'request_id' => $transferRequest->id
                    ]);
                }
                
                $this->notifications->notifyTransferApproved($transferRequest->patient, $transferRequest);
                
                return $this->sendSuccess([
                    'id' => $transferRequest->id,
                    'status' => $this->translateTransferStatus($transferRequest->status),
                    'handeledAt' => $transferRequest->handeled_at?->toIso8601String(),
                ], 'تم قبول طلب النقل ونقل المريض بنجاح.');
            }

            return $this->sendError('الطلب غير موجود أو لا ينتمي إلى مستشفاك.', [], 404);
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

            // إذا لم تكن شكوى، جرب طلب النقل (الصادر من هذا المستشفى)
            $transferRequest = PatientTransferRequest::with(['patient', 'fromHospital', 'toHospital'])
                ->where('from_hospital_id', $hospitalId)
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
                'requested_by' => $user->id,
                'rejected_by' => $user->id,
                'rejected_at' => Carbon::now(),
                'rejection_reason' => $request->rejectionReason,
            ]);

            // تسجيل العملية في audit_log
            try {
                $transferRequest->load(['patient', 'fromHospital', 'toHospital']);
                
                $newValues = [
                    'status' => $transferRequest->status,
                    'patient_id' => $transferRequest->patient_id,
                    'patient_name' => $transferRequest->patient?->full_name ?? null,
                    'from_hospital_id' => $transferRequest->from_hospital_id,
                    'from_hospital_name' => $transferRequest->fromHospital?->name ?? null,
                    'to_hospital_id' => $transferRequest->to_hospital_id,
                    'to_hospital_name' => $transferRequest->toHospital?->name ?? null,
                    'rejection_reason' => $transferRequest->rejection_reason,
                ];

                AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => $hospitalId,
                    'action' => 'reject',
                    'table_name' => 'patient_transfer_requests',
                    'record_id' => $transferRequest->id,
                    'new_values' => json_encode($newValues),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to create audit log for transfer request rejection', [
                    'error' => $e->getMessage(),
                    'request_id' => $transferRequest->id
                ]);
            }

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
            'approved' => 'تم القبول',
            'preapproved' => 'تمت الموافقة الأولية',
            'pending'  => 'قيد المراجعة',
            'rejected' => 'مرفوض',
            default    => $status ?? 'غير محدد',
        };
    }
}
