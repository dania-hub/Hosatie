<?php

namespace App\Observers;

use App\Models\PatientTransferRequest;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class PatientTransferRequestObserver
{
    public function creating(PatientTransferRequest $request)
    {
        // التأكد من أن patient_id ليس null لطلبات النقل
        if (!$request->patient_id) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                ['patient_id' => ['patient_id مطلوب ولا يمكن أن يكون null لطلبات النقل.']]
            );
        }

        // ملء from_hospital_id تلقائياً من المريض
        if ($request->patient_id && !$request->from_hospital_id) {
            $patient = \App\Models\User::find($request->patient_id);
            
            if ($patient && $patient->hospital_id) {
                $request->from_hospital_id = $patient->hospital_id;
            }
        }
    }

    /**
     * Handle the PatientTransferRequest "created" event.
     * تسجيل إنشاء طلب النقل في audit_log
     */
    public function created(PatientTransferRequest $request)
    {
        // فقط إذا كان هناك مستخدم مسجل دخول (ليس من seeder أو console)
        if (Auth::check()) {
            try {
                $user = Auth::user();
                $request->load(['patient', 'fromHospital', 'toHospital']);
                
                $newValues = [
                    'status' => $request->status,
                    'patient_id' => $request->patient_id,
                    'patient_name' => $request->patient?->full_name ?? null,
                    'from_hospital_id' => $request->from_hospital_id,
                    'from_hospital_name' => $request->fromHospital?->name ?? null,
                    'to_hospital_id' => $request->to_hospital_id,
                    'to_hospital_name' => $request->toHospital?->name ?? null,
                    'reason' => $request->reason,
                    'requested_by' => $request->requested_by,
                ];

                AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => $user->hospital_id ?? $request->from_hospital_id,
                    'action' => 'create',
                    'table_name' => 'patient_transfer_requests',
                    'record_id' => $request->id,
                    'new_values' => json_encode($newValues),
                    'ip_address' => request()->ip(),
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to create audit log for transfer request creation', [
                    'error' => $e->getMessage(),
                    'request_id' => $request->id
                ]);
            }
        }
    }
}
