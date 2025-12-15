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
                // Get patient name from record_id if table_name is 'users' or 'prescription'
                $patientName = 'غير محدد';
                $fileNumber = $log->record_id ?? 'N/A';
                
                // If the log is related to a patient (table_name = 'users' and record_id is a patient)
                if ($log->table_name === 'users' && $log->record_id) {
                    $patient = \App\Models\User::where('id', $log->record_id)
                        ->where('type', 'patient')
                        ->first();
                    if ($patient) {
                        $patientName = $patient->full_name ?? 'غير محدد';
                        $fileNumber = $patient->id;
                    }
                } elseif ($log->table_name === 'prescription' && $log->record_id) {
                    // If it's a prescription log, get patient from prescription
                    $prescription = \App\Models\Prescription::find($log->record_id);
                    if ($prescription && $prescription->patient) {
                        $patientName = $prescription->patient->full_name ?? 'غير محدد';
                        $fileNumber = $prescription->patient->id;
                    }
                }

                // Fallbacks:
                // 1) لو لم نجد مريضاً مرتبطاً، استخدم اسم المستخدم الذي نفذ العملية (الطبيب)
                if ($patientName === 'غير محدد' && $log->user) {
                    $patientName = $log->user->full_name ?? $log->user->name ?? 'غير محدد';
                }

                // 2) لو تحتوي new_values على الاسم الرباعي (JSON)، حاول استخراجه
                if ($patientName === 'غير محدد' && $log->new_values) {
                    $newValues = json_decode($log->new_values, true);
                    if (is_array($newValues)) {
                        $patientName = $newValues['full_name'] ?? $newValues['name'] ?? $patientName;
                    }
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
