<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Prescription;
use App\Models\PrescriptionDrug;
use App\Models\AuditLog;
use Carbon\Carbon;

class DashboardDoctorController extends BaseApiController
{
       public function stats(Request $request)
    {
        $doctorId = $request->user()->id;
        $hospitalId = $request->user()->hospital_id;

        // التأكد من أن المستخدم لديه hospital_id
        if (!$hospitalId) {
            return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
        }

        // ملاحظة: جميع الإحصائيات هنا خاصة بالطبيب المعين فقط (doctor_id)
        // hospital_id يستخدم فقط للتحقق من الأمان والاتساق

        // 1. إجمالي عدد المرضى (خاصة بهذا الطبيب فقط)
        // Count unique patients who have EVER had a prescription from THIS doctor
        $totalPatients = Prescription::where('doctor_id', $doctorId)
            ->where('hospital_id', $hospitalId) // للتحقق من الأمان
            ->distinct('patient_id')
            ->count();

        // 2. عدد الكشوفات اليومية (خاصة بهذا الطبيب فقط)
        // نحسب عدد المرضى الفريدين الذين قام هذا الطبيب بعمليات على أدويتهم اليوم (إضافة/تعديل/حذف)
        // من خلال AuditLog للطبيب المعين فقط (user_id = doctor_id)
        $todayAuditLogs = AuditLog::where('user_id', $doctorId) // العمليات الخاصة بهذا الطبيب فقط
            ->whereIn('table_name', ['prescription_drug', 'prescription_drugs'])
            ->whereDate('created_at', Carbon::today())
            ->get();

        $patientIds = $todayAuditLogs->map(function($log) use ($doctorId, $hospitalId) {
            // محاولة جلب patient_id من new_values أو old_values
            $newValues = $log->new_values ? json_decode($log->new_values, true) : null;
            $oldValues = $log->old_values ? json_decode($log->old_values, true) : null;
            
            // 1. محاولة من patient_info (مع التحقق من أن Prescription مرتبطة بهذا الطبيب)
            $patientInfo = $newValues['patient_info'] ?? $oldValues['patient_info'] ?? null;
            if ($patientInfo && isset($patientInfo['id'])) {
                // التحقق من أن المريض في نفس المستشفى (للأمان)
                $patient = \App\Models\User::where('id', $patientInfo['id'])
                    ->where('hospital_id', $hospitalId)
                    ->first();
                if ($patient) {
                    return $patientInfo['id'];
                }
            }
            
            // 2. محاولة من prescription_id (مع التأكد من أن Prescription خاصة بهذا الطبيب)
            $prescriptionId = $newValues['prescription_id'] ?? $oldValues['prescription_id'] ?? null;
            if ($prescriptionId) {
                $prescription = Prescription::where('doctor_id', $doctorId) // خاصة بهذا الطبيب فقط
                    ->where('hospital_id', $hospitalId)
                    ->find($prescriptionId);
                if ($prescription) {
                    return $prescription->patient_id;
                }
            }
            
            // 3. محاولة من record_id (prescription_drug id) مع التأكد من أن Prescription خاصة بهذا الطبيب
            if ($log->record_id) {
                $prescriptionDrug = PrescriptionDrug::with(['prescription' => function($query) use ($doctorId, $hospitalId) {
                    $query->where('doctor_id', $doctorId) // خاصة بهذا الطبيب فقط
                          ->where('hospital_id', $hospitalId);
                }])->find($log->record_id);
                if ($prescriptionDrug && $prescriptionDrug->prescription) {
                    return $prescriptionDrug->prescription->patient_id;
                }
            }
            
            return null;
        })->filter()->unique();
        
        $dailyExaminations = $patientIds->count();

        // 3. عدد الحالات قيد المتابعة (خاصة بهذا الطبيب فقط)
        $activeCases = Prescription::where('doctor_id', $doctorId) // خاصة بهذا الطبيب فقط
            ->where('hospital_id', $hospitalId)
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

    /**
     * GET /api/doctor/operations
     * سجل جميع عمليات الطبيب:
     * - إضافة دواء للمريض (من AuditLog - prescription_drug)
     * - تعديل دواء للمريض (من AuditLog - prescription_drug)
     * - حذف دواء من المريض (من AuditLog - prescription_drug)
     * - إنشاء طلبات توريد (من AuditLog - internal_supply_request) عندما كان مدير قسم
     */
    public function operations(Request $request)
    {
        $doctorId = $request->user()->id;
        
        // جلب جميع عمليات المستخدم من AuditLog
        // 1. عمليات الأدوية (prescription_drug)
        // 2. عمليات إنشاء طلبات التوريد (internal_supply_request) عندما كان مدير قسم
        $logs = AuditLog::where('user_id', $doctorId)
            ->where(function($query) {
                $query->whereIn('table_name', ['prescription_drug', 'prescription_drugs'])
                      ->orWhere(function($q) {
                          $q->where('table_name', 'internal_supply_request')
                            ->where('action', 'department_create_supply_request');
                      });
            })
            ->orderBy('created_at', 'desc')
            ->take(100)
            ->get()
            ->map(function ($log) {
                $operationData = [
                    'operationType' => $this->translateAction($log->action),
                    'operationDate' => $log->created_at->format('Y/m/d'),
                    'operationDateTime' => $log->created_at->format('Y/m/d H:i'),
                ];

                $newValues = $log->new_values ? json_decode($log->new_values, true) : null;
                $oldValues = $log->old_values ? json_decode($log->old_values, true) : null;
                $patientInfo = $newValues['patient_info'] ?? $oldValues['patient_info'] ?? null;
                
                // معالجة عمليات إنشاء طلبات التوريد (عندما كان مدير قسم)
                if ($log->table_name === 'internal_supply_request' && $log->action === 'department_create_supply_request') {
                    $operationData['operationType'] = 'إنشاء طلب توريد';
                    $operationData['fileNumber'] = 'REQ-' . ($log->record_id ?? 'N/A');
                    $operationData['name'] = $newValues['department_name'] ?? 'قسم غير محدد';
                    
                    // إضافة معلومات إضافية عن الطلب
                    if ($newValues && isset($newValues['item_count'])) {
                        $operationData['details'] = "إنشاء طلب توريد يحتوي على {$newValues['item_count']} عنصر";
                    } else {
                        $operationData['details'] = 'إنشاء طلب توريد';
                    }
                    
                    return $operationData;
                }
                
                // معالجة عمليات الأدوية (prescription_drug)
                // محاولة جلب معلومات الدواء والمريض
                $drugName = null;
                $quantity = null;
                $patient = null;
                
                try {
                    // محاولة جلب PrescriptionDrug إذا كان موجوداً
                    $prescriptionDrug = PrescriptionDrug::with(['prescription.patient', 'drug'])
                        ->find($log->record_id);
                    
                    if ($prescriptionDrug) {
                        // جلب معلومات الدواء
                        if ($prescriptionDrug->drug) {
                            $drugName = $prescriptionDrug->drug->name ?? null;
                        }
                        $quantity = $prescriptionDrug->monthly_quantity ?? null;
                        
                        // جلب معلومات المريض
                        if ($prescriptionDrug->prescription && $prescriptionDrug->prescription->patient) {
                            $patient = $prescriptionDrug->prescription->patient;
                            $operationData['fileNumber'] = $patient->id;
                            $operationData['name'] = $patient->full_name ?? $patient->name ?? 'غير محدد';
                        }
                    } else {
                        // في حالة الحذف أو عدم وجود السجل، محاولة جلب المعلومات من new_values أو old_values
                        
                        // محاولة جلب معلومات الدواء من new_values أو old_values
                        $drugId = null;
                        if ($newValues && isset($newValues['drug_id'])) {
                            $drugId = $newValues['drug_id'];
                            $quantity = $newValues['monthly_quantity'] ?? null;
                        } elseif ($oldValues && isset($oldValues['drug_id'])) {
                            $drugId = $oldValues['drug_id'];
                            $quantity = $oldValues['monthly_quantity'] ?? null;
                        }
                        
                        if ($drugId) {
                            $drug = \App\Models\Drug::find($drugId);
                            if ($drug) {
                                $drugName = $drug->name;
                            }
                        }
                        
                        // محاولة جلب معلومات المريض من prescription_id
                        $prescriptionId = null;
                        if ($newValues && isset($newValues['prescription_id'])) {
                            $prescriptionId = $newValues['prescription_id'];
                        } elseif ($oldValues && isset($oldValues['prescription_id'])) {
                            $prescriptionId = $oldValues['prescription_id'];
                        }
                        
                        if ($prescriptionId) {
                            $prescription = Prescription::with('patient')->find($prescriptionId);
                            if ($prescription && $prescription->patient) {
                                $patient = $prescription->patient;
                                $operationData['fileNumber'] = $patient->id;
                                $operationData['name'] = $patient->full_name ?? $patient->name ?? 'غير محدد';
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error processing operation log', [
                        'log_id' => $log->id,
                        'error' => $e->getMessage()
                    ]);
                }
                
                // إذا لم يتم تعيين معلومات المريض بعد بشكل صحيح، نحاول من patient_info
                if (!isset($operationData['fileNumber']) || !isset($operationData['name'])) {
                    if ($patientInfo && isset($patientInfo['id']) && isset($patientInfo['full_name'])) {
                        $operationData['fileNumber'] = $patientInfo['id'];
                        $operationData['name'] = $patientInfo['full_name'];
                    } else {
                        if (!isset($operationData['fileNumber'])) {
                            $operationData['fileNumber'] = $log->record_id ?? 'N/A';
                        }
                        if (!isset($operationData['name'])) {
                            $operationData['name'] = 'غير محدد';
                        }
                    }
                }
                
                // إضافة معلومات الدواء إذا كانت متوفرة
                if ($drugName) {
                    $operationData['drugName'] = $drugName;
                }
                if ($quantity !== null) {
                    $operationData['quantity'] = $quantity;
                }

                return $operationData;
            })
            ->filter(function ($operation) {
                // التأكد من وجود البيانات الأساسية
                // للعمليات العادية (prescription_drug): يجب أن يكون هناك fileNumber و name
                // لعمليات إنشاء الطلبات (internal_supply_request): يجب أن يكون هناك fileNumber على الأقل
                if (isset($operation['operationType']) && $operation['operationType'] === 'إنشاء طلب توريد') {
                    return isset($operation['fileNumber']);
                }
                return isset($operation['fileNumber']) && isset($operation['name']);
            })
            ->values()
            ->toArray();

        return $this->sendSuccess($logs, 'تم تحميل سجل العمليات بنجاح.');
    }

    /**
     * ترجمة نوع العملية إلى نص عربي واضح
     */
    private function translateAction($action)
    {
        $translations = [
            'إضافة دواء' => 'إضافة دواء للمريض',
            'تعديل دواء' => 'تعديل دواء للمريض',
            'حذف دواء' => 'حذف دواء من المريض',
            'create' => 'إضافة دواء للمريض',
            'update' => 'تعديل دواء للمريض',
            'delete' => 'حذف دواء من المريض',
            'assign' => 'إضافة دواء للمريض',
            'department_create_supply_request' => 'إنشاء طلب توريد',
            'إنشاء طلب توريد' => 'إنشاء طلب توريد',
        ];

        return $translations[$action] ?? $action;
    }
}
