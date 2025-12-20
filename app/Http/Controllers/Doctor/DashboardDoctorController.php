<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Prescription;
use Carbon\Carbon;

class DashboardDoctorController extends BaseApiController
{
       public function stats(Request $request)
    {
        $doctorId = $request->user()->id;

        // 1. إجمالي عدد المرضى (Total Registered Patients for this Doctor)
        // Logic: Count unique patients who have EVER had a prescription from this doctor
        $totalPatients = Prescription::where('doctor_id', $doctorId)
            ->distinct('patient_id')
            ->count();

        // 2. عدد الكشوفات اليومية (Daily Examinations/Prescriptions)
        $dailyExaminations = Prescription::where('doctor_id', $doctorId)
            ->whereDate('created_at', Carbon::today())
            ->count();

        // 3. عدد الحالات قيد المتابعة (Active Cases)
        $activeCases = Prescription::where('doctor_id', $doctorId)
            ->where('status', 'active')
            ->distinct('patient_id')
            ->count();

        $data = [
            'totalRegistered' => $totalPatients,     // Changed from 'today' to 'total'
            'todayRegistered' => $dailyExaminations, // Matches "Daily Examinations"
            'weekRegistered'  => $activeCases        // Matches "Under Follow-up"
        ];

        return $this->sendSuccess($data, 'تم جلب إحصائيات لوحة التحكم بنجاح.');
    }

    /**
     * Activity Log Endpoint (Added as per your routes requirement)
     * GET /api/doctor/dashboard/activity-log
     */
    public function activityLog(Request $request)
    {
        $doctorId = $request->user()->id;
        
        // Get audit logs for this doctor
        $logs = \App\Models\AuditLog::where('user_id', $doctorId)
            ->with(['patientUser'])
            ->latest()
            ->take(50)
            ->get()
            ->map(function ($log) {
                // Get patient name from record_id based on table_name
                $patientName = 'غير محدد';
                $fileNumber = $log->record_id ?? 'N/A';
                $patient = null;
                
                // Try to find patient based on table_name
                if ($log->table_name === 'users' && $log->record_id) {
                    // If the log is related to a patient directly
                    $patient = \App\Models\User::where('id', $log->record_id)
                        ->where('type', 'patient')
                        ->first();
                        
                    // إذا لم يوجد المريض، حاول استخراج معلوماته من new_values أو old_values
                    if (!$patient) {
                        $values = json_decode($log->new_values ?? $log->old_values, true);
                        if (is_array($values) && isset($values['full_name'])) {
                            $patientName = $values['full_name'];
                            $fileNumber = $log->record_id;
                        }
                    }
                } elseif ($log->table_name === 'prescription' && $log->record_id) {
                    // If it's a prescription log, get patient from prescription
                    $prescription = \App\Models\Prescription::with('patient')->find($log->record_id);
                    if ($prescription) {
                        $patient = $prescription->patient;
                    }
                } elseif ($log->table_name === 'prescription_drug' && $log->record_id) {
                    // If it's a prescription drug log, get patient from prescription_drug -> prescription -> patient
                    $prescriptionDrug = \App\Models\PrescriptionDrug::with('prescription.patient')->find($log->record_id);
                    if ($prescriptionDrug && $prescriptionDrug->prescription) {
                        $patient = $prescriptionDrug->prescription->patient;
                    }
                    
                    // إذا لم يوجد السجل (محذوف)، حاول جلب معلومات المريض من JSON
                    if (!$patient) {
                        $values = json_decode($log->new_values ?? $log->old_values, true);
                        if (is_array($values)) {
                            // أولاً: تحقق من وجود patient_info (الطريقة الجديدة)
                            if (isset($values['patient_info']) && isset($values['patient_info']['full_name'])) {
                                $patientName = $values['patient_info']['full_name'];
                                $fileNumber = $values['patient_info']['id'] ?? $log->record_id;
                            }
                            // ثانياً: حاول جلب المريض من prescription_id (الطريقة القديمة)
                            elseif (isset($values['prescription_id'])) {
                                $prescription = \App\Models\Prescription::with('patient')->find($values['prescription_id']);
                                if ($prescription) {
                                    $patient = $prescription->patient;
                                }
                            }
                        }
                    }
                }
                
                // إذا وجدنا المريض، استخرج معلوماته
                if ($patient) {
                    $patientName = $patient->full_name ?? 'غير محدد';
                    $fileNumber = $patient->id;
                }
                
                return [
                    'fileNumber'    => $fileNumber,
                    'name'          => $patientName,
                    'operationType' => $log->action ?? 'غير محدد',
                    'operationDate' => $log->created_at ? $log->created_at->format('Y/m/d') : 'N/A',
                ];
            });

        return $this->sendSuccess($logs, 'تم جلب سجل النشاطات بنجاح.');
    }
}
