<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Prescription;
use App\Models\PrescriptionDrug;
use App\Models\Drug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientOperationLogController extends BaseApiController
{
    public function index(Request $request)
    {
        // 1. Base Query - Fetch logs performed by THIS user
        $query = AuditLog::where('user_id', Auth::id())
            ->with(['user', 'hospital']) 
            ->latest();

        // 2. Fetch Data (Limit to 500)
        $logs = $query->take(500)->get();

        // 3. Process and Map to View Format
        $data = $logs->map(function ($log) {
            $context = $this->resolveGenericContext($log);
            
            $entity = $context['entity'];
            $formatted = $this->formatOperationText($log, $context);
            
            // Map fields to what frontend expects:
            $fileNumber = $log->record_id;
            $fullName = 'غير معروف';

            if ($entity) {
                // Try to find a display name and ID
                $fileNumber = $entity->file_number ?? ($entity->code ?? $entity->id);
                $fullName = $entity->full_name ?? ($entity->name ?? ($entity->name_ar ?? ($entity->title ?? '-')));
            } elseif ($log->table_name === 'users' && $log->action === 'login') {
                 $user = $log->user;
                 if ($user) {
                     $fileNumber = $user->id;
                     $fullName = $user->full_name;
                 }
            }

            // Fallback for full name if strictly null or placeholder
            if (($fullName === 'غير معروف' || $fullName === '-') && isset($context['inferred_name'])) {
                $fullName = $context['inferred_name'];
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
                'hospital_name'   => $log->hospital ? $log->hospital->name : '-',
                
                // Fields for search
                'searchable_text' => strtolower(
                    $fullName . ' ' . $fileNumber . ' ' . 
                    ($formatted['label'] ?? '') . ' ' . 
                    ($formatted['body'] ?? '') . ' ' .
                    ($log->hospital->name ?? '')
                ),
            ];
        })
        ->values();

        // 4. In-Memory Search
        if ($request->has('search') && $request->input('search') != '') {
            $search = strtolower($request->input('search'));
            $data = $data->filter(function ($item) use ($search) {
                return str_contains($item['searchable_text'], $search);
            })->values();
        }

        return response()->json($data);
    }

    private function resolveGenericContext($log)
    {
        $entity = null;
        $relatedObject = null;
        $newValues = json_decode($log->new_values, true) ?? [];
        $oldValues = json_decode($log->old_values, true) ?? [];
        
        $mergedValues = array_merge($oldValues ?? [], $newValues ?? []);
        $inferredName = null;

        // Helper to infer name from JSON if Entity not found
        if (isset($mergedValues['name'])) $inferredName = $mergedValues['name'];
        if (isset($mergedValues['full_name'])) $inferredName = $mergedValues['full_name'];
        if (isset($mergedValues['title'])) $inferredName = $mergedValues['title'];

        // 1. Specific Entity Loading
        switch ($log->table_name) {
            case 'hospitals':
                $entity = \App\Models\Hospital::find($log->record_id);
                if (!$entity) $entity = new \App\Models\Hospital($mergedValues);
                break;
                
            case 'suppliers':
                $entity = \App\Models\Supplier::find($log->record_id);
                if (!$entity) $entity = new \App\Models\Supplier($mergedValues);
                break;
                
            case 'drugs':
                $entity = \App\Models\Drug::find($log->record_id);
                if (!$entity) $entity = new \App\Models\Drug($mergedValues);
                break;

            case 'internal_supply_requests':
                $entity = \App\Models\InternalSupplyRequest::find($log->record_id);
                // If soft deleted or not found, try to hydrate from old values
                if (!$entity) {
                     $entity = new \App\Models\InternalSupplyRequest($mergedValues);
                     $entity->id = $log->record_id;
                }
                // Supply requests might have a code or ID as main identifier
                $inferredName = 'طلب #' . ($entity->id ?? $log->record_id);
                break;

            case 'external_supply_requests':
            case 'external_supply_request': // Handle singular table name legacy
                $entity = \App\Models\ExternalSupplyRequest::find($log->record_id);
                if (!$entity) {
                     $entity = new \App\Models\ExternalSupplyRequest($mergedValues);
                     $entity->id = $log->record_id;
                }
                $inferredName = 'طلب #' . ($entity->id ?? $log->record_id);
                break;

            case 'users':
                 $user = User::find($log->record_id);
                 if (!$user && isset($mergedValues['full_name'])) {
                      $user = new User($mergedValues);
                      $user->id = $log->record_id;
                 }
                 $entity = $user;
                 break;

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
        }
        
        return ['entity' => $entity, 'object' => $relatedObject, 'inferred_name' => $inferredName];
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
            'login'  => 'تسجيل دخول',
            'logout' => 'تسجيل خروج',
            'drug_expired_zeroed' => 'تصفير كمية دواء منتهية',
        ];
        
        if (isset($map[$log->action])) {
            $label = $map[$log->action];
        }

        // Login/Logout special handling
        if (in_array($log->action, ['login', 'logout'])) {
             $newValues = json_decode($log->new_values, true) ?? [];
             $method = match($newValues['method'] ?? '') {
                 'mobile_app' => 'تطبيق الهاتف',
                 'dashboard' => 'لوحة التحكم',
                 default => ''
             };
             $body = $method ? "عبر $method" : '';
             return ['label' => $label, 'body' => $body];
        }

        // Context-aware Formats
        if ($log->table_name === 'hospitals') {
            $label = 'إدارة المستشفيات';
            $body = $log->action == 'create' ? 'إضافة مستشفى جديد' : ($this->getChangesDescription($log) ?: 'تحديث بيانات مستشفى');
        }
        elseif ($log->table_name === 'suppliers') {
            $label = 'إدارة الموردين';
            $body = $log->action == 'create' ? 'إضافة مورد جديد' : ($this->getChangesDescription($log) ?: 'تحديث بيانات مورد');
        }
        elseif ($log->table_name === 'drugs') {
            $label = 'إدارة الأدوية';
            $body = $log->action == 'create' ? 'إضافة دواء جديد' : ($this->getChangesDescription($log) ?: 'تحديث بيانات دواء');
        }
        // ... (Keep existing logic for users, prescriptions, etc.)
        elseif ($log->table_name === 'users') {
            if ($log->action === 'create_patient') {
                $body = 'تم فتح ملف جديد';
                $label = 'إدارة المرضى'; 
            }
            elseif ($log->action === 'update_patient') {
                $body = 'تم تعديل البيانات الشخصية';
                $label = 'إدارة المرضى';
            }
            else {
                $label = 'إدارة المستخدمين';
                $body = $log->action == 'create' ? 'إضافة مستخدم جديد' : ($this->getChangesDescription($log) ?: 'تحديث بيانات مستخدم');
            }
        }
        
        // ... (Prescriptions logic)
        elseif ($log->table_name === 'prescriptions') {
             $label = 'وصفة طبية';
             $body = $log->action === 'create' ? 'تم إنشاء وصفة طبية' : 'تحديث حالة الوصفة';
        }
        // ... (Drugs in Prescription logic)
        elseif (in_array($log->table_name, ['prescription_drugs', 'prescription_drug'])) {
             $label = 'عملية على الأدوية';
             $pDrug = $context['object'];
             $drugName = '';
             
             if ($pDrug instanceof PrescriptionDrug) {
                 if($pDrug->drug) $drugName = $pDrug->drug->name;
                 else {
                    $d = Drug::find($pDrug->drug_id);
                    if ($d) $drugName = $d->name;
                 }
             }
             if (!$drugName && $log->new_values) {
                 $vals = json_decode($log->new_values, true);
                 if (isset($vals['drug_id'])) {
                      $d = Drug::find($vals['drug_id']);
                      if ($d) $drugName = $d->name;
                 }
             }
             $body = $drugName ? "دواء: {$drugName}" : 'تعديل في الأدوية';
        }
        // Handle Supply Requests (Internal & External)
        elseif (in_array($log->table_name, ['internal_supply_requests', 'external_supply_requests', 'external_supply_request'])) {
            $isExternal = in_array($log->table_name, ['external_supply_requests', 'external_supply_request']);
            $label = $isExternal ? 'طلب توريد خارجي' : 'طلب توريد داخلي';
            
            $statusMap = [
                'pending' => 'معلق',
                'approved' => 'مقبول',
                'rejected' => 'مرفوض',
                'fulfilled' => 'تم الإستلام',
                'completed' => 'مكتمل',
                'cancelled' => 'ملغي',
            ];

            // Specific Super Admin Actions
            if ($log->action === 'super_admin_confirm_external_supply_request') {
                $body = 'تم الإستلام وتحديث المخزون';
            }
            elseif ($log->action === 'super_admin_approve_external_supply_request') {
                $body = 'تم قبول الطلب (قيد الشحن)';
            }
            elseif ($log->action === 'super_admin_reject_external_supply_request') {
                $body = 'تم رفض الطلب';
            }
            // Supplier Actions
            elseif ($log->action === 'supplier_confirm_receipt') {
                $body = 'تم تأكيد الاستلام من قبل المورد';
            }
            elseif ($log->action === 'supplier_create_external_supply_request') {
                $body = 'تم إنشاء طلب جديد من المورد';
            }
            // If it's a creation
            elseif ($log->action === 'create') {
                $body = 'تم إنشاء طلب جديد';
            } 
            // If it's an update
            elseif ($log->action === 'update') {
                $newVals = json_decode($log->new_values, true) ?? [];
                $oldVals = json_decode($log->old_values, true) ?? [];
                
                // Check if status changed
                if (isset($newVals['status']) && isset($oldVals['status']) && $newVals['status'] !== $oldVals['status']) {
                    $newStatus = $statusMap[$newVals['status']] ?? $newVals['status'];
                    $body = "تم تحديث الحالة إلى: $newStatus";
                } else {
                    $body = 'تحديث تفاصيل الطلب';
                }
            } else {
                $body = $log->action;
            }
        }
        
        return ['label' => $label, 'body' => $body];
    }

    private function getChangesDescription($log)
    {
        if ($log->action !== 'update') return '';

        $newValues = json_decode($log->new_values, true);
        $oldValues = json_decode($log->old_values, true); // Decode old values
        
        if (!$newValues || !is_array($newValues)) return '';

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
            'is_active' => 'التفعيل',
            'generic_name' => 'الاسم العلمي',
            'strength' => 'التركيز',
            'supplier_id' => 'رقم المورد',
            'hospital_id' => 'رقم المستشفى',
            'category_id' => 'الفئة',
            'user_id'     => 'المستخدم',
            'code'        => 'الكود',
            'expiry_date' => 'تاريخ الانتهاء',
            'batch_number'=> 'رقم التشغيلة',

            // Added Drug fields
            'unit' => 'الوحدة',
            'max_monthly_dose' => 'الجرعة الشهرية القصوى',
            'country' => 'بلد المنشأ',
            'utilization_type' => 'نوع الاستخدام',
            'warnings' => 'التحذيرات',
            'indications' => 'دواعي الاستعمال',
            'contraindications' => 'موانع الاستعمال',
            'units_per_box' => 'عدد الوحدات في العلبة',
            'form' => 'الشكل الصيدلاني',
            'category' => 'الفئة',
            'department_id' => 'القسم',

            // Added User fields
            'national_id' => 'رقم الهوية الوطنية',
            'warehouse_id' => 'معرف المستودع',
            'pharmacy_id' => 'معرف الصيدلية',
            'fcm_token' => 'رمز FCM',
            'created_by' => 'تم الإنشاء بواسطة',
        ];

        $changedFields = [];
        foreach ($newValues as $key => $val) {
            if (in_array($key, ['updated_at', 'created_at', 'id', 'remember_token'])) continue;
            
            // تحقق من وجود اختلاف بين القيمة الجديدة والقديمة
            $oldVal = $oldValues[$key] ?? null;
            if ($val == $oldVal) continue;
            
            // Password special case
            if ($key === 'password') {
                $changedFields[] = 'كلمة المرور';
                continue;
            }

            $fieldName = $fieldMap[$key] ?? $key;
            $changedFields[] = $fieldName;
        }

        if (empty($changedFields)) return '';
        
        return 'تم تحديث: ' . implode('، ', array_slice($changedFields, 0, 3));
    }
}
