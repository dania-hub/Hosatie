<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- Auth & General ---
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\TestSmsController;

// --- Mobile Controllers ---
use App\Http\Controllers\Mobile\HomeController;
use App\Http\Controllers\Mobile\OrderController;
use App\Http\Controllers\Mobile\NotificationController;
use App\Http\Controllers\Mobile\DrugController;
use App\Http\Controllers\Mobile\PrescriptionController;

// --- Data Entry Controllers ---
use App\Http\Controllers\DataEntry\PatientDataEntryController;

// --- Admin Hospital Controllers ---
use App\Http\Controllers\AdminHospital\StaffController;
// --- Doctor Dashboard Controllers ---
use App\Http\Controllers\Doctor\DashboardDoctorController;
use App\Http\Controllers\Doctor\PatientDoctorController;
use App\Http\Controllers\Doctor\PrescriptionDoctorController;
use App\Http\Controllers\Doctor\DispensationDoctorController;
use App\Http\Controllers\Doctor\LookupDoctorController;
// --- Department Admin Controllers ---
use App\Http\Controllers\DepartmentAdmin\CategoryDepartmentAdminController;
use App\Http\Controllers\DepartmentAdmin\DrugDepartmentAdminController;
use App\Http\Controllers\DepartmentAdmin\ShipmentDepartmentAdminController;
use App\Http\Controllers\DepartmentAdmin\SupplyRequestControllerDepartmentAdmin;
use App\Http\Controllers\DepartmentAdmin\DashboardDepartmentAdminController;
use App\Http\Controllers\DepartmentAdmin\PatientDepartmentAdminController;
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
Route::get('test-sms', [TestSmsController::class, 'sendTest']);
// Password Recovery
Route::post('forgot-password/mobile', [ForgotPasswordController::class, 'sendResetOtp']);
Route::post('reset-password/mobile', [ForgotPasswordController::class, 'resetPassword']);
Route::post('forgot-password/dashboard', [ForgotPasswordController::class, 'sendOtpDashboard']);
Route::post('reset-password/dashboard', [ForgotPasswordController::class, 'resetPasswordDashboard']);
// Staff Account Activation (Email Link)
Route::post('activate-account', [AuthController::class, 'activateAccount']);

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
             Route::get('patients', [PatientDataEntryController::class, 'index']); // NEW: List
            Route::post('patients', [PatientDataEntryController::class, 'store']);       
            Route::get('patients/{id}', [PatientDataEntryController::class, 'show']);    
            Route::put('patients/{id}', [PatientDataEntryController::class, 'update']);  
            
            Route::get('activity-log', [PatientDataEntryController::class, 'activityLog']); 
            Route::get('stats', [PatientDataEntryController::class, 'stats']);  
            Route::delete('patients/{id}', [PatientDataEntryController::class, 'destroy']); // NEW: Delete
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
     
// Doctor Dashboard Routes
Route::prefix('doctor')->middleware(['auth:sanctum'])->group(function () {
    
    // 1. Patients
    Route::get('patients', [PatientDoctorController::class, 'index']); // List
    Route::get('patients/{id}', [PatientDoctorController::class, 'show']); // Single Details (NEW)

    // 2. Lookups (For Dropdowns)
    Route::get('drugs', [LookupDoctorController::class, 'drugs']);
    Route::get('drug-categories', [LookupDoctorController::class, 'categories']);

    // 3. Manage Drugs (Add, Edit, Delete)
    Route::post('patients/{id}/medications', [PrescriptionDoctorController::class, 'store']);
    Route::put('patients/{id}/medications/{pivotId}', [PrescriptionDoctorController::class, 'update']);
    Route::delete('patients/{id}/medications/{pivotId}', [PrescriptionDoctorController::class, 'destroy']);

    // 4. Dispensing History
    Route::get('patients/{id}/dispensations', [DispensationDoctorController::class, 'history']);
    
    // 5. Dashboard
    Route::get('dashboard/stats', [DashboardDoctorController::class, 'stats']);
    Route::get('dashboard/activity-log', [DashboardDoctorController::class, 'activityLog']);
});
    // ========================================================================
    // E. Department Admin Dashboard
    // ========================================================================
    Route::prefix('department-admin')->group(function () {
        
        // 1. Categories & Drugs
        Route::get('categories', [CategoryDepartmentAdminController::class, 'index']);
        Route::get('drugs', [DrugDepartmentAdminController::class, 'index']);
        Route::get('drugs/search', [DrugDepartmentAdminController::class, 'search']);

        // 2. Shipments (Incoming)
        Route::get('shipments', [ShipmentDepartmentAdminController::class, 'index']);
        Route::get('shipments/{id}', [ShipmentDepartmentAdminController::class, 'show']);
        Route::post('shipments/{id}/confirm', [ShipmentDepartmentAdminController::class, 'confirm']);

        // 3. Supply Requests (Outgoing)
        Route::post('supply-requests', [SupplyRequestControllerDepartmentAdmin::class, 'store']);

        // 4. Dashboard Stats
        Route::get('dashboard/stats', [DashboardDepartmentAdminController::class, 'stats']);
    // Operations Log
    Route::get('operations', [DashboardDepartmentAdminController::class, 'operations']);
    // Patient Management (For View 2)
    Route::get('patients', [PatientDepartmentAdminController::class, 'index']);
    Route::get('patients/{id}', [PatientDepartmentAdminController::class, 'show']);
    Route::put('patients/{id}/medications', [PatientDepartmentAdminController::class, 'updateMedications']);
    Route::get('patients/{id}/dispensation-history', [PatientDepartmentAdminController::class, 'dispensationHistory']);

    });



});
