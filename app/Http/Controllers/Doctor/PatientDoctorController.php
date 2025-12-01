<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Prescription;

class PatientDoctorController extends BaseApiController
{
    public function index(Request $request)
    {
        $hospitalId = $request->user()->hospital_id;

        // 1. Query Patients (with Search support)
        $query = User::where('type', 'patient');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%$search%")
                  ->orWhere('national_id', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%")
                  ->orWhere('id', 'like', "%$search%");
            });
        }

        // 2. Get Data
        $patients = $query->get()->map(function ($patient) use ($hospitalId) {
            
            // Find Active Prescription for this Patient in THIS Hospital
            $activePrescription = Prescription::with(['drugs'])
                ->where('patient_id', $patient->id)
                ->where('hospital_id', $hospitalId)
                ->where('status', 'active')
                ->first();

            return [
                'fileNumber' => $patient->id,
                'name'       => $patient->full_name,
                'nationalId' => $patient->national_id,
                // Handle birth_date safely
                'birth'      => $patient->birth_date ?? 'N/A', 
                'phone'      => $patient->phone,
                'lastUpdated'=> $patient->updated_at->toIso8601String(),
                
                // Return Medications List (Formatted for Frontend)
                'medications' => $activePrescription ? $activePrescription->drugs->map(function($drug) {
                    return [
                        'id'       => $drug->id,
                        'pivot_id' => $drug->pivot->id, // Important for Update/Delete
                        'drugName' => $drug->name,
                        'dosage'   => $drug->pivot->monthly_quantity,
                        'note'     => $drug->pivot->note
                    ];
                }) : [],

                'hasPrescription' => !!$activePrescription
            ];
        });

        return $this->sendSuccess($patients, 'تم جلب بيانات المرضى بنجاح.');
    }

    /**
     * Get Single Patient Details (Added to complete the controller)
     */
    public function show(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;
        
        $patient = User::where('type', 'patient')->where('id', $id)->first();

        if (!$patient) return $this->sendError('المريض غير موجود.', [], 404);

        $activePrescription = Prescription::with(['drugs'])
            ->where('patient_id', $patient->id)
            ->where('hospital_id', $hospitalId)
            ->where('status', 'active')
            ->first();

        $data = [
            'fileNumber' => $patient->id,
            'name'       => $patient->full_name,
            'nationalId' => $patient->national_id,
            'birth'      => $patient->birth_date ?? 'N/A',
            'phone'      => $patient->phone,
            'lastUpdated'=> $patient->updated_at->toIso8601String(),
            'medications' => $activePrescription ? $activePrescription->drugs->map(function($drug) {
                return [
                    'id'       => $drug->id,
                    'pivot_id' => $drug->pivot->id,
                    'drugName' => $drug->name,
                    'dosage'   => $drug->pivot->monthly_quantity,
                    'note'     => $drug->pivot->note
                ];
            }) : [],
        ];

        return $this->sendSuccess($data, 'تم جلب بيانات المريض بنجاح.');
    }
}
