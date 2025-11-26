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
            return $this->sendError('Server Error: ' . $e->getMessage(), [], 500);
        }
        return $this->sendError('An unexpected error occurred. Please try again.', [], 500);
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
            $credentials = $request->validate([
                'phone' => 'required|string',
                'password' => 'required|string',
                'fcm_token' => 'nullable|string',
            ]);
            // Find by PHONE
            $user = User::where('phone', $credentials['phone'])->first();

            // Check Credentials & Type
            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return $this->sendError('Invalid phone or password.', [], 401);
            }

            if ($user->type !== 'patient') {
                return $this->sendError('Access denied. Only patients can use the mobile app.', [], 403);
            }

            // FR-3 Logic: Force Password Change
            $requiresPasswordChange = false;
            if ($user->status === 'pending_activation') {
                $requiresPasswordChange = true;
            } elseif ($user->status !== 'active') {
                return $this->sendError('Your account is inactive.', [], 403);
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

            return $this->sendSuccess($data, 'Mobile login successful.');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Mobile Login Error');
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
                return $this->sendError('Invalid email or password.', [], 401);
            }

            if ($user->type === 'patient') {
                return $this->sendError('Access denied. Patients cannot access the dashboard.', [], 403);
            }

            // Check Status (Must be active)
            if ($user->status !== 'active') {
                return $this->sendError('Your account is inactive. Contact Admin.', [], 403);
            }

            $token = $user->createToken('web_dashboard')->plainTextToken;

            $data = [
                'token' => $token,
                'user'  => new UserResource($user),
                'role'  => $user->type,
            ];

            return $this->sendSuccess($data, 'Dashboard login successful.');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Dashboard Login Error');
        }
    }
    public function forceChangePassword(ForceChangePasswordRequest $request)
    {
        try {
            $user = $request->user();

            // Security: Only patients
            if ($user->type !== 'patient') {
                return $this->sendError('This action is reserved for patients.', [], 403);
            }

            // Security: Only if pending
            if ($user->status === 'active') {
                return $this->sendError('Your account is already active.', [], 400);
            }

            // Update Password & Activate
            $user->password = Hash::make($request->new_password);
            $user->status = 'active';
            $user->save();

            return $this->sendSuccess(new UserResource($user), 'Password changed and account activated successfully.');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Force Change Password Error');
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
            return $this->sendSuccess([], 'Mobile logout successful.');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Mobile Logout Error');
        }
    }

    /**
     * 2. LOGOUT DASHBOARD
     */
    public function logoutDashboard(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            return $this->sendSuccess([], 'Dashboard logout successful.');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Dashboard Logout Error');
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
                return $this->sendError('Access denied. This profile is for patients only.', [], 403);
            }

            // Use a specific resource if you want customized patient data
            // For now, we use UserResource, but you could create PatientResource later
            return $this->sendSuccess(new UserResource($user), 'Patient profile retrieved.');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Mobile Profile Error');
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
                return $this->sendError('Access denied. Patients cannot view dashboard profiles.', [], 403);
            }

            return $this->sendSuccess(new UserResource($user), 'Staff profile retrieved.');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Dashboard Profile Error');
        }
    }
    /**
     * FR-8: Update Profile
     */
//   **
//      * 1. UPDATE MOBILE PROFILE (Patients)
//      */
    public function updateProfileMobile(UpdateMobileProfileRequest $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'patient') {
                return $this->sendError('Access denied.', [], 403);
            }

            // Using the generic Request here; consider creating App\Http\Requests\Mobile\UpdateMobileProfileRequest
            // and importing it if you want automatic validation via $request->validated().
            $data = $request->all();
            
            // Secure update
            $user->fill($data);
            $user->save();

            return $this->sendSuccess(new UserResource($user), 'Patient profile updated.');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Update Mobile Profile Error');
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
                return $this->sendError('Access denied.', [], 403);
            }

            $data = $request->validated();

            $user->fill($data);
            $user->save();

            return $this->sendSuccess(new UserResource($user), 'Staff profile updated.');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Update Dashboard Profile Error');
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
                return $this->sendError('Access denied.', [], 403);
            }

            // Verify Current Password
            if (!Hash::check($request->current_password, $user->password)) {
                return $this->sendError('كلمة المرور الحالية غير صحيحة.', [], 400);
            }

            // Update
            $user->password = Hash::make($request->new_password);
            $user->save();

            return $this->sendSuccess([], 'Password changed successfully (Mobile).');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Change Mobile Password Error');
        }
    }

        public function changePasswordDashboard(ChangeDashboardPasswordRequest $request)
        {
            try {
                $user = $request->user();
    
                // Security Check
                if ($user->type === 'patient') {
                    return $this->sendError('Access denied.', [], 403);
                }
    
                if (!Hash::check($request->current_password, $user->password)) {
                    return $this->sendError('Current password is incorrect.', [], 400);
                }
    
                $user->password = Hash::make($request->new_password);
                $user->save();
    
                return $this->sendSuccess([], 'Password changed successfully (Dashboard).');
    
            } catch (\Exception $e) {
                return $this->handleException($e, 'Change Dashboard Password Error');
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
    return $this->sendError('Invalid or expired token.', [], 400);
}
        
                // 3. Activate User & Set Password
                $user = User::where('email', $request->email)->first();
                $user->password = Hash::make($request->password);
                $user->status = 'active';
                $user->save();
        
                // 4. Delete Token
              \Illuminate\Support\Facades\Cache::forget($key);
        
                return $this->sendSuccess([], 'Account activated successfully. You can now login.');
            }
    }

