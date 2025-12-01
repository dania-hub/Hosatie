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
     */
    public function activityLog(Request $request)
    {
        // Assuming AuditLog usage similar to DataEntry
        $logs = \App\Models\AuditLog::where('user_id', $request->user()->id)
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($log) {
                return [
                    'id'      => $log->id,
                    'action'  => $log->action, 
                    'details' => $log->new_values,
                    'date'    => $log->created_at->diffForHumans(),
                ];
            });

        return $this->sendSuccess($logs, 'تم جلب سجل النشاطات بنجاح.');
    }
}
