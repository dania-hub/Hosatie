<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
// use App\Http\Controllers\Api\BaseApiController; // <--- REMOVE THIS LINE
// BaseApiController is in the same folder, so you don't need to "use" it, or just use:
use App\Http\Controllers\BaseApiController; 

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Validator;


// --- Mobile Requests ---
use App\Http\Requests\Mobile\MobileLoginRequest;
use App\Http\Requests\Mobile\ForceChangePasswordRequest;
use App\Http\Requests\Mobile\UpdateMobileProfileRequest;
use App\Http\Requests\Mobile\ChangeMobilePasswordRequest;

// --- Dashboard Requests ---
use App\Http\Requests\DashboardLoginRequest;
use App\Http\Requests\ChangeDashboardPasswordRequest;
use App\Http\Requests\UpdateDashboardProfileRequest;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuthController extends BaseApiController
{
    /**
     * Handle Exceptions Helper
     */
    protected function handleException(\Exception $e, string $logMessage)
    {
        Log::error($logMessage . ': ' . $e->getMessage());
        if (config('app.debug')) {
            return $this->sendError('خطا في الخادم ' . $e->getMessage(), [], 500);
        }
        return $this->sendError('ظهر خطا غير متوقع.', [], 500);
    }

    /**
     * Unified Login
     * Handles Mobile (Patients) and Dashboard (Staff) Login
     */
    
  /**
     * 1. MOBILE LOGIN (Patients Only)
     */
       public function loginMobile(MobileLoginRequest $request)
    {
        try {
            // استخدم البيانات التي تم التحقق منها بالفعل في Request Class
            $credentials = $request->validated();

            // Find by PHONE
            $user = User::where('phone', $credentials['phone'])->first();

            // Check Credentials
            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return $this->sendError('رقم الهاتف أو كلمة المرور غير صحيحة.', [], 401);
            }

            // Check User Type
            if ($user->type !== 'patient') {
                return $this->sendError('عذراً، هذا التطبيق مخصص للمرضى فقط.', [], 403);
            }

            // FR-3 Logic: Force Password Change
            $requiresPasswordChange = false;
            if ($user->status === 'pending_activation') {
                $requiresPasswordChange = true;
            } elseif ($user->status !== 'active') {
                return $this->sendError('حسابك غير نشط، يرجى مراجعة الإدارة.', [], 403);
            }

            // Update FCM
            if (!empty($credentials['fcm_token'])) {
                $user->fcm_token = $credentials['fcm_token'];
                $user->save();
            }

            $token = $user->createToken('mobile_app')->plainTextToken;

            $data = [
                'token' => $token,
                'user'  => new UserResource($user),
                'requires_password_change' => $requiresPasswordChange,
            ];

            return $this->sendSuccess($data, 'تم تسجيل الدخول بنجاح.');

        } catch (\Exception $e) {
            return $this->handleException($e, 'خطأ في تسجيل دخول المريض');
        }
    }


    /**
     * 2. DASHBOARD LOGIN (Staff Only)
     */
    public function loginDashboard(DashboardLoginRequest $request)
    {
        try {
            $credentials = $request->validated();

            // Find by EMAIL
            $user = User::where('email', $credentials['email'])->first();

            // Check Credentials & Type
            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return $this->sendError('البريد الإلكتروني أو كلمة المرور غير صحيحة.', [], 401);
            }

            if ($user->type === 'patient') {
                return $this->sendError('تم رفض الوصول. لا يمكن للمرضى الوصول إلى لوحة التحكم.', [], 403);
            }

            // Check Status (Must be active)
            if ($user->status !== 'active') {
                return $this->sendError('حسابك غير نشط. تواصل مع المسؤول.', [], 403);
            }

            $token = $user->createToken('web_dashboard')->plainTextToken;

            $data = [
                'token' => $token,
                'user'  => new UserResource($user),
                'role'  => $user->type,
            ];

            return $this->sendSuccess($data, 'تم تسجيل الدخول إلى لوحة التحكم بنجاح.');

        } catch (\Exception $e) {
            return $this->handleException($e, 'خطأ في تسجيل الدخول إلى لوحة التحكم');
        }
    }
    /**
 * تغيير كلمة المرور الإجباري للمستخدمين الجدد
 */
public function forceChangePassword(Request $request)
{
    try {
        $user = $request->user();

        // 1. التحقق: فقط المرضى
        if ($user->type !== 'patient') {
            return $this->sendError('هذا الإجراء مخصص للمرضى فقط.', [], 403);
        }

        // 2. التحقق: يجب أن يكون pending_activation فقط
        if ($user->status !== 'pending_activation') {
            return $this->sendError('لا تحتاج لتغيير كلمة المرور.', [], 400);
        }

        // 3. التحقق من البيانات المدخلة
        $validator = Validator::make($request->all(), [
            'new_password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'
            ],
            'new_password_confirmation' => 'required|string|min:8',
        ], [
            'new_password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.',
            'new_password.confirmed' => 'كلمات المرور غير متطابقة.',
            'new_password.regex' => 'يجب أن تحتوي كلمة المرور على حرف كبير وصغير ورقم.',
        ]);

        if ($validator->fails()) {
            return $this->sendError('لأمان أقوى، يرجى استخدام مزيج من الأرقام والحروف الكبيرة والصغيرة.', $validator->errors()->toArray(), 422);
        }

        // 4. التحقق: لا يمكن استخدام كلمة المرور القديمة
        if (Hash::check($request->new_password, $user->password)) {
            return $this->sendError('لا يمكن استخدام كلمة المرور القديمة.', [], 400);
        }

        // 5. تحديث كلمة المرور وتفعيل الحساب (بدون password_changed_at)
        $user->password = Hash::make($request->new_password);
        $user->status = 'active';
        // $user->password_changed_at = now(); // <-- احذف هذا السطر
        $user->save();

        // 6. حذف جميع التوكنات القديمة (أمان إضافي)
        $user->tokens()->delete();

        // 7. إنشاء توكن جديد
        $token = $user->createToken('mobile_app')->plainTextToken;

        return $this->sendSuccess([
            'token' => $token,
            'user' => new UserResource($user),
            'message' => 'تم تغيير كلمة المرور وتفعيل الحساب بنجاح.'
        ], 'تمت العملية بنجاح');

    } catch (\Exception $e) {
        return $this->handleException($e, 'خطأ في تغيير كلمة المرور الإجباري');
    }
}
    /**
     * FR-2: Logout
     */
     public function logoutMobile(Request $request)
    {
        try {
            // Optional: You can check user type if you want to be very strict
            // if ($request->user()->type !== 'patient') { ... }

            $request->user()->currentAccessToken()->delete();
            return $this->sendSuccess([], 'تم تسجيل الخروج  بنجاح.');

        } catch (\Exception $e) {
            return $this->handleException($e, 'خطأ في تسجيل الخروج');
        }
    }

    /**
     * 2. LOGOUT DASHBOARD
     */
    public function logoutDashboard(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return $this->sendSuccess([], 'تم تسجيل الخروج من لوحة التحكم بنجاح.');

        } catch (\Exception $e) {
            return $this->handleException($e, 'خطأ في تسجيل الخروج من لوحة التحكم');
        }
    }

    /**
     * FR-7: View Profile
     */
    /**
     * 1. MOBILE PROFILE (Patients Only)
     */
    public function profileMobile(Request $request)
    {
        try {
            $user = $request->user();

            // Security Check
            if ($user->type !== 'patient') {
                return $this->sendError('تم رفض الوصول. هذا الملف الشخصي مخصص للمرضى فقط.', [], 403);
            }

            // Use a specific resource if you want customized patient data
            // For now, we use UserResource, but you could create PatientResource later
            return $this->sendSuccess(new UserResource($user), 'تم استرجاع ملف تعريف المريض بنجاح.');

        } catch (\Exception $e) {
            return $this->handleException($e, 'خطأ في الملف الشخصي');
        }
    }

    /**
     * 2. DASHBOARD PROFILE (Staff Only)
     */
    public function profileDashboard(Request $request)
{
    try {
        $user = $request->user();

        // Security Check
        if ($user->type === 'patient') {
            return $this->sendError('تم رفض الوصول. المرضى لا يمكنهم عرض ملفات تعريف لوحة التحكم.', [], 403);
        }

        // Load hospital and department so UI can show department when applicable
        // Load hospital and department relations
        $user->load('hospital', 'department');

        // If the user is a department head but department is not set via department_id,
        // try to find the department where this user is recorded as head (head_user_id)
        if ($user->type === 'department_head' && !$user->department) {
            try {
                $department = \App\Models\Department::where('head_user_id', $user->id)->first();
                if ($department) {
                    // attach the relation dynamically so UserResource can use it
                    $user->setRelation('department', $department);
                }
            } catch (\Exception $e) {
                // ignore - if db query fails, we still return profile without department
                \Log::warning('Failed to resolve department for head user '.$user->id.': '.$e->getMessage());
            }
        }

        return $this->sendSuccess(new UserResource($user), 'Staff profile retrieved.');

    } catch (\Exception $e) {
        return $this->handleException($e, 'خطأ في ملف تعريف لوحة التحكم');
    }
}
    /**
     * FR-8: Update Profile
     */
//   **
//      * 1. UPDATE MOBILE PROFILE (Patients)
//      */
   public function updateProfileMobile(Request $request) // <-- الخطوة 1: غيرنا UpdateMobileProfileRequest إلى Request
{
    try {
        $user = $request->user();

        if ($user->type !== 'patient') {
            return response()->json(['success' => false, 'message' => 'تم رفض الوصول.'], 403);
        }

        $raw_data = $request->getContent();
        $data = json_decode($raw_data, true);

        $validator = Validator::make($data, [
            'full_name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20|unique:users,phone,' . $user->id,
            'national_id' => 'sometimes|required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'بيانات غير صالحة', 'errors' => $validator->errors()], 422);
        }

        if (isset($data['full_name'])) {
            $user->full_name = $data['full_name'];
        }
        if (isset($data['phone'])) {
            $user->phone = $data['phone'];
        }
        if (isset($data['national_id'])) {
            $user->national_id = $data['national_id'];
        }

        $user->save();

        return $this->sendSuccess(new UserResource($user), 'تم تحديث ملف تعريف المريض بنجاح.');

    } catch (\Exception $e) {
        return $this->handleException($e, 'خطأ في تحديث ملف تعريف المريض');
    }
}

    /**
     * 2. UPDATE DASHBOARD PROFILE (Staff)
     */
    public function updateProfileDashboard(UpdateDashboardProfileRequest $request)
    {
        try {
            $user = $request->user();

            if ($user->type === 'patient') {
                return $this->sendError('تم رفض الوصول. المرضى لا يمكنهم تحديث ملفات تعريف لوحة التحكم.', [], 403);
            }

            $data = $request->validated();

            $user->fill($data);
            $user->save();

            return $this->sendSuccess(new UserResource($user), 'Staff profile updated.');

        } catch (\Exception $e) {
            return $this->handleException($e, 'خطأ في تحديث ملف تعريف لوحة التحكم');
        }
    }

    /**
     * FR-9: Change Password (Standard)
     */
   /**
     * 1. CHANGE PASSWORD MOBILE (Patients)
     */
    public function changePasswordMobile(ChangeMobilePasswordRequest $request)
    {
        try {
            $user = $request->user();

            // Security Check
            if ($user->type !== 'patient') {
                return $this->sendError('تم رفض الوصول. هذا الملف الشخصي مخصص للمرضى فقط.', [], 403);
            }

            // Verify Current Password
            if (!Hash::check($request->current_password, $user->password)) {
                return $this->sendError('كلمة المرور الحالية غير صحيحة.', [], 400);
            }

            // Update
            $user->password = Hash::make($request->new_password);
            $user->save();

            return $this->sendSuccess([], 'تم تغيير كلمة المرور بنجاح .');

        } catch (\Exception $e) {
            return $this->handleException($e, 'خطأ في تغيير كلمة مرور');
        }
    }

        public function changePasswordDashboard(ChangeDashboardPasswordRequest $request)
        {
            try {
                $user = $request->user();
    
                // Security Check
                if ($user->type === 'patient') {
                    return $this->sendError('تم رفض الوصول. المرضى لا يمكنهم تغيير كلمة المرور في لوحة التحكم.', [], 403);
                }
    
                if (!Hash::check($request->current_password, $user->password)) {
                    return $this->sendError('كلمة المرور الحالية غير صحيحة.', [], 400);
                }
    
                $user->password = Hash::make($request->new_password);
                $user->save();
    
                return $this->sendSuccess([], 'تم تغيير كلمة المرور بنجاح (لوحة التحكم).');
    
            } catch (\Exception $e) {
                return $this->handleException($e, 'خطأ في تغيير كلمة مرور لوحة التحكم');
            }
        }
    
        /**
         * Activate Account & Set Password (From Email Link)
         */
            public function activateAccount(Request $request)
            {
                $request->validate([
                    'token'    => 'required|string',
                    'email'    => 'required|email|exists:users,email',
                    'password' => 'required|string|min:8|confirmed',
                ]);
        
                // 1. Check Token
              
$key = 'activation_token_' . $request->email;
$cachedToken = \Illuminate\Support\Facades\Cache::get($key);

// 1. Check Token existence and validity
if (!$cachedToken || $cachedToken !== $request->token) {
    return $this->sendError('رمز غير صالح أو منتهي الصلاحية.', [], 400);
}
        
                // 3. Activate User & Set Password
                $user = User::where('email', $request->email)->first();
                $user->password = Hash::make($request->password);
                $user->status = 'active';
                $user->save();
        
                // 4. Delete Token
              \Illuminate\Support\Facades\Cache::forget($key);
        
                return $this->sendSuccess([], 'تم تفعيل الحساب بنجاح. يمكنك الآن تسجيل الدخول.');
            }
                // 7.2 تسجيل توكن الجهاز
    public function updateFcmToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string']);

        $user = $request->user();
        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response()->json(['success' => true, 'message' => 'تم تحديث توكن FCM بنجاح.']);
    }

    }

