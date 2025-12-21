<?php

namespace App\Observers;

use App\Models\PatientTransferRequest;

class PatientTransferRequestObserver
{
    public function creating(PatientTransferRequest $request)
    {
        // ملء from_hospital_id تلقائياً من المريض
        if ($request->patient_id && !$request->from_hospital_id) {
            $patient = \App\Models\User::find($request->patient_id);
            
            if ($patient && $patient->hospital_id) {
                $request->from_hospital_id = $patient->hospital_id;
            }
        }
    }
}
