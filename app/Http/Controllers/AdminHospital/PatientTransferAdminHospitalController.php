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
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
            }

            $hospitalId = $user->hospital_id;
            
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            \Illuminate\Support\Facades\Log::debug('Fetching transfer requests', [
                'hospital_id' => $hospitalId,
                'user_id' => $user->id
            ]);

            // المستشفى يرى فقط الطلبات الموجهة إليه (to_hospital_id = hospital_id)
            $requests = PatientTransferRequest::with(['patient', 'fromHospital', 'toHospital'])
                ->where('to_hospital_id', $hospitalId)   // الطلبات الموجهة إليه فقط
                ->latest()
                ->get();

            \Illuminate\Support\Facades\Log::debug('Transfer requests found', [
                'count' => $requests->count(),
                'hospital_id' => $hospitalId
            ]);

            $mappedRequests = $requests->map(function ($r) {
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
                    'toHospital'  => [
                        'id'   => $r->toHospital?->id,
                        'name' => $r->toHospital?->name,
                    ],
                    'reason'        => $r->reason,
                    'status'        => $this->statusToArabic($r->status),
                    'createdAt'     => optional($r->created_at)->toIso8601String(),
                    'updatedAt'     => optional($r->updated_at)->toIso8601String(),
                ];
            });

            \Illuminate\Support\Facades\Log::debug('Mapped transfer requests', [
                'count' => $mappedRequests->count(),
                'first_item' => $mappedRequests->first()
            ]);

            return $this->sendSuccess($mappedRequests, 'تم جلب قائمة طلبات النقل بنجاح.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Get Transfer Requests Error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->sendError('فشل في جلب قائمة طلبات النقل.', [], 500);
        }
    }

    // تحديث حالة الطلب (قبول / رفض) مع سبب
    public function updateStatus(Request $request, $id)
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

            $data = $request->validate([
                'status'          => 'required|in:approved,rejected',
                'response'        => 'nullable|string|max:1000',   // مثلاً ملاحظات
                'notes'           => 'nullable|string|max:1000',
                'rejectionReason' => 'nullable|string|max:1000',
            ]);

            $r = PatientTransferRequest::where('to_hospital_id', $hospitalId)->find($id);

            if (!$r) {
                return $this->sendError('طلب النقل غير موجود أو لا ينتمي إلى مستشفاك.', [], 404);
            }

            $r->status = $data['status'];
            
            // استخدام الأعمدة الصحيحة من قاعدة البيانات
            $r->handeled_by = $user->id;
            $r->handeled_at = now();
            
            if ($data['status'] === 'approved') {
                $r->load('patient');
                $this->notifications->notifyTransferApproved($r->patient, $r);
            } else {
                $r->rejection_reason = $data['rejectionReason'] ?? null;
                $r->load('patient');
                $this->notifications->notifyTransferRejected($r->patient, $r);
            }

            $r->save();

            $message = $data['status'] === 'approved'
                ? 'تم قبول طلب النقل بنجاح'
                : 'تم رفض طلب النقل بنجاح';

            return $this->sendSuccess([
                'id' => $r->id,
                'status' => $this->statusToArabic($r->status),
                'updatedAt' => $r->updated_at?->toIso8601String(),
            ], $message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('التحقق من البيانات فشل.', $e->errors(), 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Update Transfer Request Status Error: ' . $e->getMessage(), [
                'exception' => $e,
                'request_id' => $id
            ]);
            return $this->sendError('فشل في تحديث حالة طلب النقل.', [], 500);
        }
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
