<?php

namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DepartmentHospitalAdminController extends BaseApiController
{
    // 1) قائمة الأقسام
   public function index(Request $request)
{
    try {
        $user = $request->user();
        
        if (!$user) {
            return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
        }

        $hospitalId = $user->hospital_id;
        
        if (!$hospitalId) {
            return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
        }

        $departments = Department::with('head')
            ->where('hospital_id', $hospitalId)
            ->latest()
            ->get()
            ->map(function ($dep) {
                return [
                    'id'          => $dep->id,
                    'name'        => $dep->name,
                    'managerId'   => $dep->head_user_id,
                    'managerName' => $dep->head?->full_name ?? null,
                    'managerEmail' => $dep->head?->email ?? null,
                    'isActive'    => $dep->status === 'active',
                    'lastUpdated' => $dep->updated_at?->toIso8601String(),
                ];
            });

        return $this->sendSuccess($departments, 'تم جلب قائمة الأقسام بنجاح.');
    } catch (\Exception $e) {
        Log::error('Get Departments Error: ' . $e->getMessage(), ['exception' => $e]);
        return $this->sendError('فشل في جلب قائمة الأقسام.', [], 500);
    }
}

    // 2) قائمة الموظفين (مدراء الأقسام فقط) لاختيارهم كمديرين
 public function employees(Request $request)
{
    try {
        $user = $request->user();
        
        if (!$user) {
            return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
        }

        $hospitalId = $user->hospital_id;
        
        if (!$hospitalId) {
            return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
        }

        // جلب مدراء الأقسام فقط (department_head)
        $employees = User::where('hospital_id', $hospitalId)
            ->where('type', 'department_head')
            ->where('status', 'active')
            ->get()
            ->map(function ($u) {
                return [
                    'id'       => $u->id,
                    'fullName' => $u->full_name,
                    'isActive' => $u->status === 'active',
                ];
            });

        return $this->sendSuccess($employees, 'تم جلب قائمة مدراء الأقسام بنجاح.');
    } catch (\Exception $e) {
        Log::error('Get Employees Error: ' . $e->getMessage(), ['exception' => $e]);
        return $this->sendError('فشل في جلب قائمة الموظفين.', [], 500);
    }
}



    // 3) إنشاء قسم جديد
    public function store(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
            }

            $hospitalId = $user->hospital_id;
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            $data = $request->validate([
                'name'      => [
                    'required',
                    'string',
                    'max:255',
                    \Illuminate\Validation\Rule::unique('departments')->where(function ($query) use ($hospitalId) {
                        return $query->where('hospital_id', $hospitalId);
                    })
                ],
                'managerId' => 'nullable|exists:users,id',
            ], [
                'name.unique' => ' اسم القسم هذا موجود بالفعل في هذا المستشفى. يرجى اختيار اسم مختلف للقسم.',
            ]);

            // إذا تم تعيين مدير قسم، تغيير type من doctor إلى department_head
            if (!empty($data['managerId'])) {
                $manager = User::where('hospital_id', $hospitalId)
                    ->find($data['managerId']);
                
                if ($manager && $manager->type === 'doctor') {
                    $manager->type = 'department_head';
                    $manager->save();
                    
                    Log::info('Changed user type from doctor to department_head', [
                        'user_id' => $manager->id,
                        'department_id' => null, // سيتم تعيينه بعد إنشاء القسم
                    ]);
                }
            }

            $dep = Department::create([
                'hospital_id'  => $hospitalId,
                'name'         => $data['name'],
                'head_user_id' => $data['managerId'] ?? null,
                'status'       => 'active',
            ]);

            $dep->load('head');

            // التقاط اسم المدير الحالي إذا تم تعيينه
            $managerName = '-';
            if ($dep->head_user_id) {
                $managerName = $dep->head ? $dep->head->full_name : '-';
            }

            // تسجيل العملية في audit_log
            try {
                \App\Models\AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => $hospitalId,
                    'action' => 'إضافة قسم',
                    'table_name' => 'departments',
                    'record_id' => $dep->id,
                    'new_values' => json_encode([
                        'name' => $dep->name,
                        'head_user_id' => $dep->head_user_id,
                        'manager_name' => $managerName,
                        'status' => $dep->status,
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create audit log for department creation', ['error' => $e->getMessage()]);
            }

            return $this->sendSuccess([
                'id'          => $dep->id,
                'name'        => $dep->name,
                'managerId'   => $dep->head_user_id,
                'managerName' => $dep->head?->full_name ?? null,
                'managerEmail' => $dep->head?->email ?? null,
                'isActive'    => $dep->status === 'active',
                'lastUpdated' => $dep->updated_at?->toIso8601String(),
            ], 'تم إنشاء القسم بنجاح.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // استخدام الرسالة المخصصة من validation errors بدلاً من الرسالة العامة
            $errors = $e->errors();
            $firstError = reset($errors); // الحصول على أول خطأ
            $errorMessage = is_array($firstError) ? $firstError[0] : $firstError;
            return $this->sendError($errorMessage, $errors, 422);
        } catch (\Exception $e) {
            Log::error('Create Department Error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->sendError('فشل في إنشاء القسم.', [], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
            }

            $hospitalId = $user->hospital_id;
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            $dep = Department::where('hospital_id', $hospitalId)->find($id);
            if (!$dep) {
                return $this->sendError('القسم غير موجود أو لا ينتمي إلى مستشفاك.', [], 404);
            }

            $data = $request->validate([
                'name'      => [
                    'required',
                    'string',
                    'max:255',
                    \Illuminate\Validation\Rule::unique('departments')->where(function ($query) use ($hospitalId) {
                        return $query->where('hospital_id', $hospitalId);
                    })->ignore($id)
                ],
                'managerId' => 'nullable|exists:users,id',
                'isActive'  => 'nullable|boolean',
            ], [
                'name.unique' => '⚠️ اسم القسم هذا موجود بالفعل في هذا المستشفى. يرجى اختيار اسم مختلف للقسم.',
            ]);

            $oldManagerId = $dep->head_user_id;
            $newManagerId = $data['managerId'] ?? null;

            // إذا تم تغيير المدير أو إزالته
            if ($oldManagerId !== $newManagerId) {
                // إذا كان هناك مدير سابق، إرجاع type إلى doctor
                if ($oldManagerId) {
                    $oldManager = User::where('hospital_id', $hospitalId)
                        ->find($oldManagerId);
                    
                    if ($oldManager && $oldManager->type === 'department_head') {
                        $oldManager->type = 'doctor';
                        $oldManager->save();
                        
                        Log::info('Changed user type from department_head to doctor', [
                            'user_id' => $oldManager->id,
                            'department_id' => $dep->id,
                        ]);
                    }
                }

                // إذا تم تعيين مدير جديد، تغيير type من doctor إلى department_head
                if ($newManagerId) {
                    $newManager = User::where('hospital_id', $hospitalId)
                        ->find($newManagerId);
                    
                    if ($newManager && $newManager->type === 'doctor') {
                        $newManager->type = 'department_head';
                        $newManager->save();
                        
                        Log::info('Changed user type from doctor to department_head', [
                            'user_id' => $newManager->id,
                            'department_id' => $dep->id,
                        ]);
                    }
                }
            }

            // التقاط القيم القديمة قبل التحديث
            $oldName = $dep->name;
            $oldStatus = $dep->status;
            $oldHeadId = $dep->head_user_id;
            $oldManagerName = '-';
            if ($oldHeadId) {
                $oldManager = User::find($oldHeadId);
                $oldManagerName = $oldManager ? $oldManager->full_name : '-';
            }

            $dep->update([
                'name'         => $data['name'],
                'head_user_id' => $newManagerId,
                'status'       => isset($data['isActive']) ? ($data['isActive'] ? 'active' : 'inactive') : $dep->status,
            ]);

            $dep->load('head');

            $newManagerName = '-';
            if ($dep->head_user_id) {
                // إذا كان المدير الجديد هو نفسه القديم ولم يتغير، نوفر استعلام
                if ($dep->head_user_id == $oldHeadId) {
                    $newManagerName = $oldManagerName;
                } else {
                    $newManagerName = $dep->head ? $dep->head->full_name : '-';
                }
            }

            // تسجيل العملية في audit_log
            try {
                $logAction = 'تعديل قسم';
                if (isset($data['isActive']) && $data['isActive'] !== ($oldStatus === 'active')) {
                    $logAction = $data['isActive'] ? 'تفعيل قسم' : 'تعطيل قسم';
                }
                
                \App\Models\AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => $hospitalId,
                    'action' => $logAction,
                    'table_name' => 'departments',
                    'record_id' => $dep->id,
                    'old_values' => json_encode([
                        'name' => $oldName,
                        'status' => $oldStatus,
                        'head_user_id' => $oldHeadId,
                        'manager_name' => $oldManagerName,
                    ]),
                    'new_values' => json_encode([
                        'name' => $dep->name,
                        'status' => $dep->status,
                        'head_user_id' => $dep->head_user_id,
                        'manager_name' => $newManagerName,
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create audit log for department update', ['error' => $e->getMessage()]);
            }

            $action = isset($data['isActive']) && $data['isActive'] !== ($oldStatus === 'active') 
                ? ($data['isActive'] ? ' وتم تفعيله' : ' وتم تعطيله') 
                : '';

            return $this->sendSuccess([
                'id'          => $dep->id,
                'name'        => $dep->name,
                'managerId'   => $dep->head_user_id,
                'managerName' => $dep->head?->full_name ?? null,
                'managerEmail' => $dep->head?->email ?? null,
                'isActive'    => $dep->status === 'active',
                'lastUpdated' => $dep->updated_at?->toIso8601String(),
            ], 'تم تحديث بيانات القسم بنجاح.' . $action);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // استخدام الرسالة المخصصة من validation errors بدلاً من الرسالة العامة
            $errors = $e->errors();
            $firstError = reset($errors); // الحصول على أول خطأ
            $errorMessage = is_array($firstError) ? $firstError[0] : $firstError;
            return $this->sendError($errorMessage, $errors, 422);
        } catch (\Exception $e) {
            Log::error('Update Department Error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->sendError('فشل في تحديث بيانات القسم.', [], 500);
        }
    }


    // 4) عرض قسم واحد
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
            }

            $hospitalId = $user->hospital_id;
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            $dep = Department::with('head')
                ->where('hospital_id', $hospitalId)
                ->find($id);

            if (!$dep) {
                return $this->sendError('القسم غير موجود أو لا ينتمي إلى مستشفاك.', [], 404);
            }

            return $this->sendSuccess([
                'id'          => $dep->id,
                'name'        => $dep->name,
                'managerId'   => $dep->head_user_id,
                'managerName' => $dep->head?->full_name ?? null,
                'managerEmail' => $dep->head?->email ?? null,
                'isActive'    => $dep->status === 'active',
                'lastUpdated' => $dep->updated_at?->toIso8601String(),
            ], 'تم جلب بيانات القسم بنجاح.');
        } catch (\Exception $e) {
            Log::error('Get Department Error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->sendError('فشل في جلب بيانات القسم.', [], 500);
        }
    }

    // 5) تفعيل/تعطيل قسم
    public function toggleStatus(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
            }

            $hospitalId = $user->hospital_id;
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            $department = Department::where('hospital_id', $hospitalId)->find($id);
            if (!$department) {
                return $this->sendError('القسم غير موجود أو لا ينتمي إلى مستشفاك.', [], 404);
            }

            $data = $request->validate([
                'isActive' => 'required|boolean',
            ]);

            $oldStatus = $department->status;
            $department->status = $data['isActive'] ? 'active' : 'inactive';
            $department->save();

            // تسجيل العملية في audit_log
            try {
                \App\Models\AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => $hospitalId,
                    'action' => $data['isActive'] ? 'تفعيل قسم' : 'تعطيل قسم',
                    'table_name' => 'departments',
                    'record_id' => $department->id,
                    'old_values' => json_encode(['status' => $oldStatus]),
                    'new_values' => json_encode([
                        'status' => $department->status,
                        'name' => $department->name,
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create audit log for department toggle status', ['error' => $e->getMessage()]);
            }

            $action = $data['isActive'] ? 'تفعيل' : 'تعطيل';
            return $this->sendSuccess([
                'isActive' => $department->status === 'active',
                'lastUpdated' => $department->updated_at?->toIso8601String(),
            ], "تم {$action} القسم {$department->name} بنجاح.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            // استخدام الرسالة المخصصة من validation errors بدلاً من الرسالة العامة
            $errors = $e->errors();
            $firstError = reset($errors); // الحصول على أول خطأ
            $errorMessage = is_array($firstError) ? $firstError[0] : $firstError;
            return $this->sendError($errorMessage, $errors, 422);
        } catch (\Exception $e) {
            Log::error('Toggle Department Status Error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->sendError('فشل في تغيير حالة القسم.', [], 500);
        }
    }
}
