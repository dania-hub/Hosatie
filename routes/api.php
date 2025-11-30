<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- Auth & General ---
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForgotPasswordController;

// --- Mobile Controllers ---
use App\Http\Controllers\Mobile\HomeController;
use App\Http\Controllers\Mobile\OrderController;
use App\Http\Controllers\Mobile\NotificationController;
use App\Http\Controllers\Mobile\DrugController;
use App\Http\Controllers\Mobile\PrescriptionController;

// --- Data Entry Controllers ---
use App\Http\Controllers\DataEntry\PatientController;

// --- Admin Hospital Controllers ---
use App\Http\Controllers\AdminHospital\StaffController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ========================================================================
// 1. Public Routes (No Token Required)
// ========================================================================

// Authentication
Route::post('login/mobile', [AuthController::class, 'loginMobile']);
Route::post('login/dashboard', [AuthController::class, 'loginDashboard']);

// Password Recovery
Route::post('forgot-password/mobile', [ForgotPasswordController::class, 'sendResetOtp']);
Route::post('reset-password/mobile', [ForgotPasswordController::class, 'resetPassword']);


// ========================================================================
// 2. Protected Routes (Token Required: Sanctum)
// ========================================================================
Route::middleware('auth:sanctum')->group(function () {

    // --------------------------------------------------------------------
    // A. General Auth & Profile (Shared or Specific)
    // --------------------------------------------------------------------
    Route::post('logout/mobile', [AuthController::class, 'logoutMobile']);
    Route::post('logout/dashboard', [AuthController::class, 'logoutDashboard']);
    
    // FCM Token
    Route::post('fcm-token', [AuthController::class, 'updateFcmToken']);

    // Profile (Mobile)
    Route::get('profile/mobile', [AuthController::class, 'profileMobile']);
    Route::put('profile/mobile', [AuthController::class, 'updateProfileMobile']);
    Route::put('profile/password/mobile', [AuthController::class, 'changePasswordMobile']);
    Route::post('force-change-password', [AuthController::class, 'forceChangePassword']); // FR-3

    // Profile (Dashboard)
    Route::get('profile/dashboard', [AuthController::class, 'profileDashboard']);
    Route::put('profile/dashboard', [AuthController::class, 'updateProfileDashboard']);
    Route::put('profile/password/dashboard', [AuthController::class, 'changePasswordDashboard']);


    // --------------------------------------------------------------------
    // B. Mobile App Features (Patient)
    // --------------------------------------------------------------------
    
    // 2.1 Home Screen
    Route::get('home/mobile', [HomeController::class, 'mobileIndex']);

    // 4. Orders (Complaints & Transfers)
    Route::get('orders/mobile', [OrderController::class, 'index']);       
    Route::post('orders/mobile', [OrderController::class, 'store']);      
    Route::get('orders/mobile/{id}', [OrderController::class, 'show']);   
    Route::get('hospitals', [OrderController::class, 'hospitals']);       

    // 5. & 7. Notifications
    Route::get('notifications/mobile', [NotificationController::class, 'index']);
    Route::post('notifications/mark-as-read', [NotificationController::class, 'markAsRead']);

    // 6. Drugs & Prescriptions
    Route::get('drugs/{id}', [DrugController::class, 'show']);
    Route::get('prescriptions/history', [PrescriptionController::class, 'history']); 
    Route::get('prescriptions/current', [PrescriptionController::class, 'current']); 


    // --------------------------------------------------------------------
    // C. Data Entry Dashboard
    // --------------------------------------------------------------------
    Route::prefix('data-entry')->group(function () {
        Route::post('patients', [PatientController::class, 'store']);       
        Route::get('patients/{id}', [PatientController::class, 'show']);    
        Route::put('patients/{id}', [PatientController::class, 'update']);  
        
        Route::get('activity-log', [PatientController::class, 'activityLog']); 
        Route::get('stats', [PatientController::class, 'stats']);           
    });


    // --------------------------------------------------------------------
    // D. Admin Hospital Dashboard
    // --------------------------------------------------------------------
    Route::prefix('admin-hospital')->group(function () {
        // Example Staff Management Routes
        Route::get('staff', [StaffController::class, 'index']);
        Route::post('staff', [StaffController::class, 'store']);
        // Add other Admin Hospital routes here...
    });

});
