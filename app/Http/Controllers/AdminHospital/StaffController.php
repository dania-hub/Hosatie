<?php

namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Mail\StaffActivationMail;
use Illuminate\Support\Facades\Log;

class StaffController extends BaseApiController
{
    /**
     * Admin creates a new Staff member
     */
    public function store(Request $request)
    {
        // 1. Validate
        $request->validate([
            'full_name' => 'required|string',
            'email'     => 'required|email|unique:users,email',
            'role'      => 'required|string' // e.g., 'pharmacist', 'doctor'
        ]);

        try {
            DB::beginTransaction();

            // 2. Create User (Inactive, No Password yet)
            $user = User::create([
                'full_name' => $request->full_name,
                'email'     => $request->email,
                'type'      => $request->role, // doctor, pharmacist, etc.
                'status'    => 'pending_activation',
                'password'  => '', // Empty for now
            ]);

            // 3. Generate Secure Token
            $token = Str::random(60);

            // 4. Store Token in password_resets table
           $key = 'activation_token_' . $user->email;
\Illuminate\Support\Facades\Cache::put($key, $token, 86400); 
            // 5. Send Email
            Mail::to($user->email)->send(new StaffActivationMail($token, $user->email, $user->full_name));

            DB::commit();

            return $this->sendSuccess($user, 'Staff member created. Activation email sent.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleException($e, 'Create Staff Error');
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
