<?php

namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Drug;
use App\Models\Department;
use App\Models\Complaint;
use App\Models\PatientTransferRequest;
use App\Models\ExternalSupplyRequest;
use App\Models\ExternalSupplyRequestItem;
use App\Models\Supplier;
use App\Models\Hospital;

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
                // للعمليات المتعلقة بطلبات التوريد الخارجية من مدير المستشفى (قبول/رفض)
                // نعرض فقط: "قبول/رفض طلب توريد خارجي - رقم الشحنة: [رقم]" بدون تفاصيل أخرى
                if ($log->table_name === 'external_supply_request' && 
                    in_array($log->action, ['hospital_admin_confirm_external_supply_request', 'hospital_admin_reject_external_supply_request'])) {
                    // استخدام رقم الشحنة فقط من operationDetails
                    $operationType = $operationDetails 
                        ? $translatedAction . ' - رقم الشحنة: ' . $operationDetails
                        : $translatedAction;
                } else {
                    // للعمليات الأخرى، نعرض التفاصيل الكاملة
                    $operationType = $operationDetails 
                        ? $translatedAction . ' - ' . $operationDetails
                        : $translatedAction;
                }
                
                // جلب اسم الموظف/المريض
                $targetName = $this->getTargetName($log);
                
                return [
                    'fileNumber'    => $log->id ?? 0,          // معرف العملية
                    'operationType' => $operationType ?? 'عملية غير معروفة',    // نوع العملية مع التفاصيل
                    'targetName'    => $targetName,            // اسم الموظف/المريض
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
                    'targetName'    => '-',
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
            
            // عمليات طلبات نقل المريض
            'preapprove' => 'الموافقة الأولية على نقل مريض',
            'approve' => 'الموافقة على نقل مريض',
            'reject' => 'رفض طلب',

            // عمليات عامة
            'create' => 'إنشاء',
            'update' => 'تعديل',
            'delete' => 'حذف',
            'login' => 'تسجيل دخول',
            'logout' => 'تسجيل خروج',
            'password_change' => 'تغيير كلمة المرور',

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

        if ($tableName === 'patient_transfer_requests') {
            return match($action) {
                'create' => 'إنشاء طلب نقل مريض',
                'preapprove' => 'الموافقة الأولية على نقل مريض',
                'approve' => 'الموافقة على نقل مريض',
                'reject' => 'رفض طلب نقل مريض',
                'update' => 'تعديل طلب نقل مريض',
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
    public function getOperationDetails($log)
    {
        if (!$log->table_name || !$log->record_id) {
            return null;
        }

        try {
            switch ($log->table_name) {
                case 'drugs':
                    // جلب تفاصيل الدواء
                    $drug = Drug::find($log->record_id);
                    if ($drug) {
                        $details = [];
                        $details[] = $drug->name;
                        
                        // إضافة الفئة
                        if ($drug->category) {
                            $details[] = 'الفئة: ' . $drug->category;
                        }
                        
                        // إضافة القوة
                        if ($drug->strength) {
                            $details[] = 'القوة: ' . $drug->strength;
                        }
                        
                        // إضافة الحالة
                        if ($drug->status) {
                            $details[] = 'الحالة: ' . $drug->status;
                        }
                        
                        return implode(' - ', $details);
                    }
                    return null;

                case 'users':
                    // محاولة استخراج الحقول التي تغيرت لمقارنتها
                    $oldValues = json_decode($log->old_values ?? '{}', true);
                    $newValues = json_decode($log->new_values ?? '{}', true);

                    if (!is_array($oldValues) || !is_array($newValues)) {
                        return null;
                    }

                    $changes = [];
                    $fieldNames = [
                        'full_name' => 'الاسم',
                        'phone' => 'رقم الهاتف',
                        'email' => 'البريد الإلكتروني',
                        'birth_date' => 'تاريخ الميلاد',
                        'national_id' => 'الرقم الوطني',
                        'status' => 'الحالة',
                        'password' => 'كلمة المرور',
                    ];

                    foreach ($newValues as $field => $newValue) {
                        // التحقق من الحقول التي تغيرت وموجودة في القائمة المسموح بها
                        if (array_key_exists($field, $oldValues) && $oldValues[$field] != $newValue && isset($fieldNames[$field])) {
                            // تنسيق خاص لبعض الحقول
                            if ($field === 'birth_date' && $newValue) {
                                try {
                                    $newValue = \Carbon\Carbon::parse($newValue)->format('Y/m/d');
                                } catch (\Exception $e) {
                                    // في حالة الفشل نترك القيمة كما هي
                                }
                            }
                            
                            if ($field === 'password') {
                                $changes[] = "تعديل كلمة المرور";
                            } else {
                                $changes[] = "تعديل " . $fieldNames[$field] . " إلى: " . $newValue;
                            }
                        }
                    }

                    if (count($changes) > 0) {
                        return implode(' و ', $changes);
                    }
                    return null;

                case 'departments':
                    // محاولة استخراج الحقول التي تغيرت لمقارنتها
                    $oldValues = json_decode($log->old_values ?? '{}', true);
                    $newValues = json_decode($log->new_values ?? '{}', true);

                    if (is_array($oldValues) && is_array($newValues) && count($newValues) > 0) {
                        $changes = [];
                        
                        // 1. تغيير الاسم
                        if (isset($newValues['name']) && isset($oldValues['name']) && $newValues['name'] != $oldValues['name']) {
                            $changes[] = "تعديل الاسم إلى: " . $newValues['name'];
                        }

                        // 2. تغيير المدير
                        $oldHeadId = $oldValues['head_user_id'] ?? null;
                        $newHeadId = $newValues['head_user_id'] ?? null;
                        
                        if ($oldHeadId != $newHeadId) {
                            $oldName = $oldValues['manager_name'] ?? '-';
                            $newName = $newValues['manager_name'] ?? '-';
                            
                            if (!$oldHeadId && $newHeadId) {
                                $changes[] = "تعيين مدير جديد: " . $newName;
                            } elseif ($oldHeadId && !$newHeadId) {
                                $changes[] = "إلغاء المدير السابق: " . $oldName;
                            } else {
                                $changes[] = "تغيير المدير من " . $oldName . " إلى " . $newName;
                            }
                        }

                        if (count($changes) > 0) {
                            return implode(' و ', $changes);
                        }
                    }

                    // في حالة عدم وجود تغييرات محددة أو سجل قديم (مثل الإضافة)، نعرض الحالة الحالية
                    $department = Department::with(['head'])->find($log->record_id);
                    if ($department) {
                        $details = [];
                        $details[] = $department->name;
                        
                        // إضافة اسم المدير إن وجد
                        if ($department->head) {
                            $details[] = 'المدير: ' . $department->head->full_name;
                        }
                        
                        return implode(' - ', $details);
                    }
                    return null;

                case 'external_supply_request':
                case 'external_supply_requests':
                    // للعمليات المتعلقة بطلبات التوريد الخارجية من مدير المستشفى (قبول/رفض)
                    // نعرض فقط رقم الشحنة بدون تفاصيل أخرى
                    if (in_array($log->action, ['hospital_admin_confirm_external_supply_request', 'hospital_admin_reject_external_supply_request'])) {
                        // محاولة استخراج رقم الطلب من JSON أولاً
                        $values = json_decode($log->new_values ?? $log->old_values, true);
                        $requestId = null;
                        if (is_array($values) && isset($values['request_id'])) {
                            $requestId = $values['request_id'];
                        } elseif ($log->record_id) {
                            $requestId = $log->record_id;
                        }
                        return $requestId ? 'EXT-' . $requestId : null;
                    }
                    
                    // للعمليات الأخرى، نعرض التفاصيل الكاملة
                    $request = ExternalSupplyRequest::with(['supplier', 'items'])->find($log->record_id);
                    if ($request) {
                        $details = [];
                        $details[] = 'EXT-' . $request->id;
                        
                        // إضافة اسم المورد
                        if ($request->supplier) {
                            $details[] = 'المورد: ' . $request->supplier->name;
                        }
                        
                        // إضافة عدد الأدوية
                        $itemsCount = $request->items->count();
                        if ($itemsCount > 0) {
                            $details[] = $itemsCount . ' دواء';
                        }
                        
                        // إضافة الحالة
                        $statusMap = [
                            'pending' => 'قيد الانتظار',
                            'approved' => 'مقبول',
                            'fulfilled' => 'مكتمل',
                            'rejected' => 'مرفوض'
                        ];
                        $status = $statusMap[$request->status] ?? $request->status;
                        $details[] = 'الحالة: ' . $status;
                        
                        return implode(' - ', $details);
                    }
                    // محاولة استخراج رقم الطلب من JSON
                    $values = json_decode($log->new_values ?? $log->old_values, true);
                    if (is_array($values) && isset($values['request_id'])) {
                        return 'EXT-' . $values['request_id'];
                    }
                    return $log->record_id ? 'EXT-' . $log->record_id : null;

                case 'complaints':
                    // جلب تفاصيل الشكوى
                    $complaint = Complaint::with('patient')->find($log->record_id);
                    if ($complaint) {
                        $details = [];

                        // إضافة اسم المريض
                        if ($complaint->patient) {
                            $details[] = 'المريض: ' . $complaint->patient->full_name;
                            $details[] = 'FILE-' . $complaint->patient_id;
                        }

                        // إضافة الحالة
                        $statusMap = [
                            'قيد المراجعة' => 'قيد المراجعة',
                            'تمت المراجعة' => 'تم الرد',
                            'مرفوض' => 'مرفوض'
                        ];
                        $status = $statusMap[$complaint->status] ?? $complaint->status;
                        $details[] = 'الحالة: ' . $status;

                        // إذا كان الإجراء هو الرد على الشكوى، نضيف توضيحاً
                        if (in_array($log->action, ['reply', 'respond'])) {
                            $details[] = 'العملية: الرد على الشكوى';
                        }

                        return implode(' - ', $details);
                    }
                    return null;

                case 'patient_transfer_requests':
                    // جلب تفاصيل طلب النقل
                    $transferRequest = PatientTransferRequest::with(['patient', 'fromHospital', 'toHospital'])->find($log->record_id);
                    if ($transferRequest) {
                        // تفاصيل خاصة بالإجراءات
                        if ($log->action === 'create') {
                            // عند إنشاء طلب النقل: "إنشاء طلب نقل مريض إلى مستشفى: [اسم المستشفى]"
                            $details = ['إنشاء طلب نقل مريض'];
                            if ($transferRequest->toHospital) {
                                $details[] = 'إلى مستشفى: ' . $transferRequest->toHospital->name;
                            }
                            if ($transferRequest->patient) {
                                $details[] = 'المريض: ' . $transferRequest->patient->full_name;
                            }
                            return implode(' - ', $details);
                        } elseif ($log->action === 'approve') {
                            // عند الموافقة النهائية: "الموافقة على نقل مريض إلى مستشفى: [اسم المستشفى]"
                            return 'الموافقة على نقل مريض إلى مستشفى: ' . ($transferRequest->toHospital->name ?? '-');
                        } elseif ($log->action === 'preapprove') {
                            // عند الموافقة الأولية: "الموافقة الأولية على نقل مريض إلى مستشفى: [اسم المستشفى]"
                            $details = ['الموافقة الأولية على نقل مريض'];
                            if ($transferRequest->toHospital) {
                                $details[] = 'إلى مستشفى: ' . $transferRequest->toHospital->name;
                            }
                            if ($transferRequest->patient) {
                                $details[] = 'المريض: ' . $transferRequest->patient->full_name;
                            }
                            return implode(' - ', $details);
                        } elseif ($log->action === 'reject') {
                            // عند الرفض: "رفض طلب النقل - مع تفاصيل المريض"
                            $details = ['رفض طلب النقل'];
                            if ($transferRequest->patient) {
                                $details[] = 'المريض: ' . $transferRequest->patient->full_name;
                            }
                            if ($transferRequest->rejection_reason) {
                                $details[] = 'سبب الرفض: ' . $transferRequest->rejection_reason;
                            }
                            return implode(' - ', $details);
                        }

                        // للعمليات الأخرى (إن وجدت)
                        $details = [];
                        if ($transferRequest->patient) {
                            $details[] = 'المريض: ' . $transferRequest->patient->full_name;
                        }
                        if ($transferRequest->toHospital) {
                            $details[] = 'إلى: ' . $transferRequest->toHospital->name;
                        }
                        return implode(' - ', $details);
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

    /**
     * جلب اسم الموظف أو المريض حسب نوع العملية
     * 
     * @param AuditLog $log
     * @return string
     */
    private function getTargetName($log)
    {
        if (!$log->table_name || !$log->record_id) {
            return '-';
        }

        try {
            switch ($log->table_name) {
                case 'users':
                    // جلب اسم الموظف الذي تم التعديل عليه
                    $user = User::find($log->record_id);
                    if ($user && $user->type !== 'patient') {
                        return $user->full_name ?? '-';
                    }
                    // محاولة من JSON
                    $values = json_decode($log->new_values ?? $log->old_values ?? '{}', true);
                    if (is_array($values) && isset($values['full_name']) && isset($values['type']) && $values['type'] !== 'patient') {
                        return $values['full_name'];
                    }
                    return '-';

                case 'complaints':
                    // جلب اسم المريض من الشكوى
                    $complaint = Complaint::with('patient')->find($log->record_id);
                    if ($complaint && $complaint->patient) {
                        return $complaint->patient->full_name ?? '-';
                    }
                    // محاولة من JSON
                    $values = json_decode($log->new_values ?? $log->old_values ?? '{}', true);
                    if (is_array($values) && isset($values['patient_id'])) {
                        $patient = User::find($values['patient_id']);
                        if ($patient && $patient->type === 'patient') {
                            return $patient->full_name ?? '-';
                        }
                    }
                    return '-';

                case 'patient_transfer_requests':
                    // جلب اسم المريض من طلب النقل
                    $transferRequest = PatientTransferRequest::with('patient')->find($log->record_id);
                    if ($transferRequest && $transferRequest->patient) {
                        return $transferRequest->patient->full_name ?? '-';
                    }
                    // محاولة من JSON
                    $values = json_decode($log->new_values ?? $log->old_values ?? '{}', true);
                    if (is_array($values) && isset($values['patient_id'])) {
                        $patient = User::find($values['patient_id']);
                        if ($patient && $patient->type === 'patient') {
                            return $patient->full_name ?? '-';
                        }
                    }
                    return '-';

                default:
                    return '-';
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to get target name for audit log', [
                'log_id' => $log->id ?? null,
                'error' => $e->getMessage()
            ]);
            return '-';
        }
    }
}

