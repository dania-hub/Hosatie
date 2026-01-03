<?php

namespace App\Observers;

use App\Models\PatientTransferRequest;

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
}
