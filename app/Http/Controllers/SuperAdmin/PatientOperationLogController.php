<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Prescription;
use App\Models\PrescriptionDrug;
use App\Models\Drug;
use Illuminate\Http\Request;

class PatientOperationLogController extends BaseApiController
{
    public function index(Request $request)
    {
        // 1. Define tables
        $tables = [
            'users', 
            'prescriptions', 
            'prescription_drug', 
            'prescription_drugs', 
            'dispensings',
            'hospitals',
            'suppliers',
            'drugs',
            'inventories',
            'internal_supply_request',  // superAdmin/requests (طلبات التوريد الداخلية من المورد)
        ];

        // 2. Base Query - Fetch logs for these tables, performed by THIS user
        $query = AuditLog::whereIn('table_name', $tables)
            ->where('user_id', \Illuminate\Support\Facades\Auth::id())
            ->with(['user', 'hospital']) 
            ->latest();

        // 3. Fetch Data (Limit to 300 to ensure performance)
        $logs = $query->take(300)->get();

        // 4. Process and Map to View Format
        $data = $logs->map(function ($log) {
            $context = $this->resolveGenericContext($log);
            
            // "Patient" here is a metaphor for the target entity
            $entity = $context['patient'];
            $formatted = $this->formatOperationText($log, $context);
            
            if (!$entity && !$formatted['label']) {
                 // Skip if we cant identify meaningful info
                 return null;
            }

            // Map fields to what frontend expects:
            // file_number -> ID
            // full_name -> Name of Entity (Hospital Name, Drug Name...)
            
            $fileNumber = $log->record_id;
            $fullName = 'غير معروف';

            if ($entity) {
                $fileNumber = $entity->file_number ?? $entity->id;
                $fullName = $entity->full_name ?? ($entity->name ?? $entity->name_ar ?? 'غير معروف');
            }

            return [
                'id'              => $log->id,
                'file_number'     => $fileNumber,
                'full_name'       => $fullName,
                'operation_label' => $formatted['label'],
                'operation_body'  => $formatted['body'],
                'operation_type'  => $formatted['label'],
                'date'            => $log->created_at->format('Y/m/d'),
                'time'            => $log->created_at->format('H:i'),
                'hospital_name'   => $log->hospital ? $log->hospital->name : 'N/A',
                
                // Fields for search
                'searchable_text' => strtolower(
                    $fullName . ' ' . $fileNumber . ' ' . 
                    ($formatted['label'] ?? '') . ' ' . 
                    ($formatted['body'] ?? '') . ' ' .
                    ($log->hospital->name ?? '')
                ),
            ];
        })
        ->filter() // Remove nulls
        ->values();

        // 5. In-Memory Search (Since data volume is capped by take(300))
        if ($request->has('search') && $request->input('search') != '') {
            $search = strtolower($request->input('search'));
            $data = $data->filter(function ($item) use ($search) {
                return str_contains($item['searchable_text'], $search);
            })->values();
        }

        return response()->json($data);
    }

    /**
     * Resolves the patient and related object based on log table and record_id
     */
    private function resolveGenericContext($log)
    {
        $entity = null; // Renamed from $patient for clarity, but logic remains similar
        $relatedObject = null;
        $newValues = json_decode($log->new_values, true) ?? [];
        $oldValues = json_decode($log->old_values, true) ?? [];
        
        $mergedValues = array_merge($oldValues ?? [], $newValues ?? []);

        // 1. Specific Entity Loading
        switch ($log->table_name) {
            case 'hospitals':
                $entity = \App\Models\Hospital::find($log->record_id);
                if (!$entity && isset($mergedValues['name'])) {
                     $entity = new \App\Models\Hospital($mergedValues); // Virtual
                     $entity->id = $log->record_id;
                }
                break;
                
            case 'suppliers':
                $entity = \App\Models\Supplier::find($log->record_id);
                if (!$entity && isset($mergedValues['name'])) {
                     $entity = new \App\Models\Supplier($mergedValues);
                     $entity->id = $log->record_id;
                }
                break;
                
            case 'drugs':
                $entity = \App\Models\Drug::find($log->record_id);
                if (!$entity && isset($mergedValues['name'])) {
                     $entity = new \App\Models\Drug($mergedValues);
                     $entity->id = $log->record_id;
                }
                break;

            case 'users':
                 $user = User::find($log->record_id);
                 if (!$user) {
                      // Check JSON
                      if (isset($mergedValues['full_name'])) {
                          $user = new User($mergedValues);
                          $user->id = $log->record_id;
                      }
                 }
                 $entity = $user;
                 break;

            // ... Existing cases for patient stuff ...
             case 'prescriptions':
                $prescription = Prescription::with('patient')->find($log->record_id);
                if ($prescription) {
                    $entity = $prescription->patient;
                    $relatedObject = $prescription;
                }
                break;
            case 'prescription_drugs':
            case 'prescription_drug': 
                $pDrug = PrescriptionDrug::with('prescription.patient')->find($log->record_id);
                if ($pDrug && $pDrug->prescription) {
                    $entity = $pDrug->prescription->patient;
                    $relatedObject = $pDrug;
                }
                break;
            case 'dispensings':
                $dispensing = \App\Models\Dispensing::with('patient')->find($log->record_id);
                if ($dispensing) {
                    $entity = $dispensing->patient;
                    $relatedObject = $dispensing;
                }
                break;

            case 'internal_supply_request':
                $req = \App\Models\InternalSupplyRequest::with('supplier')->find($log->record_id);
                if ($req) {
                    $entity = (object)[
                        'id' => $req->id,
                        'file_number' => 'INT-' . $req->id,
                        'full_name' => $req->supplier ? $req->supplier->name : ('طلب توريد داخلي #' . $req->id),
                        'name' => $req->supplier ? $req->supplier->name : ('طلب #' . $req->id),
                    ];
                    $relatedObject = $req;
                }
                break;
        }
        
        // Return structured as 'patient' because index method expects it, 
        // effectively 'patient' now means 'Target Entity'
        return ['patient' => $entity, 'object' => $relatedObject];
    }

    private function formatOperationText($log, $context)
    {
        $label = $log->action;
        $body = '';

        // Translations map
        $map = [
            'create_patient' => 'إضافة مريض',
            'update_patient' => 'تعديل بيانات مريض',
            'delete_patient' => 'حذف مريض',
            'create' => 'إضافة',
            'update' => 'تعديل',
            'delete' => 'حذف',
            'drug_expired_zeroed' => 'تصفير كمية دواء منتهية',
            // superAdmin/requests (طلبات التوريد الداخلية)
            'super_admin_approve_internal_supply_request' => 'موافقة إدارة على طلب توريد داخلي',
            'super_admin_reject_internal_supply_request' => 'رفض إدارة لطلب توريد داخلي',
            'super_admin_confirm_internal_supply_request' => 'تأكيد إرسال طلب توريد داخلي',
        ];
        
        if (isset($map[$log->action])) {
            $label = $map[$log->action];
        }

        // Context-aware Formats
        if ($log->table_name === 'hospitals') {
            $label = 'إدارة المستشفيات';
            $hospitalName = '';
            if ($context['patient']) {
                $hospitalName = $context['patient']->name ?? '';
            }

            $actionVerb = match($log->action) {
                'create', 'created' => 'إضافة مستشفى جديد',
                'update', 'updated' => 'تعديل بيانات المستشفى',
                'delete', 'deleted' => 'حذف المستشفى',
                'deactivate' => 'تعطيل المستشفى',
                'activate' => 'تفعيل المستشفى',
                default => $translatedAction ?? $log->action
            };

            if ($log->action == 'create') {
                $body = $actionVerb . ($hospitalName ? ": $hospitalName" : "");
            } else {
                $changes = $this->getChangesDescription($log);
                $body = "$actionVerb: " . ($hospitalName ?: "مستشفى #{$log->record_id}") . ($changes ? " ($changes)" : "");
            } 
        }
        if ($log->table_name === 'suppliers') {
            $label = 'إدارة الموردين';
            if ($log->action == 'create') {
                $body = 'إضافة مورد جديد';
            } else {
                $changes = $this->getChangesDescription($log);
                $body = $changes ?: 'تحديث بيانات مورد';
            }
        }
        if ($log->table_name === 'drugs') {
            $label = 'إدارة الأدوية';
            if ($log->action == 'create') {
                $body = 'إضافة دواء جديد';
            } else {
                $changes = $this->getChangesDescription($log);
                $body = $changes ?: 'تحديث بيانات دواء';
            }
        }

        if ($log->table_name === 'users') {
            if ($log->action === 'create_patient') $body = 'تم فتح ملف جديد';
            elseif ($log->action === 'update_patient') $body = 'تم تعديل البيانات الشخصية';
            else {
                $label = 'إدارة المستخدمين';
                if ($log->action == 'create') {
                     $body = 'إضافة مستخدم جديد';
                } else {
                     $changes = $this->getChangesDescription($log);
                     
                     // Improve status change visibility
                     if ($changes === 'تعطيل الحساب') {
                         $body = 'تعطيل الحساب';
                     } elseif ($changes === 'تفعيل الحساب') {
                         $body = 'تفعيل الحساب';
                     } else {
                         $body = $changes ?: 'تحديث بيانات مستخدم';
                     }
                }
            }
        }
        
        if ($log->table_name === 'prescriptions') {
             $label = 'وصفة طبية';
             $body = $log->action === 'create' ? 'تم إنشاء وصفة طبية' : 'تحديث حالة الوصفة';
        }
        
        if (in_array($log->table_name, ['prescription_drugs', 'prescription_drug'])) {
             $label = 'عملية على الأدوية';
             // Try to get drug name from new values or the object
             $pDrug = $context['object'];
             $drugName = '';
             
             if ($pDrug instanceof PrescriptionDrug) {
                 // Check if relation loaded
                 if($pDrug->drug) {
                    $drugName = $pDrug->drug->name;
                 } else {
                    $d = Drug::find($pDrug->drug_id);
                    if ($d) $drugName = $d->name;
                 }
             }

             // If object is missing or drug not found, check json
             if (!$drugName && $log->new_values) {
                 $vals = json_decode($log->new_values, true);
                 if (isset($vals['drug_id'])) {
                      $d = Drug::find($vals['drug_id']);
                      if ($d) $drugName = $d->name;
                 }
             }

             $body = $drugName ? "دواء: {$drugName}" : 'تعديل في الأدوية';
        }

        // superAdmin/requests: طلبات التوريد الداخلية (internal_supply_request)
        if ($log->table_name === 'internal_supply_request') {
            if (isset($map[$log->action])) {
                $label = $map[$log->action];
            }
            $reqId = $log->record_id ?? (json_decode($log->new_values, true)['request_id'] ?? null);
            $body = $reqId ? 'رقم الطلب: INT-' . $reqId : '';
        }
        
        return ['label' => $label, 'body' => $body];
    }

    private function getChangesDescription($log)
    {
        if ($log->action !== 'update' && !str_contains($log->action, 'update')) return '';

        $newValues = json_decode($log->new_values, true);
        if (!$newValues || !is_array($newValues)) return '';

        // Handle specific status changes for better clarity
        if (count($newValues) === 1 || (count($newValues) === 2 && isset($newValues['updated_at']))) {
            if (isset($newValues['status']) || isset($newValues['is_active'])) {
                $key = isset($newValues['status']) ? 'status' : 'is_active';
                $newStatus = $newValues[$key];
                
                $isHospital = $log->table_name === 'hospitals';

                if ($newStatus == 1 || $newStatus === 'active' || $newStatus === true) {
                    return $isHospital ? 'تفعيل المؤسسة' : 'تفعيل الحساب';
                }
                if ($newStatus == 0 || $newStatus === 'inactive' || $newStatus === false) {
                    return $isHospital ? 'تعطيل المؤسسة' : 'تعطيل الحساب';
                }
            }
        }

        $fieldMap = [
            'name' => 'الاسم',
            'full_name' => 'الاسم الكامل',
            'email' => 'البريد الإلكتروني',
            'phone' => 'رقم الهاتف',
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
            'strength' => 'القوة/التركيز',
            'form' => 'الشكل الصيدلاني',
            'category' => 'الفئة العلاجية',
            'category_id' => 'الفئة العلاجية',
            'city' => 'المدينة',
            'manager_id' => 'المدير المسؤول',
            'supplier_id' => 'المورد المسؤول',
            'indications' => 'دواعي الاستعمال',
            'warnings' => 'التحذيرات',
            'contraindications' => 'موانع الاستعمال',
        ];
        $changedFields = [];
        foreach ($newValues as $key => $val) {
            if (in_array($key, ['updated_at', 'created_at', 'id', 'remember_token'])) continue;
            
            // Password special case
            if ($key === 'password') {
                $changedFields[] = 'كلمة المرور';
                continue;
            }

            $fieldName = $fieldMap[$key] ?? $key;
            
            // Customize status for hospitals in multi-field updates
            if (($key === 'status' || $key === 'is_active') && $log->table_name === 'hospitals') {
                if ($val == 1 || $val === 'active' || $val === true) $fieldName = 'تفعيل المؤسسة';
                elseif ($val == 0 || $val === 'inactive' || $val === false) $fieldName = 'تعطيل المؤسسة';
                $changedFields[] = $fieldName;
                continue;
            }

            // Show current value
            $displayVal = $val;
            if (is_scalar($val) && $key !== 'password') {
                if ($key === 'supplier_id' && $val) {
                    $supplier = \App\Models\Supplier::find($val);
                    $displayVal = $supplier ? $supplier->name : $val;
                } elseif ($key === 'manager_id' && $val) {
                    $manager = User::find($val);
                    $displayVal = $manager ? ($manager->full_name ?? $manager->name) : $val;
                } elseif ($key === 'hospital_id' && $val) {
                    $hosp = \App\Models\Hospital::find($val);
                    $displayVal = $hosp ? $hosp->name : $val;
                } elseif ($key === 'pharmacy_id' && $val) {
                    $pharmacy = \App\Models\Pharmacy::find($val);
                    $displayVal = $pharmacy ? $pharmacy->name : $val;
                } elseif ($key === 'warehouse_id' && $val) {
                    $warehouse = \App\Models\Warehouse::find($val);
                    $displayVal = $warehouse ? $warehouse->name : $val;
                } elseif ($val === true) {
                    $displayVal = 'نعم';
                } elseif ($val === false) {
                    $displayVal = 'لا';
                }

                $changedFields[] = "$fieldName: ($displayVal)";
            } else {
                $changedFields[] = $fieldName;
            }
        }

        if (empty($changedFields)) return '';
        
        // Return first 3 changes
        return 'تم تحديث: ' . implode('، ', array_slice($changedFields, 0, 3));
    }

    private function toArabicNumerals($string)
    {
        return (string)$string;
    }
}
