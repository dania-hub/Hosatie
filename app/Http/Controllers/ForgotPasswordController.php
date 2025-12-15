<?php

namespace App\Http\Controllers;

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
        
        // 1. أرسل الطلب لـ Resala (بدون OTP محلي، دع Resala يولد)
        $result = $this->resalaService->sendOtp($phone);  // أعدل الدالة لتعيد array مع 'success' و 'otp'
        
        if ($result['success']) {
            $realOtp = $result['otp'];
            \Log::info('✅ Received REAL OTP from Resala: ' . $realOtp);
            
            return $this->sendSuccess([
                'dev_otp' => (string) $realOtp, // الـ OTP الحقيقي من Resala
                'real_sms' => true,
                'message' => 'تم إرسال رمز التحقق إلى هاتفك',
                'note' => 'استخدم الرمز الذي وصل لهاتفك'
            ], 'تم إرسال رمز التحقق');
        } else {
            // fallback: توليد محلي إذا فشل Resala
            $otp = rand(100000, 999999);
            $this->saveOtpToCacheManually($phone, $otp);
            
            \Log::warning('⚠️ Resala failed, using local OTP: ' . $otp);
            
            return $this->sendSuccess([
                'dev_otp' => (string) $otp,
                'real_sms' => false,
                'message' => 'فشل الإرسال، استخدم الرمز أدناه'
            ], 'تم إنشاء رمز التحقق');
        }
        
    } catch (\Exception $e) {
        \Log::error('Error: ' . $e->getMessage());
        return $this->sendError('حدث خطأ', [], 500);
    }
}

/**
 * دالة مساعدة لحفظ OTP يدوياً
 */
private function saveOtpToCacheManually($phone, $otp)
{
    $formats = [
        $phone,
        '218' . substr($phone, 1),
        '+218' . substr($phone, 1),
        ltrim($phone, '0'),
        '0' . ltrim($phone, '218'),
    ];
    
    $formats = array_unique($formats);
    
    foreach ($formats as $format) {
        $key = 'otp_mobile_' . $format;
        Cache::put($key, $otp, 900);
    }
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
    $user = User::where('phone', $phone)->first();
    
    if (!$user) {
        \Log::error('User not found with phone: ' . $phone);
        return $this->sendError('المستخدم غير موجود.', [], 404);
    }
    
    $user->password = Hash::make($data['password']);
    $user->save();
    
    // ======================> 3. حذف OTP بعد الاستخدام <=======================
    $this->resalaService->deleteOtpFromDatabase($phone);
    
    \Log::info('✅ Password reset successful for user ID: ' . $user->id);
    
    return $this->sendSuccess([], 'تم إعادة تعيين كلمة المرور بنجاح.');
}



    /* -----------------------------------------------------------------
     * دالة جديدة: اختبار Resala مباشرة
     * ----------------------------------------------------------------- */
    
   public function testResala(Request $request)
{
    $phone = $request->input('phone', '0944980957');
    
    \Log::info('Testing Resala API', ['phone' => $phone]);
    
    $result = $this->resalaService->sendOtp($phone);
    
    if ($result['success']) {
        return response()->json([
            'success' => true,
            'message' => 'تم إرسال رسالة اختبار إلى ' . $phone,
            'otp' => $result['otp'],
            'note' => 'تحقق من هاتفك'
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

        $otp = rand(100000, 999999);
        Cache::put($key, $otp, 900);

        // Email Simulation
        return $this->sendSuccess(['dev_otp' => $otp], 'تم إرسال رمز التحقق إلى البريد الإلكتروني .');
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