<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Mobile\PrescriptionController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\AdminHospital\StaffController;
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public Routes (No Middleware)

// Public Route (For the employee to set password)
Route::post('activate-account', [AuthController::class, 'activateAccount']);

// Protected Admin Route (To create the user)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('admin/staff', [StaffController::class, 'store']);
});
// Mobile
Route::post('forgot-password/mobile', [ForgotPasswordController::class, 'sendOtpMobile']);
Route::post('reset-password/mobile', [ForgotPasswordController::class, 'resetPasswordMobile']);

// Dashboard
Route::post('forgot-password/dashboard', [ForgotPasswordController::class, 'sendOtpDashboard']);
Route::post('reset-password/dashboard', [ForgotPasswordController::class, 'resetPasswordDashboard']);
Route::post('login/mobile', [AuthController::class, 'loginMobile']);       // For Patient App
Route::post('login/dashboard', [AuthController::class, 'loginDashboard']); // For Web Dashboard

// Protected Routes (Token Required)
Route::middleware('auth:sanctum')->group(function () {
    
// Logout Mobile
    Route::post('logout/mobile', [AuthController::class, 'logoutMobile']);

    // Logout Dashboard
    Route::post('logout/dashboard', [AuthController::class, 'logoutDashboard']);
Route::get('profile/mobile', [AuthController::class, 'profileMobile']);
    Route::get('profile/dashboard', [AuthController::class, 'profileDashboard']);
// Update Profile Mobile
    Route::put('profile/mobile', [AuthController::class, 'updateProfileMobile']);

    // Update Profile Dashboard
    Route::put('profile/dashboard', [AuthController::class, 'updateProfileDashboard']);
 // Change Password Mobile
    Route::put('profile/password/mobile', [AuthController::class, 'changePasswordMobile']);

    // Change Password Dashboard
    Route::put('profile/password/dashboard', [AuthController::class, 'changePasswordDashboard']);    Route::get('prescriptions', [PrescriptionController::class, 'patientPrescriptions']);
    Route::post('force-change-password', [AuthController::class, 'forceChangePassword']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Add your other protected routes here...
});
