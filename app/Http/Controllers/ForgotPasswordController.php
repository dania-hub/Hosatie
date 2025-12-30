<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\BaseApiController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use App\Services\ResalaService; // إضافة السيرفيس الجديد

// Import the Requests
use App\Http\Requests\Mobile\ForgotMobilePasswordRequest;
use App\Http\Requests\Mobile\ResetMobilePasswordRequest;
use App\Http\Requests\ForgotDashboardPasswordRequest;
use App\Http\Requests\ResetDashboardPasswordRequest;

class ForgotPasswordController extends BaseApiController
{
    protected $resalaService;

    public function __construct()
    {
        $this->resalaService = new ResalaService();
    }

    /* -----------------------------------------------------------------
     * MOBILE (Patients - Phone) - مع Resala API
     * ----------------------------------------------------------------- */

  // في ForgotPasswordController::sendOtpMobile
public function sendOtpMobile(ForgotMobilePasswordRequest $request)
{
    try {
        $phone = $request->validated()['phone'];
        
        \Log::info('=== SENDING OTP VIA RESALA ===');
        \Log::info('Phone: ' . $phone);
        
        // ======================> 1. التحقق من وجود المستخدم في قاعدة البيانات <=======================
        \Log::info('🔍 Checking if user exists in database...');
        $user = User::where('phone', $phone)->first();
        
        if (!$user) {
            \Log::warning('❌ User not found in database for phone: ' . $phone);
            
            // محاولة العثور على الرقم بصيغ مختلفة
            $alternativeFormats = $this->getPhoneFormats($phone);
            $foundInAlternative = false;
            $alternativePhone = '';
            
            foreach ($alternativeFormats as $format) {
                $alternativeUser = User::where('phone', $format)->first();
                if ($alternativeUser) {
                    $foundInAlternative = true;
                    $alternativePhone = $format;
                    \Log::info('Found user with alternative phone format: ' . $format);
                    break;
                }
            }
            
            if (!$foundInAlternative) {
                return $this->sendError(
                    'رقم الهاتف غير مسجل في النظام. يرجى التحقق من الرقم أو إنشاء حساب جديد.',
                    ['phone_not_registered' => true],
                    404
                );
            } else {
                // إذا وجد المستخدم بصيغة مختلفة، تحديث الرقم المطلوب
                $phone = $alternativePhone;
                $user = User::where('phone', $phone)->first();
                \Log::info('✅ Using alternative phone format: ' . $phone);
            }
        }
        
        \Log::info('✅ User found: ID ' . $user->id . ', Name: ' . ($user->name ?? 'N/A'));
        
        // ======================> 2. التحقق من حالة المستخدم <=======================
        if ($user->status !== 'active' && $user->status !== 'pending_activation') {
            \Log::warning('❌ User status is not active: ' . $user->status);
            return $this->sendError(
                'حسابك غير نشط، يرجى مراجعة الإدارة.',
                ['account_inactive' => true],
                403
            );
        }
        
        // ======================> 3. أرسل الطلب لـ Resala <=======================
        \Log::info('📤 Sending OTP request to Resala...');
        $result = $this->resalaService->sendOtp($phone);  // أعدل الدالة لتعيد array مع 'success' و 'otp'
        
        if ($result['success']) {
            $realOtp = $result['otp'];
            \Log::info('✅ Received REAL OTP from Resala: ' . $realOtp);
            
            return $this->sendSuccess([
                'dev_otp' => (string) $realOtp, // الـ OTP الحقيقي من Resala
                'real_sms' => true,
                'message' => 'تم إرسال رمز التحقق إلى هاتفك',
                'note' => 'استخدم الرمز الذي وصل لهاتفك',
                'user_name' => $user->name ?? 'مستخدم',
                'user_exists' => true
            ], 'تم إرسال رمز التحقق');
        } else {
            // fallback: توليد محلي إذا فشل Resala
            $otp = rand(100000, 999999);
            $this->saveOtpToCacheManually($phone, $otp);
            
            \Log::warning('⚠️ Resala failed, using local OTP: ' . $otp);
            
            return $this->sendSuccess([
                'dev_otp' => (string) $otp,
                'real_sms' => false,
                'message' => 'فشل الإرسال، استخدم الرمز أدناه',
                'user_name' => $user->name ?? 'مستخدم',
                'user_exists' => true
            ], 'تم إنشاء رمز التحقق');
        }
        
    } catch (\Exception $e) {
        \Log::error('Error in sendOtpMobile: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        return $this->sendError('حدث خطأ في النظام. يرجى المحاولة لاحقاً.', [], 500);
    }
}

/**
 * دالة مساعدة لحفظ OTP يدوياً
 */
private function saveOtpToCacheManually($phone, $otp)
{
    $formats = $this->getPhoneFormats($phone);
    
    foreach ($formats as $format) {
        $key = 'otp_mobile_' . $format;
        Cache::put($key, $otp, 900);
    }
}

/**
 * دالة مساعدة للحصول على جميع صيغ الرقم
 */
private function getPhoneFormats($phone)
{
    $formats = [];
    
    // 1. الرقم كما هو
    $formats[] = $phone;
    
    // 2. بدون علامة + إذا كانت موجودة
    if (str_starts_with($phone, '+')) {
        $formats[] = substr($phone, 1);
    }
    
    // 3. مع 218 في البداية
    if (str_starts_with($phone, '0')) {
        $formats[] = '218' . substr($phone, 1);
        $formats[] = '+218' . substr($phone, 1);
    }
    
    // 4. بدون الصفر الأول
    if (str_starts_with($phone, '0')) {
        $formats[] = substr($phone, 1);
    }
    
    // 5. مع 0 في البداية إذا بدأ بـ 218
    if (str_starts_with($phone, '218')) {
        $formats[] = '0' . substr($phone, 3);
    }
    
    // 6. الحصول على آخر 9 أرقام
    $formats[] = substr($phone, -9);
    
    // 7. الحصول على آخر 10 أرقام
    $formats[] = substr($phone, -10);
    
    return array_unique(array_filter($formats));
}

public function resetPasswordMobile(ResetMobilePasswordRequest $request)
{
    $data = $request->validated();
    $phone = $data['phone'];
    $submittedOtp = $data['otp'];
    
    \Log::info('=== FINAL OTP VERIFICATION ===');
    \Log::info('Phone from request: ' . $phone);
    \Log::info('OTP from user: ' . $submittedOtp);
    \Log::info('Current time: ' . now()->format('Y-m-d H:i:s'));
    
    // ======================> 0. التحقق من وجود المستخدم أولاً <=======================
    \Log::info('🔍 Checking if user exists before OTP verification...');
    $user = User::where('phone', $phone)->first();
    
    if (!$user) {
        // محاولة العثور على الرقم بصيغ مختلفة
        $alternativeFormats = $this->getPhoneFormats($phone);
        $foundInAlternative = false;
        
        foreach ($alternativeFormats as $format) {
            $alternativeUser = User::where('phone', $format)->first();
            if ($alternativeUser) {
                $foundInAlternative = true;
                $phone = $format;
                $user = $alternativeUser;
                \Log::info('Found user with alternative phone format: ' . $format);
                break;
            }
        }
        
        if (!$foundInAlternative) {
            \Log::error('❌ User not found with phone: ' . $phone);
            return $this->sendError('المستخدم غير موجود.', [], 404);
        }
    }
    
    \Log::info('✅ User found: ID ' . $user->id);
    
    // ======================> 1. استخدم ResalaService للتحقق <=======================
    \Log::info('Using ResalaService::verifyOtpFromDatabase...');
    $verified = $this->resalaService->verifyOtpFromDatabase($phone, $submittedOtp);
    
    if (!$verified) {
        \Log::error('❌ OTP VERIFICATION FAILED via ResalaService');
        
        // للتشخيص: تحقق مما في جدول otp_verifications
        $records = \DB::table('otp_verifications')
            ->where('phone', 'like', '%' . $phone . '%')
            ->orWhere('phone', 'like', '%' . substr($phone, -6) . '%')
            ->get();
            
        \Log::error('All OTP records found for debugging:', ['records' => $records]);
        
        return $this->sendError('رمز التحقق غير صحيح أو منتهي الصلاحية.', [], 400);
    }
    
    \Log::info('✅ OTP VERIFIED SUCCESSFULLY via ResalaService');
    
    // ======================> 2. تحديث كلمة المرور <=======================
    $user->password = Hash::make($data['password']);
    $user->save();
    
    // ======================> 3. حذف OTP بعد الاستخدام <=======================
    $this->resalaService->deleteOtpFromDatabase($phone);
    
    \Log::info('✅ Password reset successful for user ID: ' . $user->id);
    
    return $this->sendSuccess([], 'تم إعادة تعيين كلمة المرور بنجاح.');
}

public function testResala(Request $request)
{
    $phone = $request->input('phone', '0944980957');
    
    \Log::info('Testing Resala API', ['phone' => $phone]);
    
    // التحقق من وجود المستخدم أولاً
    $user = User::where('phone', $phone)->first();
    
    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'رقم الهاتف غير مسجل في النظام',
            'phone_not_registered' => true
        ], 404);
    }
    
    $result = $this->resalaService->sendOtp($phone);
    
    if ($result['success']) {
        return response()->json([
            'success' => true,
            'message' => 'تم إرسال رسالة اختبار إلى ' . $phone,
            'otp' => $result['otp'],
            'note' => 'تحقق من هاتفك',
            'user_name' => $user->name ?? 'مستخدم'
        ]);
    } else {
        return response()->json([
            'success' => false,
            'message' => 'فشل إرسال الرسالة',
            'note' => 'تحقق من logs Laravel'
        ], 500);
    }
}

    /* -----------------------------------------------------------------
     * DASHBOARD (Staff - Email)
     * ----------------------------------------------------------------- */

  public function sendOtpDashboard(ForgotDashboardPasswordRequest $request)
{
    $email = $request->validated()['email'];
    $key = 'otp_dashboard_' . $email;

    $otp = rand(1000, 9999);
    Cache::put($key, $otp, 900);

    // الحصول على بيانات المستخدم
    $user = User::where('email', $email)->first();

    // إرسال الإيميل بالقالب الاحترافي
    try {
        Mail::send('emails.otp-reset', [
            'user' => $user,
            'otp' => $otp
        ], function ($message) use ($email) {
            $message->to($email);
            $message->subject('رمز إعادة تعيين كلمة المرور - نظام حصتي');
        });
    } catch (\Exception $e) {
        \Log::error('فشل إرسال OTP: ' . $e->getMessage());
    }

    // للتطوير فقط - يمكن حذف dev_otp لاحقاً
    return $this->sendSuccess(['dev_otp' => $otp], 'تم إرسال رمز التحقق إلى البريد الإلكتروني.');
}



    public function resetPasswordDashboard(ResetDashboardPasswordRequest $request)
    {
        $data = $request->validated();
        $key = 'otp_dashboard_' . $data['email'];

        $cachedOtp = Cache::get($key);

        if (!$cachedOtp || $cachedOtp != $data['otp']) {
            return $this->sendError('رمز التحقق غير صالح أو منتهي الصلاحية.', [], 400);
        }

        // Update User
        $user = User::where('email', $data['email'])->first();
        $user->password = Hash::make($data['password']);
        $user->save();

        Cache::forget($key);

        return $this->sendSuccess([], 'تم إعادة تعيين كلمة المرور بنجاح .');
    }
}