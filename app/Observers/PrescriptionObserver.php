<?php

namespace App\Observers;

use App\Models\Prescription;

class PrescriptionObserver
{
    public function creating(Prescription $prescription)
    {
        // ملء hospital_id تلقائياً من patient (المريض) فقط إذا لم يكن موجوداً
        if ($prescription->patient_id && !$prescription->hospital_id) {
            try {
                $patient = \App\Models\User::find($prescription->patient_id);
                
                if ($patient && $patient->hospital_id) {
                    $prescription->hospital_id = $patient->hospital_id;
                }
            } catch (\Exception $e) {
                // في حالة حدوث خطأ، نترك hospital_id كما هو (null أو القيمة الممررة)
                \Log::warning('PrescriptionObserver: Error getting patient hospital_id', [
                    'patient_id' => $prescription->patient_id,
                    'error' => $e->getMessage()
                ]);
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
