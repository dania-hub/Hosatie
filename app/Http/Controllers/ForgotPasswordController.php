<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseApiController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

// Import the Requests
use App\Http\Requests\Mobile\ForgotMobilePasswordRequest;
use App\Http\Requests\Mobile\ResetMobilePasswordRequest;
use App\Http\Requests\ForgotDashboardPasswordRequest;
use App\Http\Requests\ResetDashboardPasswordRequest;

class ForgotPasswordController extends BaseApiController
{
    /* -----------------------------------------------------------------
     * MOBILE (Patients - Phone)
     * ----------------------------------------------------------------- */

    public function sendOtpMobile(ForgotMobilePasswordRequest $request)
    {
        $phone = $request->validated()['phone'];
        $key = 'otp_mobile_' . $phone;

        $otp = rand(100000, 999999);
        Cache::put($key, $otp, 900); // 15 Minutes

        // SMS Simulation
        return $this->sendSuccess(['dev_otp' => $otp], 'OTP sent to phone (Mobile).');
    }

    public function resetPasswordMobile(ResetMobilePasswordRequest $request)
    {
        $data = $request->validated();
        $key = 'otp_mobile_' . $data['phone'];

        $cachedOtp = Cache::get($key);

        if (!$cachedOtp || $cachedOtp != $data['otp']) {
            return $this->sendError('Invalid or expired OTP.', [], 400);
        }

        // Update User
        $user = User::where('phone', $data['phone'])->first();
        $user->password = Hash::make($data['password']);
        $user->save();

        Cache::forget($key); // Clear OTP

        return $this->sendSuccess([], 'Password reset successfully (Mobile).');
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
        return $this->sendSuccess(['dev_otp' => $otp], 'OTP sent to email (Dashboard).');
    }

    public function resetPasswordDashboard(ResetDashboardPasswordRequest $request)
    {
        $data = $request->validated();
        $key = 'otp_dashboard_' . $data['email'];

        $cachedOtp = Cache::get($key);

        if (!$cachedOtp || $cachedOtp != $data['otp']) {
            return $this->sendError('Invalid or expired OTP.', [], 400);
        }

        // Update User
        $user = User::where('email', $data['email'])->first();
        $user->password = Hash::make($data['password']);
        $user->save();

        Cache::forget($key);

        return $this->sendSuccess([], 'Password reset successfully (Dashboard).');
    }
}
