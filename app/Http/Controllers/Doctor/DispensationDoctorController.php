<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Dispensing;

class DispensationDoctorController extends BaseApiController
{
    /**
     * Get Dispensing History for a Patient
     * GET /api/doctor/patients/{id}/dispensations
     */
    public function history(Request $request, $patientId)
    {
        // Get history for this patient
        // Ideally, filter by Hospital too, if doctors can only see local history
        $hospitalId = $request->user()->hospital_id;

        $history = Dispensing::with(['drug', 'pharmacist', 'pharmacy'])
            ->where('patient_id', $patientId)
            // ->where('pharmacy.hospital_id', $hospitalId) // Optional: Limit to this hospital's pharmacy?
            ->latest('created_at')
            ->get()
            ->map(function ($record) {
                return [
                    'id'         => $record->id,
                    'drug_name'  => $record->drug->name,
                    'quantity'   => $record->quantity_dispensed,
                    'date'       => $record->created_at->format('Y-m-d H:i'),
                    'pharmacist' => $record->pharmacist->full_name ?? 'غير معروف', // Unknown
                    'pharmacy'   => $record->pharmacy->name ?? 'غير معروف',       // Unknown
                    'status'     => $record->reverted ? 'تم الإرجاع' : 'تم الصرف', // Reverted : Dispensed
                ];
            });

        return $this->sendSuccess($history, 'تم جلب سجل صرف الدواء بنجاح.');
    }
}
