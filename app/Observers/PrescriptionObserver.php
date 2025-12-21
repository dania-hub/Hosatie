<?php

namespace App\Observers;

use App\Models\Prescription;

class PrescriptionObserver
{
    public function creating(Prescription $prescription)
    {
        // ملء hospital_id تلقائياً من patient (المريض)
        if ($prescription->patient_id && !$prescription->hospital_id) {
            $patient = \App\Models\User::find($prescription->patient_id);
            
            if ($patient && $patient->hospital_id) {
                $prescription->hospital_id = $patient->hospital_id;
            }
        }
    }
    
    public function updating(Prescription $prescription)
    {
        // نفس المنطق عند التحديث
        if ($prescription->patient_id && !$prescription->hospital_id) {
            $patient = \App\Models\User::find($prescription->patient_id);
            
            if ($patient && $patient->hospital_id) {
                $prescription->hospital_id = $patient->hospital_id;
            }
        }
    }
}
