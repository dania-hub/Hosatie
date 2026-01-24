<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Prescription;
use App\Models\PrescriptionDrug;
use App\Models\Drug;
use Illuminate\Http\Request;

class OperationLogSuperController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = AuditLog::with(['user', 'patientUser', 'hospital']);

        // Filter by Hospital
        if ($request->has('hospital_id') && $request->input('hospital_id') != '') {
            $query->where('hospital_id', $request->input('hospital_id'));
        }

        // Search
        if ($request->has('search') && $request->input('search') != '') {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhereHas('user', function($u) use ($search) {
                      $u->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('patientUser', function($p) use ($search) {
                      $p->where('name', 'like', "%{$search}%")
                        ->orWhere('file_number', 'like', "%{$search}%");
                  });
            });
        }

        $logs = $query->latest()->get();

        $data = $logs->map(function ($log) {
            // Use eager loaded user if available, otherwise find
            $user = $log->user ?? User::find($log->user_id);
            
            $patientName = $this->getPatientName($log);
            
            // Get formatted text (Label + Body)
            $formattedText = $this->formatOperationText($log);

            // Get appropriate file number
            $fileNumber = $this->getFileNumber($log);

            $result = [
                'id'            => $log->id,
                'file_number'   => $fileNumber,
                'employee_name' => $user->full_name ?? ($user->name ?? 'غير معروف'),
                'employee_role' => $this->translateUserType($user->type ?? ''),
                'patient_name'  => $patientName,
                'action_label'  => $formattedText['label'],
                'description'   => $formattedText['body'],
                'operation_type'=> $formattedText['label'] . ' ' . $formattedText['body'], // Fallback
                'date'          => $log->created_at?->format('Y/m/d'),
                'time'          => $log->created_at?->format('H:i'),
                'hospital_name' => $log->hospital ? $log->hospital->name : 'N/A',
                'hospital_id'   => $log->hospital_id,
            ];

            return $result;
        });

        return response()->json($data);
    }

    /**
     * تنسيق نص العملية (العنوان والتفاصيل)
     */
    private function formatOperationText($log)
    {
        $newValues = $log->new_values ? json_decode($log->new_values, true) : [];
        $oldValues = $log->old_values ? json_decode($log->old_values, true) : [];
        $translatedAction = $this->translateAction($log->action);

        // 0. Auth
        if (in_array($log->action, ['login', 'logout'])) {
             $newValues = $log->new_values ? json_decode($log->new_values, true) : [];
             $method = match($newValues['method'] ?? '') {
                 'mobile_app' => 'تطبيق الهاتف',
                 'dashboard' => 'لوحة التحكم',
                 default => ''
             };
             return [
                'label' => $translatedAction,
                'body' => $method ? "عبر $method" : ''
             ];
        }

        // 1. Create Patient
        if ($log->action === 'create_patient') {
            $name = $newValues['full_name'] ?? '-';
            $fileNo = $log->record_id;
            return [
                'label' => 'إضافة',
                'body' => "تم اضافة ملف مريض $name رقم الملف $fileNo"
            ];
        }

        // 2. Update Patient
        if ($log->action === 'update_patient') {
            $changes = $this->getChangesDescription($log);
            return [
                'label' => 'تعديل',
                'body' => $changes ?: "تم تعديل بيانات الملف"
            ];
        }

        // 3. Drugs (Prescription Drugs)
        if (in_array($log->table_name, ['prescription_drug', 'prescription_drugs'])) {
             $drugName = '';
             $qty = '';
             
             // Try to get drug details
             try {
                $prescriptionDrug = PrescriptionDrug::with('drug')->find($log->record_id);
                if ($prescriptionDrug && $prescriptionDrug->drug) {
                    $drugName = $prescriptionDrug->drug->name;
                    $qty = $prescriptionDrug->monthly_quantity;
                } else {
                    // Fallback to JSON
                    if (isset($newValues['drug_id'])) {
                        $drug = Drug::find($newValues['drug_id']);
                        if ($drug) $drugName = $drug->name;
                        $qty = $newValues['monthly_quantity'] ?? '';
                    } elseif (isset($oldValues['drug_id'])) {
                        $drug = Drug::find($oldValues['drug_id']);
                        if ($drug) $drugName = $drug->name;
                        $qty = $oldValues['monthly_quantity'] ?? '';
                    }
                }
             } catch (\Exception $e) {}

             $actionName = 'إضافة دواء للمريض';
             if (str_contains($log->action, 'update') || str_contains($log->action, 'تعديل')) $actionName = 'تعديل دواء للمريض';
             if (str_contains($log->action, 'delete') || str_contains($log->action, 'حذف')) $actionName = 'حذف دواء من المريض';

             return [
                 'label' => "$actionName - الدواء: $drugName",
                 'body' => "الكمية: $qty"
             ];
        }

        // 4. Supply Requests
        if (in_array($log->table_name, ['internal_supply_request', 'external_supply_request'])) {
            $reqId = $log->record_id;
            if (isset($newValues['request_id'])) $reqId = $newValues['request_id'];
            
            $prefix = ($log->table_name === 'internal_supply_request') ? 'INT-' : 'EXT-';
            
            return [
                'label' => $translatedAction,
                'body' => "رقم الشحنة: $prefix$reqId"
            ];
        }

        // 5. Generic Update Logic (Hospitals, Drugs, Users, etc.)
        if (str_contains($log->action, 'update')) {
             $changes = $this->getChangesDescription($log);
             return [
                'label' => $translatedAction,
                'body' => $changes ?: 'تم تحديث السجل'
             ];
        }

        // 6. Generic Create/Delete Logic
        if (str_contains($log->action, 'create')) {
             $name = $newValues['name'] ?? ($newValues['full_name'] ?? '');
             return [
                'label' => $translatedAction,
                'body' => $name ? "تم إضافة: $name" : 'تم إنشاء سجل جديد'
             ];
        }

        // Default
        return [
            'label' => $translatedAction,
            'body' => '' // No details by default
        ];
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
            // مصادقة
            'login' => 'تسجيل دخول',
            'logout' => 'تسجيل خروج',

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
            'رفض طلب توريد داخلي' => 'رفض طلب توريد داخلي',
            
            // عمليات الشحنات الداخلية
            'استلام شحنة' => 'استلام شحنة',
            'pharmacist_confirm_internal_receipt' => 'تأكيد استلام شحنة داخلية',
            'department_confirm_internal_receipt' => 'تأكيد استلام شحنة داخلية (قسم)',
            
            // عمليات طلبات التوريد الخارجية
            'create_external_supply_request' => 'إنشاء طلب توريد خارجي',
          
            'storekeeper_confirm_external_delivery' => 'تأكيد استلام توريد خارجي',
            'supplier_confirm_external_supply_request' => 'تأكيد طلب توريد خارجي (مورد)',
            'supplier_approve_external_supply_request' => 'موافقة مورد على طلب توريد خارجي',
            'supplier_reject_external_supply_request' => 'رفض طلب توريد خارجي (مورد)',
            'supplier_create_external_supply_request' => 'إنشاء طلب توريد (مورد)',
            'supplier_update_external_supply_request' => 'تعديل طلب توريد (مورد)',
            'supplier_cancel_external_supply_request' => 'إلغاء طلب توريد (مورد)',
            'super_admin_confirm_external_supply_request' => 'إرسال شحنة (إدارة)',
            'hospital_admin_reject_external_supply_request' => 'رفض طلب توريد خارجي (مدير مستشفى)',
            'hospital_admin_update_external_supply_request_notes' => 'تحديث ملاحظات طلب توريد خارجي',
            
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

        // بناء النص مع رقم الشحنة
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

    /**
     * Helper to get a string describing what fields changed
     */
    private function getChangesDescription($log)
    {
        // Only relevant for updates
        if (!str_contains($log->action, 'update') && $log->action !== 'update') {
            return '';
        }

        $newValues = json_decode($log->new_values, true);
        if (!$newValues || !is_array($newValues)) {
            return '';
        }

        // Field translations
        $fieldMap = [
            'name' => 'الاسم',
            'full_name' => 'الاسم الكامل',
            'email' => 'البريد الإلكتروني',
            'phone' => 'رقم الهاتف',
            'mobile' => 'رقم الجوال',
            'address' => 'العنوان',
            'status' => 'الحالة',
            'password' => 'كلمة المرور',
            'type' => 'نوع الحساب',
            'role' => 'الصلاحية',
            'license_number' => 'رقم الترخيص',
            'description' => 'الوصف',
            'manufacturer' => 'الشركة المصنعة',
            'price' => 'السعر',
            'quantity' => 'الكمية',
            'current_quantity' => 'الكمية الحالية',
            'is_active' => 'التفعيل',
            'generic_name' => 'الاسم العلمي',
            'strength' => 'التركيز',
            'form' => 'الشكل الصيدلاني',
            'category' => 'الفئة',
            'expiry_date' => 'تاريخ الانتهاء',
            'code' => 'الكود',
            'department_id' => 'القسم',
            'hospital_id' => 'المستشفى',
        ];

        $changedFields = [];
        foreach ($newValues as $key => $val) {
            if (in_array($key, ['updated_at', 'created_at', 'id', 'remember_token', 'password_reset_token'])) continue;
            
            // Password special case
            if ($key === 'password') {
                $changedFields[] = 'كلمة المرور';
                continue;
            }

            $fieldName = $fieldMap[$key] ?? $key;
            $changedFields[] = $fieldName;
        }

        if (empty($changedFields)) {
            return 'تحديث بيانات';
        }
        
        // Return first few changes
        return 'تم تحديث: ' . implode('، ', array_slice($changedFields, 0, 4));
    }
}
