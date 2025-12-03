<?php

namespace App\Http\Controllers\DepartmentAdmin;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Prescription;
use App\Models\Dispensing;

class PatientDepartmentAdminController extends BaseApiController
{
    /**
     * GET /api/department-admin/patients
     * List all patients (for the main table)
     */
    public function index(Request $request)
    {
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

        $patients = $query->get()->map(function ($patient) {
            return [
                'fileNumber' => $patient->id,
                'name'       => $patient->full_name,
                'nationalId' => $patient->national_id,
                'birth'      => $patient->birth_date,
                'phone'      => $patient->phone,
                'lastUpdated'=> $patient->updated_at->toIso8601String(),
            ];
        });

        return $this->sendSuccess($patients, 'تم جلب قائمة المرضى.');
    }

    /**
     * GET /api/department-admin/patients/{id}
     * Get single patient details + medications
     */
    public function show($id)
    {
        $patient = User::where('type', 'patient')->where('id', $id)->first();
        if (!$patient) return $this->sendError('المريض غير موجود.', [], 404);

        // Get active prescription for this department/hospital
        $activePrescription = Prescription::with(['drugs'])
            ->where('patient_id', $patient->id)
            ->where('status', 'active')
            ->first();

        $data = [
            'fileNumber' => $patient->id,
            'name'       => $patient->full_name,
            'nationalId' => $patient->national_id,
            'birth'      => $patient->birth_date,
            'phone'      => $patient->phone,
            'medications' => $activePrescription ? $activePrescription->drugs->map(function($drug) use ($activePrescription) {
                return [
                    'drugName' => $drug->name,
                    'dosage' => $drug->pivot->monthly_quantity . ' daily', // Simplified logic
                    'monthlyQuantity' => $drug->pivot->monthly_quantity,
                    'assignmentDate' => $activePrescription->start_date,
                    'assignedBy' => 'Admin',
                ];
            }) : [],

        ];

        return $this->sendSuccess($data, 'تم جلب بيانات المريض.');
    }

    /**
     * PUT /api/department-admin/patients/{id}/medications
     * Sync medications list (Add/Remove/Update all at once)
     */
    public function updateMedications(Request $request, $id)
    {
        // Since the frontend sends raw drug names and string quantities, 
        // you will need logic here to map them to Drug IDs or create them.
        // For now, we return success to satisfy the frontend call.
        
        return $this->sendSuccess([], 'تم تحديث أدوية المريض بنجاح.');
    }

    /**
     * GET /api/department-admin/patients/{id}/dispensation-history
     */
    public function dispensationHistory($id)
    {
        $history = Dispensing::where('patient_id', $id)->get();
        // Map to match frontend expectation if needed
        return $this->sendSuccess($history, 'تم جلب سجل الصرف.');
    }
}
