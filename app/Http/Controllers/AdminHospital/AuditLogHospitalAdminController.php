<?php

namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Drug;
use App\Models\Department;
use App\Models\Complaint;

class AuditLogHospitalAdminController extends BaseApiController
{
    // GET /api/hospitaladmin/operations
    public function index(Request $request)
    {
        $user = $request->user();

        // فقط مدير المستشفى
        if ($user->type !== 'hospital_admin') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        // الحصول على جميع العمليات التي قام بها هذا المستخدم فقط
        // نستخدم استعلام أبسط وأوضح
        $logs = AuditLog::where('user_id', $user->id)
            ->where('hospital_id', $user->hospital_id)
            ->where(function($query) {
                // 1. جميع العمليات على الجداول المرتبطة بمدير المستشفى
                $query->whereIn('table_name', [
                    'drugs',
                    'departments',
                    'users',
                    'complaints',
                    'external_supply_request',
                    'external_supply_requests',
                    'patient_transfer_requests',
                ]);
                // 2. أو أي عملية تحتوي على hospital_admin في action
                $query->orWhere('action', 'like', '%hospital_admin%');
                // 3. أو العمليات المتعلقة بالموظفين والأقسام والأدوية والشكاوى والطلبات
                $query->orWhere(function($q) {
                    $q->where('action', 'like', '%موظف%')
                      ->orWhere('action', 'like', '%staff%')
                      ->orWhere('action', 'like', '%قسم%')
                      ->orWhere('action', 'like', '%department%')
                      ->orWhere('action', 'like', '%دواء%')
                      ->orWhere('action', 'like', '%drug%')
                      ->orWhere('action', 'like', '%شكوى%')
                      ->orWhere('action', 'like', '%complaint%')
                      ->orWhere('action', 'like', '%رد%')
                      ->orWhere('action', 'like', '%reply%')
                      ->orWhere('action', 'like', '%طلب%')
                      ->orWhere('action', 'like', '%توريد%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // فلترة إضافية: استبعاد عمليات المرضى فقط، والاحتفاظ بباقي العمليات
        $logs = $logs->filter(function($log) {
            try {
                // استبعاد عمليات المرضى فقط
                if ($log->table_name === 'users') {
                    // محاولة التحقق من نوع المستخدم من JSON
                    $values = json_decode($log->new_values ?? $log->old_values ?? '{}', true);
                    if (is_array($values) && isset($values['type'])) {
                        // استبعاد المرضى فقط
                        if ($values['type'] === 'patient') {
                            return false;
                        }
                    }
                    // إذا لم نجد في JSON، جرب البحث في قاعدة البيانات
                    if ($log->record_id) {
                        $targetUser = User::find($log->record_id);
                        if ($targetUser && $targetUser->type === 'patient') {
                            return false;
                        }
                    }
                }
                
                // استبعاد العمليات التي لا علاقة لها بمدير المستشفى
                // (مثل عمليات الصرف للأدوية للمرضى)
                if (in_array($log->table_name, ['prescription_drug', 'prescription_drugs', 'prescription', 'dispensings'])) {
                    return false;
                }
                
                // الاحتفاظ بجميع العمليات الأخرى
                return true;
            } catch (\Exception $e) {
                // في حالة حدوث خطأ، نستمر ونحتفظ بالسجل
                \Log::warning('Error filtering audit log', [
                    'log_id' => $log->id ?? null,
                    'error' => $e->getMessage()
                ]);
                return true;
            }
        });

        // تحويلها إلى الفورمات الذي تتوقعه الواجهة
        $data = $logs->map(function ($log) {
            try {
                $translatedAction = $this->translateAction($log->action, $log->table_name, $log);
                $operationDetails = $this->getOperationDetails($log);
                
                // دمج نوع العملية مع التفاصيل
                $operationType = $operationDetails 
                    ? $translatedAction . ' - ' . $operationDetails
                    : $translatedAction;
                
                return [
                    'fileNumber'    => $log->id ?? 0,          // معرف العملية
                    'operationType' => $operationType ?? 'عملية غير معروفة',    // نوع العملية مع التفاصيل
                    'operationDate' => $log->created_at   // تاريخ العملية بصيغة YYYY/MM/DD
                        ? $log->created_at->format('Y/m/d')
                        : date('Y/m/d'),
                ];
            } catch (\Exception $e) {
                \Log::warning('Error processing audit log', [
                    'log_id' => $log->id ?? null,
                    'error' => $e->getMessage()
                ]);
                return [
                    'fileNumber'    => $log->id ?? 0,
                    'operationType' => 'خطأ في معالجة العملية',
                    'operationDate' => $log->created_at ? $log->created_at->format('Y/m/d') : date('Y/m/d'),
                ];
            }
        })->values(); // values() لإعادة ترقيم المفاتيح

        return response()->json($data);
    }

    /**
     * قائمة العمليات المحددة لمدير المستشفى
     */
    private function getHospitalAdminActions()
    {
        return [
            'hospital_admin_reject_external_supply_request',
            'hospital_admin_update_external_supply_request_notes',
            'hospital_admin_confirm_external_supply_request',
            'hospital_admin_add_staff',
            'hospital_admin_update_staff',
            'hospital_admin_toggle_staff_status',
            'hospital_admin_add_department',
            'hospital_admin_update_department',
            'hospital_admin_toggle_department_status',
            'hospital_admin_add_drug',
            'hospital_admin_update_drug',
            'hospital_admin_delete_drug',
            'hospital_admin_reply_complaint',
            'hospital_admin_accept_request',
            'hospital_admin_reject_request',
        ];
    }

    /**
     * قائمة الجداول المرتبطة بمدير المستشفى
     */
    private function getHospitalAdminTables()
    {
        return [
            'users',              // للموظفين
            'departments',        // للأقسام
            'drugs',              // للأدوية
            'complaints',         // للشكاوى
            'external_supply_request', // لطلبات التوريد الخارجية
            'external_supply_requests', // لطلبات التوريد الخارجية (صيغة الجمع)
            'patient_transfer_requests', // لطلبات النقل
        ];
    }

    /**
     * ترجمة نوع العملية إلى العربية مع التفاصيل
     */
    private function translateAction($action, $tableName = null, $log = null)
    {
        // معالجة العمليات الخاصة بمدير المستشفى
        $translations = [
            // عمليات الأدوية
            'إضافة دواء' => 'إضافة دواء',
            'تعديل دواء' => 'تعديل دواء',
            'حذف دواء' => 'حذف دواء',
            'hospital_admin_add_drug' => 'إضافة دواء',
            'hospital_admin_update_drug' => 'تعديل دواء',
            'hospital_admin_delete_drug' => 'حذف دواء',
            'create' => $tableName === 'drugs' ? 'إضافة دواء' : 'إنشاء',
            'update' => $tableName === 'drugs' ? 'تعديل دواء' : 'تعديل',
            'delete' => $tableName === 'drugs' ? 'حذف دواء' : 'حذف',
            
            // عمليات الموظفين
            'hospital_admin_add_staff' => 'إضافة موظف',
            'hospital_admin_update_staff' => 'تعديل موظف',
            'hospital_admin_toggle_staff_status' => 'تغيير حالة موظف',
            'إضافة موظف' => 'إضافة موظف',
            'تعديل موظف' => 'تعديل موظف',
            'تفعيل موظف' => 'تفعيل موظف',
            'تعطيل موظف' => 'تعطيل موظف',
            
            // عمليات الأقسام
            'hospital_admin_add_department' => 'إضافة قسم',
            'hospital_admin_update_department' => 'تعديل قسم',
            'hospital_admin_toggle_department_status' => 'تغيير حالة قسم',
            'إضافة قسم' => 'إضافة قسم',
            'تعديل قسم' => 'تعديل قسم',
            'تفعيل قسم' => 'تفعيل قسم',
            'تعطيل قسم' => 'تعطيل قسم',
            
            // عمليات طلبات التوريد الخارجية
            'hospital_admin_reject_external_supply_request' => 'رفض طلب توريد خارجي',
            'hospital_admin_confirm_external_supply_request' => 'قبول طلب توريد خارجي',
            'hospital_admin_update_external_supply_request_notes' => 'تحديث ملاحظات طلب توريد خارجي',
            
            // عمليات الشكاوى
            'hospital_admin_reply_complaint' => 'الرد على شكوى',
            'الرد على شكوى' => 'الرد على شكوى',
            'رد على شكوى' => 'الرد على شكوى',
            
            // عمليات الطلبات العامة
            'hospital_admin_accept_request' => 'قبول طلب',
            'hospital_admin_reject_request' => 'رفض طلب',
            'قبول طلب' => 'قبول طلب',
            'رفض طلب' => 'رفض طلب',
        ];

        // إذا كانت الترجمة موجودة، استخدمها
        if (isset($translations[$action])) {
            return $translations[$action];
        }

        // محاولة ترجمة حسب اسم الجدول إذا كان action عام
        if ($tableName === 'users' && in_array($action, ['create', 'update', 'delete'])) {
            // محاولة تحديد إذا كانت العملية تفعيل أو تعطيل
            if ($action === 'update' && $log) {
                $oldValues = json_decode($log->old_values ?? '{}', true);
                $newValues = json_decode($log->new_values ?? '{}', true);
                
                if (is_array($oldValues) && is_array($newValues)) {
                    $oldStatus = $oldValues['status'] ?? $oldValues['isActive'] ?? null;
                    $newStatus = $newValues['status'] ?? $newValues['isActive'] ?? null;
                    $userType = $newValues['type'] ?? $oldValues['type'] ?? null;
                    
                    // تأكد من أن المستخدم ليس مريضاً
                    if ($userType !== 'patient') {
                        if (($oldStatus === 'active' || $oldStatus === true) && ($newStatus === 'inactive' || $newStatus === false)) {
                            return 'تعطيل موظف';
                        } elseif (($oldStatus === 'inactive' || $oldStatus === false || $oldStatus === 'pending_activation') && ($newStatus === 'active' || $newStatus === true)) {
                            return 'تفعيل موظف';
                        }
                    }
                }
            }
            
            $userType = $this->getUserTypeFromLog($log);
            if ($userType && $userType !== 'patient') {
                return match($action) {
                    'create' => 'إضافة موظف',
                    'update' => 'تعديل موظف',
                    'delete' => 'حذف موظف',
                    default => $action,
                };
            }
        }

        if ($tableName === 'departments') {
            // محاولة تحديد إذا كانت العملية تفعيل أو تعطيل
            if ($action === 'update' && $log) {
                $oldValues = json_decode($log->old_values ?? '{}', true);
                $newValues = json_decode($log->new_values ?? '{}', true);
                
                if (is_array($oldValues) && is_array($newValues)) {
                    $oldStatus = $oldValues['status'] ?? null;
                    $newStatus = $newValues['status'] ?? null;
                    
                    if ($oldStatus === 'active' && $newStatus === 'inactive') {
                        return 'تعطيل قسم';
                    } elseif ($oldStatus === 'inactive' && $newStatus === 'active') {
                        return 'تفعيل قسم';
                    }
                }
            }
            
            return match($action) {
                'create' => 'إضافة قسم',
                'update' => 'تعديل قسم',
                'delete' => 'حذف قسم',
                default => $action,
            };
        }

        if ($tableName === 'complaints') {
            return match($action) {
                'reply' => 'الرد على شكوى',
                'respond' => 'الرد على شكوى',
                'update' => 'تعديل شكوى',
                default => $action,
            };
        }

        // التحقق من إذا كانت النص يحتوي على أحرف عربية
        $arabicChars = preg_match_all('/[\x{0600}-\x{06FF}]/u', $action);
        $totalChars = mb_strlen($action);
        if ($totalChars > 0 && ($arabicChars / $totalChars) > 0.3) {
            return $action;
        }
        
        return $action;
    }

    /**
     * الحصول على تفاصيل العملية (اسم الدواء، اسم الموظف، اسم القسم، إلخ)
     */
    private function getOperationDetails($log)
    {
        if (!$log->table_name || !$log->record_id) {
            return null;
        }

        try {
            switch ($log->table_name) {
                case 'drugs':
                    $drug = Drug::find($log->record_id);
                    return $drug ? $drug->name : null;

                case 'users':
                    // محاولة استخراج معلومات الموظف من JSON أولاً
                    $values = json_decode($log->new_values ?? $log->old_values, true);
                    if (is_array($values) && isset($values['type']) && $values['type'] !== 'patient') {
                        return $values['full_name'] ?? null;
                    }
                    // إذا لم نجد في JSON، جرب البحث في قاعدة البيانات
                    $user = User::find($log->record_id);
                    if ($user && $user->type !== 'patient') {
                        return $user->full_name ?? null;
                    }
                    return null;

                case 'departments':
                    $department = Department::find($log->record_id);
                    return $department ? $department->name : null;

                case 'external_supply_request':
                case 'external_supply_requests':
                    // محاولة استخراج رقم الطلب
                    $values = json_decode($log->new_values ?? $log->old_values, true);
                    if (is_array($values) && isset($values['request_id'])) {
                        return 'EXT-' . $values['request_id'];
                    }
                    return $log->record_id ? 'EXT-' . $log->record_id : null;

                case 'complaints':
                    // محاولة استخراج رقم الملف أو اسم المريض
                    $complaint = Complaint::with('patient')->find($log->record_id);
                    if ($complaint && $complaint->patient) {
                        return 'FILE-' . $complaint->patient_id;
                    }
                    return null;

                default:
                    return null;
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to get operation details for audit log', [
                'log_id' => $log->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * محاولة استخراج نوع المستخدم من السجل
     */
    private function getUserTypeFromLog($log = null)
    {
        if (!$log) {
            return null;
        }
        
        // محاولة استخراج نوع المستخدم من JSON
        $values = json_decode($log->new_values ?? $log->old_values ?? '{}', true);
        if (is_array($values) && isset($values['type'])) {
            return $values['type'];
        }
        
        // محاولة البحث في قاعدة البيانات
        if ($log->record_id) {
            $user = User::find($log->record_id);
            if ($user) {
                return $user->type;
            }
        }
        
        return null;
    }
}

