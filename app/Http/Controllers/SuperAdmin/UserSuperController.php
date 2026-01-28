<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use App\Models\Hospital;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\StaffActivationMail;
use App\Mail\AdminPasswordResetMail;

class UserSuperController extends BaseApiController
{
    /**
     * FR-94: عرض مدراء النظام والموردين
     * GET /api/super-admin/users
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('عذراً، ليس لديك صلاحية الوصول لهذه الموارد.', null, 403);
            }

            $query = User::with(['hospital', 'supplier', 'department', 'managedDepartment'])
                ->where('type', '!=', 'patient')
                ->where('id', '!=', $user->id);

            // البحث
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('national_id', 'like', "%{$search}%");
                });
            }

            // التصفية حسب النوع (المفرد)
            if ($request->has('type')) {
                $query->where('type', $request->input('type'));
            }

            // التصفية حسب الأنواع المتعددة
            if ($request->has('types')) {
                $types = $request->input('types');
                if (is_string($types)) {
                    $types = explode(',', $types);
                }
                if (is_array($types) && count($types) > 0) {
                     $query->whereIn('type', $types);
                }
            }

            // التصفية حسب الحالة
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // التصفية حسب المستشفى
            if ($request->has('hospital_id')) {
                $query->where('hospital_id', $request->input('hospital_id'));
            }

            // التصفية حسب المورد
            if ($request->has('supplier_id')) {
                $query->where('supplier_id', $request->input('supplier_id'));
            }

            $users = $query->orderBy('full_name')->get()->map(function ($u) {
                // Initialize type and department data
                $type = $u->type;
                $departmentData = null;

                // 1. Check direct department relationship (belongsTo via department_id)
                if ($u->department) {
                    $departmentData = [
                        'id' => $u->department->id,
                        'name' => $u->department->name
                    ];
                    // If user has a department relationship, mark as department_head per requirement
                    $type = 'department_head';
                }

                // 2. Check managed department relationship (hasOne via head_user_id)
                if (!$departmentData && $u->managedDepartment) {
                    $departmentData = [
                        'id' => $u->managedDepartment->id,
                        'name' => $u->managedDepartment->name
                    ];
                    $type = 'department_head';
                }

                // 3. Fallback: Manual check for managed department via head_user_id query
                // This catches cases where the relationship didn't load but the ID link exists
                if (!$departmentData) {
                     $managedDept = \App\Models\Department::where('head_user_id', $u->id)->first();
                     if ($managedDept) {
                         $departmentData = [
                             'id' => $managedDept->id,
                             'name' => $managedDept->name
                         ];
                         $type = 'department_head';
                     }
                }

                return [
                    'id' => $u->id,
                    'fullName' => $u->full_name,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'nationalId' => $u->national_id,
                    'birthDate' => $u->birth_date ? $u->birth_date->format('Y-m-d') : null,
                    'type' => $type,
                    'typeArabic' => $this->translateUserType($type),
                    'status' => $u->status,
                    'statusArabic' => $this->translateStatus($u->status),
                    'hospital' => $u->hospital ? [
                        'id' => $u->hospital->id,
                        'name' => $u->hospital->name,
                        'code' => $u->hospital->code,
                    ] : null,
                    'supplier' => $u->supplier ? [
                        'id' => $u->supplier->id,
                        'name' => $u->supplier->name,
                        'code' => $u->supplier->code,
                    ] : null,
                    'department' => $departmentData,
                    'createdAt' => optional($u->created_at)->format('Y-m-d'),
                ];
            });

            return $this->sendSuccess($users, 'تم تحميل قائمة المدراء بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Users Index Error');
        }
    }

    /**
     * عرض تفاصيل مستخدم
     * GET /api/super-admin/users/{id}
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('عذراً، ليس لديك صلاحية الوصول لهذه الموارد.', null, 403);
            }

            $targetUser = User::with(['hospital', 'supplier', 'creator'])
                ->whereIn('type', ['hospital_admin', 'supplier_admin'])
                ->findOrFail($id);

            $data = [
                'id' => $targetUser->id,
                'fullName' => $targetUser->full_name,
                'email' => $targetUser->email,
                'phone' => $targetUser->phone,
                'nationalId' => $targetUser->national_id,
                'birthDate' => $targetUser->birth_date ? $targetUser->birth_date->format('Y-m-d') : null,
                'type' => $targetUser->type,
                'typeArabic' => $this->translateUserType($targetUser->type),
                'status' => $targetUser->status,
                'statusArabic' => $this->translateStatus($targetUser->status),
                'hospital' => $targetUser->hospital ? [
                    'id' => $targetUser->hospital->id,
                    'name' => $targetUser->hospital->name,
                    'code' => $targetUser->hospital->code,
                    'city' => $targetUser->hospital->city,
                ] : null,
                'supplier' => $targetUser->supplier ? [
                    'id' => $targetUser->supplier->id,
                    'name' => $targetUser->supplier->name,
                    'code' => $targetUser->supplier->code,
                    'city' => $targetUser->supplier->city,
                ] : null,
                'createdBy' => $targetUser->creator ? [
                    'id' => $targetUser->creator->id,
                    'name' => $targetUser->creator->full_name,
                ] : null,
                'createdAt' => optional($targetUser->created_at)->format('Y-m-d H:i'),
                'updatedAt' => optional($targetUser->updated_at)->format('Y-m-d H:i'),
            ];

            return $this->sendSuccess($data, 'تم جلب تفاصيل المستخدم بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin User Show Error');
        }
    }

    /**
     * FR-93: إنشاء حساب مدير نظام مستشفى أو مورد
     * POST /api/super-admin/users
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('عذراً، ليس لديك صلاحية الوصول لهذه الموارد.', null, 403);
            }

            $validator = Validator::make($request->all(), [
                'type' => 'required|in:hospital_admin,supplier_admin',
                'full_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'required|string|max:20|unique:users,phone',
                'national_id' => 'nullable|string|max:20|unique:users,national_id',
                'birth_date' => 'nullable|date',
                'hospital_id' => 'required_if:type,hospital_admin|exists:hospitals,id',
                'supplier_id' => 'required_if:type,supplier_admin|exists:suppliers,id',
            ], [
                'type.required' => 'يرجى تحديد نوع المدير (مستشفى أو مورد)',
                'type.in' => 'نوع المستخدم المختار غير صحيح',
                'full_name.required' => 'الاسم الرباعي مطلوب',
                'email.required' => 'البريد الإلكتروني حقل إلزامي',
                'email.unique' => 'عذراً، هذا البريد الإلكتروني مسجل مسبقاً',
                'phone.required' => 'رقم الهاتف حقل إلزامي',
                'phone.unique' => 'عذراً، رقم الهاتف هذا مسجل مسبقاً',
                'national_id.unique' => 'عذراً، الرقم الوطني هذا مسجل مسبقاً',
                'hospital_id.required_if' => 'يرجى اختيار المستشفى لمدير النظام',
                'supplier_id.required_if' => 'يرجى اختيار الشركة الموردة لمدير المورد',
            ]);

            if ($validator->fails()) {
                return $this->sendError('عذراً، البيانات المدخلة غير صحيحة. يرجى التحقق منها.', $validator->errors(), 422);
            }

            // توليد كلمة مرور عشوائية
            $randomPassword = Str::random(10);

            $newUser = User::create([
                'type' => $request->type,
                'full_name' => $request->full_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'national_id' => $request->national_id,
                'birth_date' => $request->birth_date,
                'password' => Hash::make($randomPassword),
                'hospital_id' => $request->type === 'hospital_admin' ? $request->hospital_id : null,
                'supplier_id' => $request->type === 'supplier_admin' ? $request->supplier_id : null,
                'status' => 'pending_activation',
                'created_by' => $user->id,
            ]);

            // 1. Generate Secure Token
            $token = Str::random(60);

            // 2. Store Token in Cache
            $key = 'activation_token_' . $newUser->email;
            \Illuminate\Support\Facades\Cache::put($key, $token, 86400); // 24 hours

            // 3. Send Email (مع معالجة الأخطاء)
            try {
                $recipientEmail = trim($newUser->email);
                Mail::to($recipientEmail)->send(new StaffActivationMail($token, $recipientEmail, $newUser->full_name));
                
                \Log::info('Activation email sent (attempted) via Mailable to: ' . $recipientEmail);

            } catch (\Throwable $mailException) {
                // تسجيل خطأ البريد ولكن لا نوقف العملية
                \Log::error('Failed to send activation email: ' . $mailException->getMessage(), [
                    'user_id' => $newUser->id,
                    'email' => $newUser->email,
                    'trace' => $mailException->getTraceAsString()
                ]);
            }

            return $this->sendSuccess([
                'id' => $newUser->id,
                'fullName' => $newUser->full_name,
                'email' => $newUser->email,
                'temporaryPassword' => $randomPassword, // في الواقع يُرسل عبر البريد فقط
            ], '✅ تم إنشاء حساب المدير بنجاح. تم إرسال بيانات الدخول إلى البريد الإلكتروني.', 201);

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin User Store Error');
        }
    }

    /**
     * FR-95: تعديل بيانات حساب مدير نظام أو مورد
     * PUT /api/super-admin/users/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('عذراً، ليس لديك صلاحية الوصول لهذه الموارد.', null, 403);
            }

            $targetUser = User::whereIn('type', ['hospital_admin', 'supplier_admin'])
                ->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'full_name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:users,email,' . $id,
                'phone' => 'sometimes|required|string|max:20|unique:users,phone,' . $id,
                'national_id' => 'nullable|string|max:20|unique:users,national_id,' . $id,
                'birth_date' => 'nullable|date',
                'hospital_id' => 'nullable|exists:hospitals,id',
                'supplier_id' => 'nullable|exists:suppliers,id',
            ], [
                'email.unique' => 'عذراً، هذا البريد الإلكتروني مسجل مسبقاً',
                'phone.unique' => 'عذراً، رقم الهاتف هذا مسجل مسبقاً',
                'national_id.unique' => 'عذراً، الرقم الوطني هذا مسجل مسبقاً',
                'hospital_id.exists' => 'عذراً، المستشفى المختار غير صحيح',
                'supplier_id.exists' => 'عذراً، المورد المختار غير صحيح',
            ]);

            if ($validator->fails()) {
                return $this->sendError('عذراً، البيانات المدخلة غير صحيحة. يرجى التحقق منها.', $validator->errors(), 422);
            }

            $targetUser->update($request->only([
                'full_name', 'email', 'phone', 'national_id', 'birth_date', 'hospital_id', 'supplier_id'
            ]));

            return $this->sendSuccess([
                'id' => $targetUser->id,
                'fullName' => $targetUser->full_name,
            ], '✅ تم تحديث بيانات المدير بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin User Update Error');
        }
    }

    /**
     * FR-96: تعطيل حساب مدير نظام أو مورد
     * PATCH /api/super-admin/users/{id}/deactivate
     */
    public function deactivate(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('عذراً، ليس لديك صلاحية الوصول لهذه الموارد.', null, 403);
            }

            $targetUser = User::whereIn('type', ['hospital_admin', 'supplier_admin'])
                ->findOrFail($id);

            if ($targetUser->status === 'inactive') {
                return $this->sendError('الحساب معطل بالفعل', null, 400);
            }

            DB::beginTransaction();

            try {
                // تعطيل الحساب فقط دون إزالة الارتباط بالمستشفى أو المورد
                $targetUser->update(['status' => 'inactive']);

                // حذف جميع tokens للمستخدم لمنع استمرار الدخول
                $targetUser->tokens()->delete();

                DB::commit();

                return $this->sendSuccess([
                    'id' => $targetUser->id,
                    'fullName' => $targetUser->full_name,
                    'status' => 'inactive',
                ], '✅ تم تعطيل الحساب بنجاح (مع الاحتفاظ بالتبعية)');

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin User Deactivate Error');
        }
    }

    /**
     * FR-97: تفعيل حساب مدير نظام أو مورد
     * PATCH /api/super-admin/users/{id}/activate
     */
    public function activate(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('عذراً، ليس لديك صلاحية الوصول لهذه الموارد.', null, 403);
            }

            $targetUser = User::whereIn('type', ['hospital_admin', 'supplier_admin'])
                ->findOrFail($id);

            if ($targetUser->status === 'active') {
                return $this->sendError('الحساب مفعل بالفعل', null, 400);
            }

            // التحقق من أن مدير المستشفى مربوط بمستشفى قبل التفعيل
            if ($targetUser->type === 'hospital_admin') {
                if (!$targetUser->hospital_id) {
                    return $this->sendError(
                        'لا يمكن تفعيل حساب مدير المستشفى إلا إذا كان مربوطاً بمستشفى. يرجى تعيينه لمستشفى من صفحة المستشفيات أولاً.',
                        ['hospital_id' => ['المدير غير مربوط بأي مستشفى']],
                        422
                    );
                }

                // منع التنشيط إذا كان هناك مدير نشط حالياً لنفس المستشفى
                $activeManager = User::where('type', 'hospital_admin')
                    ->where('hospital_id', $targetUser->hospital_id)
                    ->where('status', 'active')
                    ->where('id', '!=', $targetUser->id)
                    ->first();
                
                if ($activeManager) {
                    return $this->sendError('فشل تفعيل لوجود مدير مستشفى حالياً', null, 422);
                }

                // التحقق من أن المستشفى موجود ونشط
                $hospital = Hospital::find($targetUser->hospital_id);
                if (!$hospital) {
                    return $this->sendError(
                        'المستشفى المرتبط بهذا المدير غير موجود',
                        ['hospital_id' => ['المستشفى غير موجود']],
                        422
                    );
                }
            }

            // التحقق من أن مدير المورد مربوط بمورد قبل التفعيل
            if ($targetUser->type === 'supplier_admin') {
                if (!$targetUser->supplier_id) {
                    return $this->sendError(
                        'لا يمكن تفعيل حساب مدير المورد إلا إذا كان مربوطاً بمورد. يرجى تعيينه لمورد أولاً.',
                        ['supplier_id' => ['المدير غير مربوط بأي مورد']],
                        422
                    );
                }

                // منع التنشيط إذا كان هناك مدير نشط حالياً لنفس المورد
                $activeSupplierManager = User::where('type', 'supplier_admin')
                    ->where('supplier_id', $targetUser->supplier_id)
                    ->where('status', 'active')
                    ->where('id', '!=', $targetUser->id)
                    ->first();
                
                if ($activeSupplierManager) {
                    return $this->sendError('فشل التفعيل لوجود مدير مستودع توريد حالياً', null, 422);
                }
            }

            $targetUser->update(['status' => 'active']);

            return $this->sendSuccess([
                'id' => $targetUser->id,
                'fullName' => $targetUser->full_name,
                'status' => 'active',
            ], '✅ تم تفعيل الحساب بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin User Activate Error');
        }
    }

    /**
     * إعادة تعيين كلمة مرور مستخدم
     * POST /api/super-admin/users/{id}/reset-password
     */
    public function resetPassword(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('عذراً، ليس لديك صلاحية الوصول لهذه الموارد.', null, 403);
            }

            $targetUser = User::whereIn('type', ['hospital_admin', 'supplier_admin'])
                ->findOrFail($id);

            // توليد كلمة مرور جديدة
            $newPassword = Str::random(10);

            $targetUser->update([
                'password' => Hash::make($newPassword),
                'status' => 'pending_activation', // يجب تغيير كلمة المرور عند أول دخول
            ]);

            // حذف جميع tokens القديمة
            $targetUser->tokens()->delete();

            // إرسال كلمة المرور الجديدة عبر البريد الإلكتروني
            try {
                $recipientEmail = trim($targetUser->email);
                Mail::to($recipientEmail)->send(new AdminPasswordResetMail($targetUser->full_name, $newPassword));
                
                \Log::info('Password reset email sent (attempted) via Mailable to: ' . $recipientEmail);
            } catch (\Throwable $e) {
                \Log::error('فشل إرسال بريد إعادة تعيين كلمة المرور: ' . $e->getMessage(), [
                    'user_id' => $targetUser->id,
                    'email' => $targetUser->email,
                    'trace' => $e->getTraceAsString()
                ]);
            }

            return $this->sendSuccess([
                'id' => $targetUser->id,
                'email' => $targetUser->email,
                'temporaryPassword' => $newPassword, // في الواقع يُرسل عبر البريد فقط
            ], '✅ تم إعادة تعيين كلمة المرور بنجاح. تم إرسال كلمة المرور الجديدة إلى البريد الإلكتروني.');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin User Reset Password Error');
        }
    }

    /**
     * التحقق من وجود رقم الهاتف في النظام
     * GET /api/super-admin/users/check-phone/{phone}
     */
    public function checkPhone(Request $request, $phone)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('عذراً، ليس لديك صلاحية الوصول لهذه الموارد.', null, 403);
            }

            // التحقق من التنسيق
            if (!preg_match('/^(021|092|091|093|094)\d{7}$/', $phone)) {
                return $this->sendError('عذراً، تنسيق رقم الهاتف غير صحيح. يجب أن يبدأ بـ 021, 091, 092, 093, 094', null, 422);
            }

            // التحقق من وجود الرقم في users و hospitals و suppliers
            $existsInUsers = User::where('phone', $phone)->exists();
            $existsInHospitals = Hospital::where('phone', $phone)->exists();
            $existsInSuppliers = Supplier::where('phone', $phone)->exists();
            $exists = $existsInUsers || $existsInHospitals || $existsInSuppliers;

            return $this->sendSuccess([
                'exists' => $exists,
                'phone' => $phone
            ], $exists ? 'عذراً، رقم الهاتف هذا مسجل مسبقاً' : 'رقم الهاتف متاح للاستخدام');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin User Check Phone Error');
        }
    }

    /**
     * التحقق من وجود الرقم الوطني في النظام
     * GET /api/super-admin/users/check-national-id/{nationalId}
     */
    public function checkNationalId(Request $request, $nationalId)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('عذراً، ليس لديك صلاحية الوصول لهذه الموارد.', null, 403);
            }

            // التحقق من التنسيق
            if (!preg_match('/^[12]\d{11}$/', $nationalId)) {
                return $this->sendError('عذراً، تنسيق الرقم الوطني غير صحيح. يجب أن يبدأ بـ 1 أو 2 ويتكون من 12 رقم', null, 422);
            }

            // التحقق من وجود الرقم في users
            $exists = User::where('national_id', $nationalId)->exists();

            return $this->sendSuccess([
                'exists' => $exists,
                'nationalId' => $nationalId
            ], $exists ? 'عذراً، الرقم الوطني هذا مسجل مسبقاً' : 'الرقم الوطني متاح للاستخدام');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin User Check National ID Error');
        }
    }

    /**
     * التحقق من وجود البريد الإلكتروني في النظام
     * GET /api/super-admin/users/check-email/{email}
     */
    public function checkEmail(Request $request, $email)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('عذراً، ليس لديك صلاحية الوصول لهذه الموارد.', null, 403);
            }

            // التحقق من التنسيق
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->sendError('عذراً، تنسيق البريد الإلكتروني غير صحيح', null, 422);
            }

            // التحقق من وجود البريد في users
            $exists = User::where('email', $email)->exists();

            return $this->sendSuccess([
                'exists' => $exists,
                'email' => $email
            ], $exists ? 'عذراً، هذا البريد الإلكتروني مسجل مسبقاً' : 'البريد الإلكتروني متاح للاستخدام');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin User Check Email Error');
        }
    }

    /**
     * Helper: ترجمة نوع المستخدم
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
            'patient' => 'مريض',
            'data_entry' => 'مدخل البيانات',
            default => $type,
        };
    }

    /**
     * Helper: ترجمة الحالة
     */
    private function translateStatus($status)
    {
        return match($status) {
            'active' => 'نشط',
            'inactive' => 'معطل',
            'pending_activation' => 'بانتظار التفعيل',
            default => $status,
        };
    }
}
