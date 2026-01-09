<?php
namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Prescription;
use App\Models\PrescriptionDrug;
use App\Models\Drug;
use App\Models\Complaint;
use App\Models\PatientTransferRequest;
use App\Http\Controllers\AdminHospital\AuditLogHospitalAdminController;
use Illuminate\Http\Request;

class OperationLogController extends BaseApiController
{
    public function index(Request $request)
    {
        $user = $request->user();
        $hospitalId = $user->hospital_id;

        // التأكد من أن المستخدم لديه hospital_id
        if (!$hospitalId) {
            return response()->json([
                'error' => 'المستخدم غير مرتبط بمستشفى'
            ], 400);
        }

        // جلب السجلات مع فلترة حسب hospital_id واستبعاد جميع العمليات التي قام بها مدير المستشفى
        // نستخدم whereHas للفلترة في قاعدة البيانات مباشرة لتحسين الأداء
        $logs = AuditLog::where('hospital_id', $hospitalId)
            ->where(function ($query) {
                // إما لا يوجد user_id (سجلات قديمة)
                $query->whereNull('user_id')
                    // أو يوجد user_id لكن user غير موجود (تم حذفه)
                    ->orWhereDoesntHave('user')
                    // أو يوجد user لكنه ليس hospital_admin (استبعاد جميع عمليات hospital_admin)
                    ->orWhereHas('user', function ($q) {
                        $q->where('type', '!=', 'hospital_admin');
                    });
            })->latest()->get();
        
        // فلترة إضافية للتأكد من عدم وجود أي عمليات لمدير المستشفى
        $logs = $logs->filter(function ($log) {
            if (!$log->user_id) {
                return true; // السجلات القديمة بدون user_id تظهر
            }
            
            $user = User::find($log->user_id);
            if (!$user) {
                return true; // المستخدم المحذوف تظهر عملياته
            }
            
            // استبعاد جميع عمليات hospital_admin
            return $user->type !== 'hospital_admin';
        });

        $data = $logs->map(function ($log) {
            $user = User::find($log->user_id);
            $patientName = $this->getPatientName($log);
            $translatedAction = $this->translateAction($log->action);
            
            // إضافة تفاصيل الدواء إذا كانت العملية متعلقة بدواء
            $operationType = $this->addDrugDetails($log, $translatedAction);
            
            // إضافة رقم الشحنة إذا كانت العملية متعلقة بطلب
            $operationType = $this->addRequestDetails($log, $operationType);

            // الحصول على تفاصيل إضافية للشكوى أو طلب نقل المريض
            // ملاحظة: نحن نستخدم getOperationDetails فقط لبعض العمليات المحددة
            // ولا نستبدل نوع العملية بالكامل، بل ندمج التفاصيل إذا لزم الأمر
            try {
                $userType = $user->type ?? null;
                
                // للعمليات المتعلقة بمدير المخزن والصيدلي ومدير القسم على الشحنات، نتعامل معها بشكل خاص
                // لأن addRequestDetails يضيف رقم الشحنة بالفعل، لا نحتاج getOperationDetails
                if (in_array($userType, ['warehouse_manager', 'pharmacist', 'department_head']) && 
                    in_array($log->table_name, ['internal_supply_request', 'external_supply_request', 'external_supply_requests'])) {
                    // نوع العملية جاهز من addRequestDetails، لا حاجة لتعديله
                } elseif (in_array($log->table_name, ['complaints', 'patient_transfer_requests', 'drugs', 'departments', 'users'])) {
                    // فقط للعمليات التي نحتاج فيها تفاصيل إضافية
                    $auditCtrl = new AuditLogHospitalAdminController();
                    $detailOperation = $auditCtrl->getOperationDetails($log);
                    if ($detailOperation) {
                        // دمج نوع العملية مع التفاصيل بدلاً من استبدالها
                        // فقط إذا كانت التفاصيل تحتوي على معلومات إضافية وليست مجرد رقم شحنة
                        if (!preg_match('/^(INT-|EXT-)\d+$/', $detailOperation)) {
                            // إذا كانت التفاصيل ليست مجرد رقم شحنة (تمت إضافتها بالفعل في addRequestDetails)
                            // ندمج نوع العملية مع التفاصيل
                            if (strpos($operationType, $detailOperation) === false) {
                                $operationType = $translatedAction . ' - ' . $detailOperation;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to get detailed operation info', ['log_id' => $log->id, 'error' => $e->getMessage()]);
            }

            // الحصول على رقم الملف المناسب (رقم ملف الشحنة للشحنات، رقم المريض للمرضى)
            $fileNumber = $this->getFileNumber($log);

            $result = [
                'fileNumber'    => $fileNumber,
                'name'          => $user->full_name ?? 'غير معروف',
                'role'          => $this->translateUserType($user->type ?? ''),
                'patientName'   => $patientName,                          // اسم المريض أو "-"
                'operationType' => $operationType,                       // نوع العملية معرّب مع تفاصيل الدواء إن وجدت
                'operationDate' => $log->created_at?->format('Y/m/d'),
            ];

            // إضافة changes للعمليات المتعلقة بالمرضى (مثل dataEntry/operationLog)
            if ($log->table_name === 'users') {
                $patient = User::find($log->record_id);
                if ($patient && $patient->type === 'patient') {
                    $old = $log->old_values ? json_decode($log->old_values, true) : [];
                    $new = $log->new_values ? json_decode($log->new_values, true) : [];
                    $result['changes'] = [
                        'old' => $old,
                        'new' => $new,
                    ];
                }
            }

            return $result;
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
        // إذا لم يكن هناك table_name، لا يمكن تحديد المريض
        if (!$log->table_name) {
            // محاولة أخيرة - البحث في JSON عن patient_id أو patient_name
            $values = json_decode($log->new_values ?? $log->old_values, true);
            if (is_array($values)) {
                if (isset($values['patient_name'])) {
                    return $values['patient_name'];
                }
                if (isset($values['patient_id'])) {
                    $patient = User::find($values['patient_id']);
                    if ($patient && $patient->type === 'patient') {
                        return $patient->full_name ?? '-';
                    }
                }
            }
            return '-';
        }

        // الحالة 1: العملية مرتبطة مباشرة بمريض (table_name = 'users')
        if ($log->table_name === 'users') {
            $patient = User::find($log->record_id);
            
            if ($patient && $patient->type === 'patient') {
                return $patient->full_name ?? '-';
            }
            
            // محاولة استخراج اسم المريض من JSON إذا كان محذوفاً
            // في حالة الحذف، نستخدم old_values (لأن new_values = null)
            $values = json_decode($log->old_values ?? $log->new_values, true);
            if (is_array($values)) {
                // إذا كان هناك full_name في old_values، استخدمه (خاصة في حالة الحذف)
                if (isset($values['full_name'])) {
                    // للتحقق من أنه مريض، نتحقق من action أو من وجود حقول خاصة بالمرضى
                    if ($log->action === 'delete_patient' || 
                        $log->action === 'create_patient' || 
                        $log->action === 'update_patient' ||
                        isset($values['national_id']) || 
                        isset($values['birth_date']) ||
                        (isset($values['type']) && $values['type'] === 'patient')) {
                        return $values['full_name'];
                    }
                }
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

        // الحالة 3: العملية مرتبطة بدواء في وصفة (table_name = 'prescription_drug' أو 'prescription_drugs')
        if ($log->table_name === 'prescription_drug' || $log->table_name === 'prescription_drugs') {
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
                // إذا كان هناك patient_name مباشرة في JSON (مثل في حالة صرف الأدوية)
                if (isset($values['patient_name'])) {
                    return $values['patient_name'];
                }
            }
            
            return '-';
        }

        // الحالة 4: العملية مرتبطة بصرف دواء (table_name = 'dispensings')
        // في هذه الحالة، record_id هو patient_id مباشرة
        if ($log->table_name === 'dispensings') {
            // محاولة استخراج اسم المريض من JSON أولاً
            $values = json_decode($log->new_values ?? $log->old_values, true);
            if (is_array($values)) {
                if (isset($values['patient_name'])) {
                    return $values['patient_name'];
                }
                if (isset($values['patient_id'])) {
                    $patient = User::find($values['patient_id']);
                    if ($patient && $patient->type === 'patient') {
                        return $patient->full_name ?? '-';
                    }
                }
            }
            
            // إذا لم نجد في JSON، جرب استخدام record_id كـ patient_id
            if ($log->record_id) {
                $patient = User::find($log->record_id);
                if ($patient && $patient->type === 'patient') {
                    return $patient->full_name ?? '-';
                }
            }
            
            return '-';
        }

        // الحالة 5: محاولة أخيرة - البحث في JSON عن patient_id أو patient_name لأي جدول آخر
        $values = json_decode($log->new_values ?? $log->old_values, true);
        if (is_array($values)) {
            // إذا كان هناك patient_name مباشرة
            if (isset($values['patient_name'])) {
                return $values['patient_name'];
            }
            // إذا كان هناك patient_id، جرب جلب المريض
            if (isset($values['patient_id'])) {
                $patient = User::find($values['patient_id']);
                if ($patient && $patient->type === 'patient') {
                    return $patient->full_name ?? '-';
                }
            }
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
            'تراجع عن صرف وصفة طبية' => 'تراجع عن صرف وصفة طبية',
            
            // عمليات طلبات التوريد الداخلية
            'إنشاء طلب توريد' => 'إنشاء طلب توريد',
            'department_create_supply_request' => 'إنشاء طلب توريد داخلي (قسم)',
            'pharmacist_create_supply_request' => 'إنشاء طلب توريد داخلي (صيدلي)',
            'storekeeper_confirm_internal_request' => 'تأكيد طلب توريد داخلي',
            'storekeeper_reject_internal_request' => 'رفض طلب توريد داخلي',
            'رفض طلب توريد داخلي' => 'رفض طلب توريد داخلي',
            
            // عمليات الشحنات الداخلية
            'استلام شحنة' => 'استلام شحنة',
            'pharmacist_confirm_internal_receipt' => 'تأكيد استلام شحنة داخلية',
            'department_confirm_internal_receipt' => 'تأكيد استلام شحنة داخلية (قسم)',
            
            // عمليات طلبات التوريد الخارجية
            'create_external_supply_request' => 'إنشاء طلب توريد خارجي',
            'storekeeper_confirm_external_delivery' => 'تأكيد استلام توريد خارجي',
            'storekeeper_reject_external_request' => 'رفض طلب توريد خارجي',
            'supplier_create_external_supply_request' => 'إنشاء طلب توريد خارجي (مورد)',
            'supplier_confirm_external_supply_request' => 'تأكيد طلب توريد خارجي (مورد)',
            'supplier_approve_external_supply_request' => 'موافقة مورد على طلب توريد خارجي',
            'supplier_reject_external_supply_request' => 'رفض طلب توريد خارجي (مورد)',
            'hospital_admin_confirm_external_supply_request' => 'قبول طلب توريد خارجي',
            'hospital_admin_reject_external_supply_request' => 'رفض طلب توريد خارجي',
            'hospital_admin_update_external_supply_request_notes' => 'تحديث ملاحظات طلب توريد خارجي',
            
            // عمليات طلبات نقل المريض
            'preapprove' => 'الموافقة الأولية على نقل مريض',
            'approve' => 'الموافقة على نقل مريض',
            
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
            'confirm' => 'تأكيد',
            'confirmed' => 'تم التأكيد',
            'reject' => 'رفض',
            'rejection' => 'رفض',
        ];

        // إذا كانت الترجمة موجودة، استخدمها
        if (isset($translations[$action])) {
            return $translations[$action];
        }

        // التحقق من إذا كانت النص يحتوي على أحرف عربية (نسبة بسيطة من الأحرف العربية)
        // إذا كانت النص يحتوي على أحرف عربية أكثر من الإنجليزية، أعدها كما هي
        $arabicChars = preg_match_all('/[\x{0600}-\x{06FF}]/u', $action);
        $totalChars = mb_strlen($action);
        if ($totalChars > 0 && ($arabicChars / $totalChars) > 0.3) {
            return $action;
        }
        
        // في حالة عدم وجود ترجمة والنص ليس عربي، أعد النص الأصلي
        // (يمكن إضافة ترجمة تلقائية هنا في المستقبل)
        return $action;
    }

    /**
     * الحصول على رقم الملف المناسب حسب نوع العملية
     * 
     * @param AuditLog $log
     * @return string|int
     */
    private function getFileNumber($log)
    {
        // للعمليات المتعلقة بالشحنات الداخلية (internal_supply_request)
        if ($log->table_name === 'internal_supply_request') {
            // محاولة استخراج request_id من JSON أولاً
            $values = json_decode($log->new_values ?? $log->old_values, true);
            $requestId = null;
            if (is_array($values) && isset($values['request_id'])) {
                $requestId = $values['request_id'];
            } elseif ($log->record_id) {
                $requestId = $log->record_id;
            }
            return $requestId ? 'INT-' . $requestId : ($log->record_id ?? '-');
        }
        
        // للعمليات المتعلقة بالطلبات الخارجية (external_supply_request)
        if ($log->table_name === 'external_supply_request') {
            // محاولة استخراج request_id من JSON أولاً
            $values = json_decode($log->new_values ?? $log->old_values, true);
            $requestId = null;
            if (is_array($values) && isset($values['request_id'])) {
                $requestId = $values['request_id'];
            } elseif ($log->record_id) {
                $requestId = $log->record_id;
            }
            return $requestId ? 'EXT-' . $requestId : ($log->record_id ?? '-');
        }
        
        // للعمليات المتعلقة بالمرضى، استخدم record_id كرقم ملف
        if (in_array($log->table_name, ['users', 'prescription', 'prescription_drug', 'prescription_drugs', 'dispensings'])) {
            return $log->record_id ?? '-';
        }
        
        // في جميع الحالات الأخرى، استخدم record_id
        return $log->record_id ?? '-';
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
        // معالجة عمليات التراجع عن صرف وصفة طبية
        if ($log->action === 'تراجع عن صرف وصفة طبية' || $translatedAction === 'تراجع عن صرف وصفة طبية') {
            try {
                $newValues = $log->new_values ? json_decode($log->new_values, true) : null;
                
                if ($newValues && isset($newValues['drugs']) && is_array($newValues['drugs'])) {
                    // إذا كان هناك عدة أدوية، عرضها جميعاً
                    $drugsDetails = [];
                    foreach ($newValues['drugs'] as $drug) {
                        if (isset($drug['drug_name']) && isset($drug['quantity'])) {
                            $drugsDetails[] = $drug['drug_name'] . ' (الكمية -' . $drug['quantity'] . ')';
                        }
                    }
                    
                    if (!empty($drugsDetails)) {
                        return $translatedAction . ' - الدواء: ' . implode('، ', $drugsDetails);
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to get revert dispense details for audit log', [
                    'log_id' => $log->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // فقط للعمليات المتعلقة بالأدوية في الوصفات
        if ($log->table_name !== 'prescription_drug' && $log->table_name !== 'prescription_drugs') {
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

    /**
     * إضافة رقم الشحنة إلى نوع العملية للطلبات
     * 
     * @param AuditLog $log
     * @param string $operationType
     * @return string
     */
    private function addRequestDetails($log, $operationType)
    {
        // فقط للعمليات المتعلقة بالطلبات
        if ($log->table_name !== 'internal_supply_request' && $log->table_name !== 'external_supply_request') {
            return $operationType;
        }

        $requestNumber = null;

        try {
            // محاولة استخراج request_id من JSON أولاً
            $values = json_decode($log->new_values ?? $log->old_values, true);
            $requestId = null;
            
            if (is_array($values) && isset($values['request_id'])) {
                $requestId = $values['request_id'];
            } elseif ($log->record_id) {
                $requestId = $log->record_id;
            }

            if ($requestId) {
                if ($log->table_name === 'internal_supply_request') {
                    $requestNumber = 'INT-' . $requestId;
                } elseif ($log->table_name === 'external_supply_request') {
                    $requestNumber = 'EXT-' . $requestId;
                }
            }
        } catch (\Exception $e) {
            // في حالة الخطأ، نستمر بدون رقم الشحنة
            \Log::warning('Failed to get request number for audit log', [
                'log_id' => $log->id,
                'error' => $e->getMessage()
            ]);
        }

        // للعمليات المتعلقة بطلبات التوريد الخارجية من مدير المستشفى (قبول/رفض)
        // نعرض فقط: "قبول/رفض طلب توريد خارجي - رقم الشحنة: [رقم]" بدون تفاصيل أخرى
        if ($log->table_name === 'external_supply_request' && 
            in_array($log->action, ['hospital_admin_confirm_external_supply_request', 'hospital_admin_reject_external_supply_request'])) {
            // استخدام النص المترجم مباشرة مع رقم الشحنة فقط (تجاهل أي تفاصيل إضافية من $operationType)
            $baseText = $this->translateAction($log->action);
            if ($requestNumber) {
                return $baseText . ' - رقم الشحنة: ' . $requestNumber;
            }
            return $baseText;
        }

        // بناء النص مع رقم الشحنة للعمليات الأخرى
        if ($requestNumber) {
            return $operationType . ' - رقم الشحنة: ' . $requestNumber;
        }

        // إذا لم يوجد رقم الشحنة، أعد النص الأصلي
        return $operationType;
    }

    /**
     * ترجمة نوع المستخدم إلى العربية
     * 
     * @param string $type
     * @return string
     */
    private function translateUserType($type)
    {
        return match($type) {
            'hospital_admin' => 'مدير نظام المستشفى',
            'supplier_admin' => 'مدير المورد',
            'super_admin' => 'المدير الأعلى',
            'warehouse_manager' => 'مسؤول المخزن',
            'pharmacist' => 'صيدلي',
            'doctor' => 'طبيب',
            'department_head' => 'مدير القسم',
            'data_entry' => 'مدخل بيانات',
            'patient' => 'مريض',
            default => $type,
        };
    }
}
