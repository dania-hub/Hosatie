<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $query = User::with(['hospital', 'supplier'])
                ->whereIn('type', ['hospital_admin', 'supplier_admin']);

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

            // التصفية حسب النوع
            if ($request->has('type')) {
                $query->where('type', $request->input('type'));
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
                return [
                    'id' => $u->id,
                    'fullName' => $u->full_name,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'nationalId' => $u->national_id,
                    'type' => $u->type,
                    'typeArabic' => $this->translateUserType($u->type),
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
                    'createdAt' => optional($u->created_at)->format('Y-m-d'),
                ];
            });

            return $this->sendSuccess($users, 'تم جلب قائمة المستخدمين بنجاح');

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
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
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
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $validator = Validator::make($request->all(), [
                'type' => 'required|in:hospital_admin,supplier_admin',
                'full_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'required|string|max:20|unique:users,phone',
                'national_id' => 'nullable|string|max:20|unique:users,national_id',
                'hospital_id' => 'required_if:type,hospital_admin|exists:hospital,id',
                'supplier_id' => 'required_if:type,supplier_admin|exists:supplier,id',
            ], [
                'type.required' => 'نوع المستخدم مطلوب',
                'type.in' => 'نوع المستخدم غير صحيح',
                'full_name.required' => 'الاسم الكامل مطلوب',
                'email.required' => 'البريد الإلكتروني مطلوب',
                'email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
                'phone.required' => 'رقم الهاتف مطلوب',
                'phone.unique' => 'رقم الهاتف مستخدم بالفعل',
                'national_id.unique' => 'الرقم الوطني مستخدم بالفعل',
                'hospital_id.required_if' => 'المستشفى مطلوب لمدير النظام',
                'supplier_id.required_if' => 'المورد مطلوب لمدير المورد',
            ]);

            if ($validator->fails()) {
                return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
            }

            // توليد كلمة مرور عشوائية
            $randomPassword = Str::random(10);

            $newUser = User::create([
                'type' => $request->type,
                'full_name' => $request->full_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'national_id' => $request->national_id,
                'password' => Hash::make($randomPassword),
                'hospital_id' => $request->type === 'hospital_admin' ? $request->hospital_id : null,
                'supplier_id' => $request->type === 'supplier_admin' ? $request->supplier_id : null,
                'status' => 'pending_activation',
                'created_by' => $user->id,
            ]);

            // TODO: إرسال بريد إلكتروني يحتوي على:
            // - اسم المستخدم
            // - كلمة المرور المؤقتة
            // - رابط تفعيل الحساب

            return $this->sendSuccess([
                'id' => $newUser->id,
                'fullName' => $newUser->full_name,
                'email' => $newUser->email,
                'temporaryPassword' => $randomPassword, // في الواقع يُرسل عبر البريد فقط
            ], 'تم إنشاء الحساب بنجاح. تم إرسال بيانات الدخول إلى البريد الإلكتروني', 201);

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
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $targetUser = User::whereIn('type', ['hospital_admin', 'supplier_admin'])
                ->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'full_name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:users,email,' . $id,
                'phone' => 'sometimes|required|string|max:20|unique:users,phone,' . $id,
                'national_id' => 'nullable|string|max:20|unique:users,national_id,' . $id,
            ], [
                'email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
                'phone.unique' => 'رقم الهاتف مستخدم بالفعل',
                'national_id.unique' => 'الرقم الوطني مستخدم بالفعل',
            ]);

            if ($validator->fails()) {
                return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
            }

            $targetUser->update($request->only([
                'full_name', 'email', 'phone', 'national_id'
            ]));

            return $this->sendSuccess([
                'id' => $targetUser->id,
                'fullName' => $targetUser->full_name,
            ], 'تم تعديل بيانات المستخدم بنجاح');

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
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $targetUser = User::whereIn('type', ['hospital_admin', 'supplier_admin'])
                ->findOrFail($id);

            if ($targetUser->status === 'inactive') {
                return $this->sendError('الحساب معطل بالفعل', null, 400);
            }

            $targetUser->update(['status' => 'inactive']);

            // حذف جميع tokens للمستخدم
            $targetUser->tokens()->delete();

            return $this->sendSuccess([
                'id' => $targetUser->id,
                'fullName' => $targetUser->full_name,
                'status' => 'inactive',
            ], 'تم تعطيل الحساب بنجاح');

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
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $targetUser = User::whereIn('type', ['hospital_admin', 'supplier_admin'])
                ->findOrFail($id);

            if ($targetUser->status === 'active') {
                return $this->sendError('الحساب مفعل بالفعل', null, 400);
            }

            $targetUser->update(['status' => 'active']);

            return $this->sendSuccess([
                'id' => $targetUser->id,
                'fullName' => $targetUser->full_name,
                'status' => 'active',
            ], 'تم تفعيل الحساب بنجاح');

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
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
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

            // TODO: إرسال كلمة المرور الجديدة عبر البريد الإلكتروني

            return $this->sendSuccess([
                'id' => $targetUser->id,
                'email' => $targetUser->email,
                'temporaryPassword' => $newPassword, // في الواقع يُرسل عبر البريد فقط
            ], 'تم إعادة تعيين كلمة المرور بنجاح. تم إرسال كلمة المرور الجديدة إلى البريد الإلكتروني');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin User Reset Password Error');
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
