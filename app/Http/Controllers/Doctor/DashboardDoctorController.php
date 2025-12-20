<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Prescription;
use App\Models\PrescriptionDrug;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardDoctorController extends BaseApiController
{
       public function stats(Request $request)
    {
        $doctorId = $request->user()->id;

        // 1. إجمالي عدد المرضى (Total Registered Patients for this Doctor)
        // Logic: Count unique patients who have EVER had a prescription from this doctor
        $totalPatientsResult = DB::table('prescription')
            ->where('doctor_id', $doctorId)
            ->select(DB::raw('count(distinct patient_id) as count'))
            ->first();
        $totalPatients = $totalPatientsResult ? $totalPatientsResult->count : 0;

        // 2. عدد الكشوفات اليومية (Daily Examinations/Prescriptions)
        // يشمل: الوصفات الطبية التي تم إنشاؤها اليوم + الوصفات الطبية التي تم تعديلها اليوم
        // (من خلال عمليات إضافة/تعديل/حذف الأدوية)
        
        // أ) الوصفات الطبية التي تم إنشاؤها اليوم
        $prescriptionsCreatedToday = Prescription::where('doctor_id', $doctorId)
            ->whereDate('created_at', Carbon::today())
            ->pluck('id')
            ->toArray();
        
        // ب) الوصفات الطبية التي تم تحديثها اليوم (من خلال عمليات إضافة/تعديل/حذف الأدوية)
        // نحصل على الوصفات الطبية المرتبطة بعمليات إضافة/تعديل/حذف الأدوية من AuditLog
        $todayAuditLogs = AuditLog::where('user_id', $doctorId)
            ->where('table_name', 'prescription_drug')
            ->whereIn('action', ['إضافة دواء', 'تعديل دواء', 'حذف دواء'])
            ->whereDate('created_at', Carbon::today())
            ->get();
        
        $prescriptionIdsFromAudit = [];
        foreach ($todayAuditLogs as $log) {
            // محاولة جلب prescription_id من new_values أو old_values
            $newValues = $log->new_values ? json_decode($log->new_values, true) : null;
            $oldValues = $log->old_values ? json_decode($log->old_values, true) : null;
            
            if ($newValues && isset($newValues['prescription_id'])) {
                $prescriptionIdsFromAudit[] = $newValues['prescription_id'];
            } elseif ($oldValues && isset($oldValues['prescription_id'])) {
                $prescriptionIdsFromAudit[] = $oldValues['prescription_id'];
            } else {
                // محاولة جلب من PrescriptionDrug مباشرة
                try {
                    $prescriptionDrug = PrescriptionDrug::find($log->record_id);
                    if ($prescriptionDrug && $prescriptionDrug->prescription_id) {
                        $prescriptionIdsFromAudit[] = $prescriptionDrug->prescription_id;
                    }
                } catch (\Exception $e) {
                    // في حالة الحذف، قد لا يكون السجل موجوداً
                }
            }
        }
        
        // ج) دمج جميع الوصفات الطبية المميزة
        $allPrescriptionIds = array_unique(array_merge($prescriptionsCreatedToday, $prescriptionIdsFromAudit));
        
        // د) التأكد من أن الوصفات الطبية تخص هذا الطبيب
        if (empty($allPrescriptionIds)) {
            $dailyExaminations = 0;
        } else {
            $validPrescriptionIds = Prescription::where('doctor_id', $doctorId)
                ->whereIn('id', $allPrescriptionIds)
                ->pluck('id')
                ->toArray();
            
            $dailyExaminations = count($validPrescriptionIds);
        }

        // 3. عدد الحالات قيد المتابعة (Active Cases)
        $activeCasesResult = DB::table('prescription')
            ->where('doctor_id', $doctorId)
            ->where('status', 'active')
            ->select(DB::raw('count(distinct patient_id) as count'))
            ->first();
        $activeCases = $activeCasesResult ? $activeCasesResult->count : 0;

        $data = [
            'totalRegistered' => $totalPatients,     // إجمالي عدد المرضى
            'todayRegistered' => $dailyExaminations, // عدد الكشوفات اليوم
            'weekRegistered'  => $activeCases        // عدد الحالات قيد المتابعة
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
                    $prescriptionDrug = \App\Models\PrescriptionDrug::with(['prescription.patient', 'drug'])->find($log->record_id);
                    
                    // محاولة جلب معلومات الدواء
                    $drugName = null;
                    $quantity = null;
                    
                    if ($prescriptionDrug) {
                        if ($prescriptionDrug->drug) {
                            $drugName = $prescriptionDrug->drug->name ?? null;
                        }
                        $quantity = $prescriptionDrug->monthly_quantity ?? null;
                        
                        if ($prescriptionDrug->prescription) {
                            $patient = $prescriptionDrug->prescription->patient;
                        }
                    }
                    
                    // إذا لم يوجد السجل (محذوف)، حاول جلب معلومات المريض والدواء من JSON
                    if (!$patient || !$drugName) {
                        $values = json_decode($log->new_values ?? $log->old_values, true);
                        if (is_array($values)) {
                            // جلب معلومات الدواء
                            if (!$drugName && isset($values['drug_id'])) {
                                $drug = \App\Models\Drug::find($values['drug_id']);
                                if ($drug) {
                                    $drugName = $drug->name;
                                }
                            }
                            if ($quantity === null && isset($values['monthly_quantity'])) {
                                $quantity = $values['monthly_quantity'];
                            }
                            
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
                
                $result = [
                    'fileNumber'    => $fileNumber,
                    'name'          => $patientName,
                    'operationType' => $log->action ?? 'غير محدد',
                    'operationDate' => $log->created_at ? $log->created_at->format('Y/m/d') : 'N/A',
                ];
                
                // إضافة معلومات الدواء إذا كانت متوفرة
                if ($drugName) {
                    $result['drugName'] = $drugName;
                }
                if ($quantity !== null) {
                    $result['quantity'] = $quantity;
                }
                
                return $result;
            });

        return $this->sendSuccess($logs, 'تم جلب سجل النشاطات بنجاح.');
    }
}
