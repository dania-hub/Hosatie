<?php

namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use App\Models\User;
use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Mail\StaffActivationMail;
use Illuminate\Support\Facades\Log;

class StaffController extends BaseApiController
{
    /**
     * Get all staff members for the hospital
     */
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

            // خريطة تحويل الأدوار من الإنجليزية إلى العربية
            $roleMap = [
                'doctor' => 'طبيب',
                'pharmacist' => 'صيدلي',
                'warehouse_manager' => 'مدير المخزن',
                'department_head' => 'مدير القسم',
                'data_entry' => 'مدخل بيانات',
            ];

            // جلب الموظفين مع التأكد من أنهم ينتمون للمستشفى الصحيح فقط
            // والتأكد من أن القسم (إن وجد) ينتمي لنفس المستشفى
            $query = User::where('hospital_id', $hospitalId)
                ->whereIn('type', ['doctor', 'pharmacist', 'warehouse_manager', 'department_head', 'data_entry']);
            
            // إذا كان department_id موجوداً في الجدول، نضيف فلتر للقسم
            if (Schema::hasColumn('users', 'department_id')) {
                $query->where(function($q) use ($hospitalId) {
                    // التأكد من أن القسم (إن وجد) ينتمي لنفس المستشفى
                    // أو أن الموظف ليس له قسم
                    $q->whereNull('department_id')
                      ->orWhereHas('department', function($deptQuery) use ($hospitalId) {
                          $deptQuery->where('hospital_id', $hospitalId);
                      });
                });
            }
            
            $staff = $query
                ->with([
                    'department' => function($query) use ($hospitalId) {
                        // التأكد من أن القسم المنقول ينتمي لنفس المستشفى
                        $query->where('hospital_id', $hospitalId);
                    },
                    'managedDepartment' => function($query) use ($hospitalId) {
                        // جلب القسم الذي يكون المستخدم مديراً له (لـ department_head)
                        $query->where('hospital_id', $hospitalId);
                    }
                ])
                ->latest()
                ->get()
                ->map(function ($user) use ($roleMap, $hospitalId) {
                    // التحقق من القسم: إما من department_id أو من managedDepartment
                    $departmentName = null;
                    $departmentId = null;
                    
                    // إذا كان المستخدم من نوع department_head، نبحث عن القسم من خلال managedDepartment
                    if ($user->type === 'department_head') {
                        if ($user->managedDepartment && $user->managedDepartment->hospital_id == $hospitalId) {
                            $departmentName = $user->managedDepartment->name;
                            $departmentId = $user->managedDepartment->id;
                        }
                    } 
                    // وإلا نبحث من خلال department_id
                    else if ($user->department && $user->department->hospital_id == $hospitalId) {
                        $departmentName = $user->department->name;
                        $departmentId = $user->department->id;
                    }
                    
                    return [
                        'fileNumber' => $user->id,
                        'name' => $user->full_name ?? '',
                        'nationalId' => $user->national_id ?? '',
                        'birth' => $user->birth_date ? $user->birth_date->format('Y/m/d') : null,
                        'phone' => $user->phone ?? '',
                        'email' => $user->email ?? '',
                        'role' => $roleMap[$user->type] ?? $user->type, // تحويل إلى العربية
                        'department' => $departmentName,
                        'departmentId' => $departmentId,
                        'isActive' => $user->status === 'active',
                        'lastUpdated' => $user->updated_at?->toIso8601String(),
                    ];
                })
                ->filter(function($employee) {
                    // إزالة أي موظفين بدون بيانات أساسية
                    return !empty($employee['name']) && !empty($employee['email']);
                })
                ->values();

            return $this->sendSuccess($staff, 'تم جلب قائمة الموظفين بنجاح.');
        } catch (\Exception $e) {
            Log::error('Get Staff Error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->sendError('فشل في جلب قائمة الموظفين.', [], 500);
        }
    }

    /**
     * Admin creates a new Staff member
     */
    public function store(Request $request)
    {
        try {
            $currentUser = $request->user();
            
            if (!$currentUser) {
                return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
            }

            $hospitalId = $currentUser->hospital_id;
            
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            // 1. Validate
            $validated = $request->validate([
                'full_name' => 'required|string|max:255',
                'email'     => 'required|email|unique:users,email',
                'role'      => 'required|string|in:doctor,pharmacist,warehouse_manager,department_head,data_entry',
                'phone'     => 'nullable|string|unique:users,phone',
                'national_id' => 'nullable|string|unique:users,national_id',
                'birth_date' => 'nullable|date',
                'department_id' => 'nullable|integer|exists:departments,id',
            ]);
            
            // تسجيل البيانات للتحقق
            Log::info('Creating employee', [
                'role' => $validated['role'],
                'department_id' => $validated['department_id'] ?? null,
                'hospital_id' => $hospitalId
            ]);

            DB::beginTransaction();

            // 2. إذا كان الموظف مدير مخزن، التحقق من عدم وجود مدير مخزن نشط آخر للمستشفى
            $warehouse = null;
            if ($validated['role'] === 'warehouse_manager') {
                $existingWarehouseManager = User::where('hospital_id', $hospitalId)
                    ->where('type', 'warehouse_manager')
                    ->where('status', 'active') // التحقق فقط من المديرين النشطين
                    ->first();
                
                if ($existingWarehouseManager) {
                    DB::rollBack();
                    Log::warning('Active warehouse manager already exists for hospital', [
                        'hospital_id' => $hospitalId,
                        'existing_manager_id' => $existingWarehouseManager->id
                    ]);
                    return $this->sendError('يوجد مدير مخزن نشط بالفعل لهذا المستشفى. لا يمكن إضافة أكثر من مدير مخزن نشط واحد لكل مستشفى.', [], 400);
                }

                // البحث عن المستودع المرتبط بنفس hospital_id
                $warehouse = \App\Models\Warehouse::where('hospital_id', $hospitalId)->first();
                
                if (!$warehouse) {
                    DB::rollBack();
                    Log::warning('Warehouse not found for hospital', [
                        'hospital_id' => $hospitalId
                    ]);
                    return $this->sendError('لم يتم العثور على مستودع لهذا المستشفى. يرجى إنشاء مستودع أولاً.', [], 404);
                }
            }

            // 3. إذا كان الموظف صيدلي، البحث عن الصيدلية الرئيسية للمستشفى
            $pharmacy = null;
            if ($validated['role'] === 'pharmacist') {
                // البحث عن الصيدلية الرئيسية (التي تحتوي على "الرئيسية" في الاسم)
                $pharmacy = Pharmacy::where('hospital_id', $hospitalId)
                    ->where('name', 'LIKE', '%الرئيسية%')
                    ->first();
                
                // إذا لم يتم العثور على صيدلية رئيسية، جلب أول صيدلية نشطة للمستشفى
                if (!$pharmacy) {
                    $pharmacy = Pharmacy::where('hospital_id', $hospitalId)
                        ->where('status', 'active')
                        ->first();
                }
                
                if (!$pharmacy) {
                    DB::rollBack();
                    Log::warning('Pharmacy not found for hospital', [
                        'hospital_id' => $hospitalId
                    ]);
                    return $this->sendError('لم يتم العثور على صيدلية لهذا المستشفى. يرجى إنشاء صيدلية أولاً.', [], 404);
                }
            }

            // 4. Create User (Inactive, No Password yet)
            // ملاحظة: department_id لا يوجد في جدول users، العلاقة تتم من خلال head_user_id في جدول departments
            $userData = [
                'full_name'   => $validated['full_name'],
                'email'       => $validated['email'],
                'type'        => $validated['role'],
                'hospital_id' => $hospitalId,
                'phone'       => $validated['phone'] ?? null,
                'national_id' => $validated['national_id'] ?? null,
                'birth_date'  => $validated['birth_date'] ?? null,
                'status'      => 'pending_activation',
                'password'    => '', // Empty for now
                'created_by'  => $currentUser->id,
            ];

            // إذا كان مدير مخزن، إضافة warehouse_id
            if ($validated['role'] === 'warehouse_manager' && $warehouse) {
                $userData['warehouse_id'] = $warehouse->id;
            }

            // إذا كان صيدلي، إضافة pharmacy_id
            if ($validated['role'] === 'pharmacist' && $pharmacy) {
                $userData['pharmacy_id'] = $pharmacy->id;
            }

            $user = User::create($userData);

            // 5. إذا كان الموظف مدير قسم وتم اختيار قسم، تحديث القسم
            if ($validated['role'] === 'department_head' && isset($validated['department_id']) && $validated['department_id'] !== null) {
                try {
                    $departmentId = (int)$validated['department_id'];
                    $department = \App\Models\Department::where('hospital_id', $hospitalId)
                        ->find($departmentId);
                    
                    if ($department) {
                        // التحقق من أن القسم لا يملك مديراً بالفعل
                        if ($department->head_user_id && $department->head_user_id != $user->id) {
                            DB::rollBack();
                            Log::warning('Department already has a manager', [
                                'department_id' => $departmentId,
                                'existing_manager_id' => $department->head_user_id,
                                'new_manager_id' => $user->id
                            ]);
                            return $this->sendError('هذا القسم لديه مدير بالفعل.', [], 400);
                        }
                        
                        // تحديث القسم بربطه بالمدير الجديد
                        $department->head_user_id = $user->id;
                        $department->save();
                        
                        Log::info('Department updated with new manager', [
                            'department_id' => $departmentId,
                            'manager_id' => $user->id
                        ]);
                    } else {
                        DB::rollBack();
                        Log::warning('Department not found', [
                            'department_id' => $departmentId,
                            'hospital_id' => $hospitalId
                        ]);
                        return $this->sendError('القسم المحدد غير موجود أو لا ينتمي إلى مستشفاك.', [], 404);
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Error updating department', [
                        'error' => $e->getMessage(),
                        'department_id' => $validated['department_id'] ?? null,
                        'trace' => $e->getTraceAsString()
                    ]);
                    return $this->sendError('حدث خطأ أثناء تحديث القسم: ' . $e->getMessage(), [], 500);
                }
            }

            // 6. Generate Secure Token
            $token = Str::random(60);

            // 7. Store Token in Cache
            $key = 'activation_token_' . $user->email;
            \Illuminate\Support\Facades\Cache::put($key, $token, 86400); // 24 hours
            
            // 8. Send Email (مع معالجة الأخطاء)
            try {
                Mail::to($user->email)->send(new StaffActivationMail($token, $user->email, $user->full_name));
            } catch (\Exception $mailException) {
                // تسجيل خطأ البريد ولكن لا نوقف العملية
                Log::warning('Failed to send activation email: ' . $mailException->getMessage(), [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
            }

            DB::commit();

            // تسجيل العملية في audit_log
            try {
                \App\Models\AuditLog::create([
                    'user_id' => $currentUser->id,
                    'hospital_id' => $hospitalId,
                    'action' => 'إضافة موظف',
                    'table_name' => 'users',
                    'record_id' => $user->id,
                    'new_values' => json_encode([
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                        'type' => $user->type,
                        'phone' => $user->phone,
                        'national_id' => $user->national_id,
                        'birth_date' => $user->birth_date ? 
                            (\Carbon\Carbon::parse($user->birth_date)->format('Y-m-d')) : null,
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create audit log for staff creation', ['error' => $e->getMessage()]);
            }

            return $this->sendSuccess([
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->type,
            ], 'تم إنشاء حساب الموظف بنجاح. سيتم إرسال رابط التفعيل إلى بريده الإلكتروني.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->sendError('التحقق من البيانات فشل.', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create Staff Error: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->all()
            ]);
            return $this->sendError('فشل في إنشاء حساب الموظف: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Update staff member
     */
    public function update(Request $request, $id)
    {
        try {
            $currentUser = $request->user();
            
            if (!$currentUser) {
                return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
            }

            $hospitalId = $currentUser->hospital_id;
            
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            // البحث عن الموظف في نفس المستشفى
            $staff = User::where('hospital_id', $hospitalId)
                ->whereIn('type', ['doctor', 'pharmacist', 'warehouse_manager', 'department_head', 'data_entry'])
                ->findOrFail($id);

            // Validate
            $validated = $request->validate([
                'full_name' => 'sometimes|required|string|max:255',
                'email'     => 'sometimes|required|email|unique:users,email,' . $id,
                'role'      => 'sometimes|required|string|in:doctor,pharmacist,warehouse_manager,department_head,data_entry',
                'phone'     => 'nullable|string|unique:users,phone,' . $id,
                'national_id' => 'nullable|string|unique:users,national_id,' . $id,
                'birth_date' => 'nullable|date',
            ]);

            DB::beginTransaction();

            // إذا تم تغيير النوع إلى warehouse_manager، التحقق من عدم وجود مدير مخزن نشط آخر
            $warehouse = null;
            if (isset($validated['role']) && $validated['role'] === 'warehouse_manager') {
                // التحقق فقط إذا كان النوع يتغير من نوع آخر إلى warehouse_manager
                if ($staff->type !== 'warehouse_manager') {
                    $existingWarehouseManager = User::where('hospital_id', $hospitalId)
                        ->where('type', 'warehouse_manager')
                        ->where('status', 'active') // التحقق فقط من المديرين النشطين
                        ->where('id', '!=', $id) // استثناء المستخدم الحالي
                        ->first();
                    
                    if ($existingWarehouseManager) {
                        DB::rollBack();
                        Log::warning('Active warehouse manager already exists for hospital', [
                            'hospital_id' => $hospitalId,
                            'existing_manager_id' => $existingWarehouseManager->id,
                            'updating_user_id' => $id
                        ]);
                        return $this->sendError('يوجد مدير مخزن نشط بالفعل لهذا المستشفى. لا يمكن إضافة أكثر من مدير مخزن نشط واحد لكل مستشفى.', [], 400);
                    }
                }

                // البحث عن المستودع المرتبط بنفس hospital_id
                $warehouse = \App\Models\Warehouse::where('hospital_id', $hospitalId)->first();
                
                if (!$warehouse) {
                    DB::rollBack();
                    Log::warning('Warehouse not found for hospital', [
                        'hospital_id' => $hospitalId
                    ]);
                    return $this->sendError('لم يتم العثور على مستودع لهذا المستشفى. يرجى إنشاء مستودع أولاً.', [], 404);
                }
            }

            // إذا تم تغيير النوع إلى pharmacist، البحث عن الصيدلية الرئيسية للمستشفى
            $pharmacy = null;
            if (isset($validated['role']) && $validated['role'] === 'pharmacist') {
                // البحث عن الصيدلية الرئيسية (التي تحتوي على "الرئيسية" في الاسم)
                $pharmacy = Pharmacy::where('hospital_id', $hospitalId)
                    ->where('name', 'LIKE', '%الرئيسية%')
                    ->first();
                
                // إذا لم يتم العثور على صيدلية رئيسية، جلب أول صيدلية نشطة للمستشفى
                if (!$pharmacy) {
                    $pharmacy = Pharmacy::where('hospital_id', $hospitalId)
                        ->where('status', 'active')
                        ->first();
                }
                
                if (!$pharmacy) {
                    DB::rollBack();
                    Log::warning('Pharmacy not found for hospital', [
                        'hospital_id' => $hospitalId
                    ]);
                    return $this->sendError('لم يتم العثور على صيدلية لهذا المستشفى. يرجى إنشاء صيدلية أولاً.', [], 404);
                }
            }

            // تحديث بيانات الموظف
            if (isset($validated['full_name'])) {
                $staff->full_name = $validated['full_name'];
            }
            if (isset($validated['email'])) {
                $staff->email = $validated['email'];
            }
            if (isset($validated['role'])) {
                $staff->type = $validated['role'];
                
                // إذا تم تغيير النوع إلى warehouse_manager، تعيين warehouse_id
                if ($validated['role'] === 'warehouse_manager' && $warehouse) {
                    $staff->warehouse_id = $warehouse->id;
                } elseif ($validated['role'] !== 'warehouse_manager') {
                    // إذا تم تغيير النوع من warehouse_manager إلى نوع آخر، إزالة warehouse_id
                    $staff->warehouse_id = null;
                }

                // إذا تم تغيير النوع إلى pharmacist، تعيين pharmacy_id
                if ($validated['role'] === 'pharmacist' && $pharmacy) {
                    $staff->pharmacy_id = $pharmacy->id;
                } elseif ($validated['role'] !== 'pharmacist') {
                    // إذا تم تغيير النوع من pharmacist إلى نوع آخر، إزالة pharmacy_id
                    $staff->pharmacy_id = null;
                }
            }
            if (isset($validated['phone'])) {
                $staff->phone = $validated['phone'];
            }
            if (isset($validated['national_id'])) {
                $staff->national_id = $validated['national_id'];
            }
            if (isset($validated['birth_date'])) {
                $staff->birth_date = $validated['birth_date'];
            }

            // التقاط القيم القديمة قبل الحفظ
            $oldValues = [
                'full_name' => $staff->getOriginal('full_name'),
                'email' => $staff->getOriginal('email'),
                'type' => $staff->getOriginal('type'),
                'phone' => $staff->getOriginal('phone'),
                'national_id' => $staff->getOriginal('national_id'),
                'birth_date' => $staff->getOriginal('birth_date') ? 
                    (\Carbon\Carbon::parse($staff->getOriginal('birth_date'))->format('Y-m-d')) : null,
            ];

            $staff->save();

            DB::commit();

            // تسجيل العملية في audit_log
            try {
                \App\Models\AuditLog::create([
                    'user_id' => $currentUser->id,
                    'hospital_id' => $hospitalId,
                    'action' => 'تعديل موظف',
                    'table_name' => 'users',
                    'record_id' => $staff->id,
                    'old_values' => json_encode($oldValues),
                    'new_values' => json_encode([
                        'full_name' => $staff->full_name,
                        'email' => $staff->email,
                        'type' => $staff->type,
                        'phone' => $staff->phone,
                        'national_id' => $staff->national_id,
                        'birth_date' => $staff->birth_date ? 
                            (\Carbon\Carbon::parse($staff->birth_date)->format('Y-m-d')) : null,
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create audit log for staff update', ['error' => $e->getMessage()]);
            }

            return $this->sendSuccess([
                'id' => $staff->id,
                'name' => $staff->full_name,
                'email' => $staff->email,
                'role' => $staff->type,
            ], 'تم تحديث بيانات الموظف ' . ($staff->full_name) . ' بنجاح.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('الموظف غير موجود أو لا ينتمي إلى هذا المستشفى.', [], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->sendError('التحقق من البيانات فشل.', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update Staff Error: ' . $e->getMessage(), [
                'exception' => $e,
                'staff_id' => $id,
                'request_data' => $request->all()
            ]);
            return $this->sendError('فشل في تحديث بيانات الموظف: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Toggle staff status (activate/deactivate)
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $currentUser = $request->user();
            
            if (!$currentUser) {
                return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
            }

            $hospitalId = $currentUser->hospital_id;
            
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            // التحقق من صحة البيانات
            $validated = $request->validate([
                'isActive' => 'required|boolean',
            ]);

            // البحث عن الموظف في نفس المستشفى
            $staff = User::where('hospital_id', $hospitalId)
                ->whereIn('type', ['doctor', 'pharmacist', 'warehouse_manager', 'department_head', 'data_entry'])
                ->findOrFail($id);

            // حفظ الحالة القديمة لتسجيلها
            $oldStatus = $staff->status;
            
            // إذا كان الموظف مدير مخزن ويتم تفعيله، التحقق من عدم وجود مدير مخزن نشط آخر
            if ($staff->type === 'warehouse_manager' && $validated['isActive'] && $oldStatus !== 'active') {
                $existingActiveWarehouseManager = User::where('hospital_id', $hospitalId)
                    ->where('type', 'warehouse_manager')
                    ->where('status', 'active')
                    ->where('id', '!=', $id) // استثناء المستخدم الحالي
                    ->first();
                
                if ($existingActiveWarehouseManager) {
                    Log::warning('Cannot activate warehouse manager: another active manager exists', [
                        'hospital_id' => $hospitalId,
                        'existing_manager_id' => $existingActiveWarehouseManager->id,
                        'attempting_to_activate_id' => $id
                    ]);
                    return $this->sendError('يوجد مدير مخزن نشط بالفعل لهذا المستشفى. لا يمكن تفعيل أكثر من مدير مخزن واحد في نفس الوقت.', [], 400);
                }
            }
            
            // تحديث الحالة
            $staff->status = $validated['isActive'] ? 'active' : 'inactive';
            $staff->save();

            // تسجيل العملية في audit_log
            try {
                \App\Models\AuditLog::create([
                    'user_id' => $currentUser->id,
                    'hospital_id' => $hospitalId,
                    'action' => $validated['isActive'] ? 'تفعيل موظف' : 'تعطيل موظف',
                    'table_name' => 'users',
                    'record_id' => $staff->id,
                    'old_values' => json_encode(['status' => $oldStatus]),
                    'new_values' => json_encode([
                        'status' => $staff->status,
                        'full_name' => $staff->full_name,
                        'type' => $staff->type,
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to create audit log for staff toggle status', ['error' => $e->getMessage()]);
            }

            return $this->sendSuccess([
                'id' => $staff->id,
                'name' => $staff->full_name,
                'isActive' => $staff->status === 'active',
                'lastUpdated' => $staff->updated_at->toIso8601String(),
            ], $validated['isActive'] ? 'تم تفعيل حساب الموظف بنجاح.' : 'تم تعطيل حساب الموظف بنجاح.');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('الموظف غير موجود أو لا ينتمي إلى هذا المستشفى.', [], 404);
        } catch (\Exception $e) {
            Log::error('Toggle Staff Status Error: ' . $e->getMessage(), [
                'exception' => $e,
                'staff_id' => $id,
                'request_data' => $request->all()
            ]);
            return $this->sendError('فشل في تحديث حالة الموظف: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Check if phone, email, or national_id already exists
     */
    public function checkUnique(Request $request)
    {
        try {
            $currentUser = $request->user();
            
            if (!$currentUser) {
                return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
            }

            $hospitalId = $currentUser->hospital_id;
            
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            $phone = $request->input('phone');
            $email = $request->input('email');
            $nationalId = $request->input('national_id');
            $excludeId = $request->input('exclude_id'); // For edit mode

            $exists = [];
            $messages = [];

            // Check phone
            if ($phone) {
                $phoneQuery = User::where('hospital_id', $hospitalId)
                    ->where('phone', $phone);
                
                if ($excludeId) {
                    $phoneQuery->where('id', '!=', $excludeId);
                }
                
                if ($phoneQuery->exists()) {
                    $exists['phone'] = true;
                    $messages['phone'] = 'رقم الهاتف موجود بالفعل في النظام';
                }
            }

            // Check email
            if ($email) {
                $emailQuery = User::where('hospital_id', $hospitalId)
                    ->where('email', $email);
                
                if ($excludeId) {
                    $emailQuery->where('id', '!=', $excludeId);
                }
                
                if ($emailQuery->exists()) {
                    $exists['email'] = true;
                    $messages['email'] = 'البريد الإلكتروني موجود بالفعل في النظام';
                }
            }

            // Check national_id
            if ($nationalId) {
                $nationalIdQuery = User::where('hospital_id', $hospitalId)
                    ->where('national_id', $nationalId);
                
                if ($excludeId) {
                    $nationalIdQuery->where('id', '!=', $excludeId);
                }
                
                if ($nationalIdQuery->exists()) {
                    $exists['national_id'] = true;
                    $messages['national_id'] = 'الرقم الوطني موجود بالفعل في النظام';
                }
            }

            return $this->sendSuccess([
                'exists' => count($exists) > 0,
                'fields' => $exists,
                'messages' => $messages
            ], count($exists) > 0 ? 'يوجد تكرار في البيانات' : 'البيانات متاحة');

        } catch (\Exception $e) {
            Log::error('Check Unique Error: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->all()
            ]);
            return $this->sendError('فشل في التحقق من البيانات: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Handle exceptions for this controller: log and return JSON error.
     *
     * @param \Throwable $e
     * @param string $contextMessage
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleException(\Throwable $e, $contextMessage = 'Error')
    {
        // Log the exception for debugging
        Log::error($contextMessage . ': ' . $e->getMessage(), ['exception' => $e]);

        // Return a generic JSON error response
        return response()->json([
            'success' => false,
            'message' => $contextMessage,
            'error'   => $e->getMessage()
        ], 500);
    }
}
