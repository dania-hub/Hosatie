<?php
namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Prescription;
use App\Models\PrescriptionDrug;
use App\Models\Drug;
use Illuminate\Http\Request;

class OperationLogController extends BaseApiController
{
    public function index(Request $request)
    {
        $logs = AuditLog::latest()->get();

        $data = $logs->map(function ($log) {
            $user = User::find($log->user_id);
            $patientName = $this->getPatientName($log);
            $translatedAction = $this->translateAction($log->action);
            
            // إضافة تفاصيل الدواء إذا كانت العملية متعلقة بدواء
            $operationType = $this->addDrugDetails($log, $translatedAction);

            return [
                'fileNumber'    => $log->record_id,                       // أو صيغة أخرى
                'name'          => $user->full_name ?? 'غير معروف',
                'patientName'   => $patientName,                          // اسم المريض أو "-"
                'operationType' => $operationType,                       // نوع العملية معرّب مع تفاصيل الدواء إن وجدت
                'operationDate' => $log->created_at?->format('Y/m/d'),
            ];
        });

        return response()->json($data);
    }

    /**
     * جلب اسم المريض إذا كانت العملية مرتبطة بمريض
     * 
     * @param AuditLog $log
     * @return string
     */
    private function getPatientName($log)
    {
        // إذا لم يكن هناك table_name أو record_id، لا يوجد مريض
        if (!$log->table_name || !$log->record_id) {
            return '-';
        }

        // الحالة 1: العملية مرتبطة مباشرة بمريض (table_name = 'users')
        if ($log->table_name === 'users') {
            $patient = User::find($log->record_id);
            
            if ($patient && $patient->type === 'patient') {
                return $patient->full_name ?? '-';
            }
            
            // محاولة استخراج اسم المريض من JSON إذا كان محذوفاً
            $values = json_decode($log->new_values ?? $log->old_values, true);
            if (is_array($values) && isset($values['full_name']) && isset($values['type']) && $values['type'] === 'patient') {
                return $values['full_name'];
            }
            
            return '-';
        }

        // الحالة 2: العملية مرتبطة بوصفة طبية (table_name = 'prescription')
        if ($log->table_name === 'prescription') {
            $prescription = Prescription::with('patient')->find($log->record_id);
            
            if ($prescription && $prescription->patient) {
                return $prescription->patient->full_name ?? '-';
            }
            
            // محاولة استخراج معلومات المريض من JSON
            $values = json_decode($log->new_values ?? $log->old_values, true);
            if (is_array($values)) {
                // إذا كان هناك patient_info في JSON
                if (isset($values['patient_info']) && isset($values['patient_info']['full_name'])) {
                    return $values['patient_info']['full_name'];
                }
                // إذا كان هناك patient_id، جرب جلب المريض
                if (isset($values['patient_id'])) {
                    $patient = User::find($values['patient_id']);
                    if ($patient && $patient->type === 'patient') {
                        return $patient->full_name ?? '-';
                    }
                }
            }
            
            return '-';
        }

        // الحالة 3: العملية مرتبطة بدواء في وصفة (table_name = 'prescription_drug')
        if ($log->table_name === 'prescription_drug') {
            $prescriptionDrug = PrescriptionDrug::with('prescription.patient')->find($log->record_id);
            
            if ($prescriptionDrug && $prescriptionDrug->prescription && $prescriptionDrug->prescription->patient) {
                return $prescriptionDrug->prescription->patient->full_name ?? '-';
            }
            
            // محاولة استخراج معلومات المريض من JSON
            $values = json_decode($log->new_values ?? $log->old_values, true);
            if (is_array($values)) {
                // إذا كان هناك patient_info في JSON
                if (isset($values['patient_info']) && isset($values['patient_info']['full_name'])) {
                    return $values['patient_info']['full_name'];
                }
                // إذا كان هناك prescription_id، جرب جلب الوصفة ثم المريض
                if (isset($values['prescription_id'])) {
                    $prescription = Prescription::with('patient')->find($values['prescription_id']);
                    if ($prescription && $prescription->patient) {
                        return $prescription->patient->full_name ?? '-';
                    }
                }
            }
            
            return '-';
        }

        // في جميع الحالات الأخرى، لا يوجد مريض مرتبط
        return '-';
    }

    /**
     * ترجمة نوع العملية إلى العربية
     * 
     * @param string $action
     * @return string
     */
    private function translateAction($action)
    {
        $translations = [
            // عمليات المرضى
            'create_patient' => 'إضافة مريض',
            'update_patient' => 'تعديل مريض',
            'delete_patient' => 'حذف مريض',
            
            // عمليات الأدوية في الوصفات
            'إضافة دواء' => 'إضافة دواء للمريض',
            'تعديل دواء' => 'تعديل دواء للمريض',
            'حذف دواء' => 'حذف دواء من المريض',
            
            // عمليات طلبات التوريد
            'إنشاء طلب توريد' => 'إنشاء طلب توريد',
            'create_external_supply_request' => 'إنشاء طلب توريد خارجي',
            'storekeeper_confirm_internal_request' => 'تأكيد طلب توريد داخلي',
            
            // عمليات الشحنات
            'استلام شحنة' => 'استلام شحنة',
            'pharmacist_confirm_internal_receipt' => 'تأكيد استلام شحنة داخلية',
            
            // عمليات عامة
            'create' => 'إنشاء',
            'update' => 'تعديل',
            'delete' => 'حذف',
            'created' => 'إنشاء',
            'updated' => 'تعديل',
            'deleted' => 'حذف',
            'assign' => 'إسناد',
            'dispense' => 'صرف وصفة طبية',
            'dispensed' => 'صرف وصفة طبية',
            'approved' => 'موافقة',
            'rejected' => 'رفض',
        ];

        // إذا كانت الترجمة موجودة، استخدمها
        if (isset($translations[$action])) {
            return $translations[$action];
        }

        // إذا كانت العملية بالفعل بالعربية، أعدها كما هي
        // (يمكن إضافة فحص بسيط هنا إذا لزم الأمر)
        
        // في حالة عدم وجود ترجمة، أعد النص الأصلي
        return $action;
    }

    /**
     * إضافة تفاصيل الدواء (الاسم والكمية) إلى نوع العملية
     * 
     * @param AuditLog $log
     * @param string $translatedAction
     * @return string
     */
    private function addDrugDetails($log, $translatedAction)
    {
        // فقط للعمليات المتعلقة بالأدوية في الوصفات
        if ($log->table_name !== 'prescription_drug') {
            return $translatedAction;
        }

        $drugName = null;
        $quantity = null;

        try {
            // محاولة جلب معلومات الدواء من السجل
            $prescriptionDrug = PrescriptionDrug::with('drug')->find($log->record_id);
            
            if ($prescriptionDrug && $prescriptionDrug->drug) {
                $drugName = $prescriptionDrug->drug->name;
                $quantity = $prescriptionDrug->monthly_quantity;
            } else {
                // في حالة الحذف أو عدم وجود السجل، محاولة جلب المعلومات من JSON
                $newValues = $log->new_values ? json_decode($log->new_values, true) : null;
                $oldValues = $log->old_values ? json_decode($log->old_values, true) : null;
                
                // محاولة من new_values أولاً
                if ($newValues && isset($newValues['drug_id'])) {
                    $drug = Drug::find($newValues['drug_id']);
                    if ($drug) {
                        $drugName = $drug->name;
                    }
                    $quantity = $newValues['monthly_quantity'] ?? null;
                } 
                // محاولة من old_values إذا لم نجد في new_values
                elseif ($oldValues && isset($oldValues['drug_id'])) {
                    $drug = Drug::find($oldValues['drug_id']);
                    if ($drug) {
                        $drugName = $drug->name;
                    }
                    $quantity = $oldValues['monthly_quantity'] ?? null;
                }
            }
        } catch (\Exception $e) {
            // في حالة الخطأ، نستمر بدون تفاصيل الدواء
            \Log::warning('Failed to get drug details for audit log', [
                'log_id' => $log->id,
                'error' => $e->getMessage()
            ]);
        }

        // بناء النص مع التفاصيل
        if ($drugName && $quantity !== null) {
            return $translatedAction . ' - الدواء: ' . $drugName . ' - الكمية: ' . $quantity;
        } elseif ($drugName) {
            return $translatedAction . ' - الدواء: ' . $drugName;
        }

        // إذا لم توجد تفاصيل الدواء، أعد النص الأصلي
        return $translatedAction;
    }
}
