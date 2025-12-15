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
use App\Http\Controllers\AdminHospital\ComplaintHospitalAdminController;
use App\Http\Controllers\AdminHospital\DepartmentHospitalAdminController;
use App\Http\Controllers\AdminHospital\OperationLogController;
use App\Http\Controllers\AdminHospital\PatientHospitalAdminController;
use App\Http\Controllers\AdminHospital\ExternalShipmentAdminHospitalController;
use App\Http\Controllers\AdminHospital\StatsAdminHospitalController;
use App\Http\Controllers\AdminHospital\PatientTransferAdminHospitalController;

// --- Doctor Dashboard Controllers ---
use App\Http\Controllers\Doctor\DashboardDoctorController;
use App\Http\Controllers\Doctor\PatientDoctorController;
use App\Http\Controllers\Doctor\PrescriptionDoctorController;
use App\Http\Controllers\Doctor\DispensationDoctorController;
use App\Http\Controllers\Doctor\LookupDoctorController;
use App\Http\Controllers\Doctor\DrugDoctorController;
// --- Department Admin Controllers ---
use App\Http\Controllers\DepartmentAdmin\CategoryDepartmentAdminController;
use App\Http\Controllers\DepartmentAdmin\DrugDepartmentAdminController;
use App\Http\Controllers\DepartmentAdmin\ShipmentDepartmentAdminController;
use App\Http\Controllers\DepartmentAdmin\SupplyRequestControllerDepartmentAdmin;
use App\Http\Controllers\DepartmentAdmin\DashboardDepartmentAdminController;
use App\Http\Controllers\DepartmentAdmin\PatientDepartmentAdminController;
// --- Pharmacist Controllers ---
use App\Http\Controllers\Pharmacist\DrugPharmacistController;
use App\Http\Controllers\Pharmacist\CategoryPharmacistController;
use App\Http\Controllers\Pharmacist\SupplyRequestPharmacistController;
use App\Http\Controllers\Pharmacist\DashboardPharmacistController;
use App\Http\Controllers\Pharmacist\PatientPharmacistController;
// -- Store Keeper ---
use App\Http\Controllers\StoreKeeper\WarehouseInventoryController;
use App\Http\Controllers\StoreKeeper\CategoryStoreKeeperController;
use App\Http\Controllers\StoreKeeper\ExternalSupplyRequestController;
use App\Http\Controllers\StoreKeeper\AuditLogStoreKeeperController;
use App\Http\Controllers\StoreKeeper\InternalSupplyRequestController;
use App\Http\Controllers\StoreKeeper\DashboardStoreKeeperController;




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

  Route::get('/departments', [DepartmentHospitalAdminController::class, 'index']);
    Route::post('/departments', [DepartmentHospitalAdminController::class, 'store']);
    Route::put('/departments/{id}', [DepartmentHospitalAdminController::class, 'update']);
    Route::patch('/departments/{id}/toggle-status', [DepartmentHospitalAdminController::class, 'toggleStatus']);

    Route::get('/employees', [DepartmentHospitalAdminController::class, 'employees']);

    Route::get('/operations', [OperationLogController::class, 'index']);

   Route::get('/patients', [PatientHospitalAdminController::class, 'index']);
    Route::get('/patients/{id}', [PatientHospitalAdminController::class, 'show']);
    Route::put('/patients/{id}/medications', [PatientHospitalAdminController::class, 'updateMedications']);
    Route::get('/patients/{id}/dispensation-history', [PatientHospitalAdminController::class, 'dispensationHistory']);

    Route::get('/shipments', [ExternalShipmentAdminHospitalController::class, 'index']);
    Route::get('/shipments/{id}', [ExternalShipmentAdminHospitalController::class, 'show']);
    Route::put('/shipments/{id}/confirm', [ExternalShipmentAdminHospitalController::class, 'confirm']);
    Route::put('/shipments/{id}/reject',  [ExternalShipmentAdminHospitalController::class, 'reject']);
        // Complaint Management Routes
         Route::get('requests',              [ComplaintHospitalAdminController::class, 'index']);
        Route::get('requests/{id}',         [ComplaintHospitalAdminController::class, 'show']);
        Route::post('requests/{id}/respond',[ComplaintHospitalAdminController::class, 'respond']);
        Route::post('requests/{id}/reject', [ComplaintHospitalAdminController::class, 'reject']);

        Route::get( '/admin-hospital/stats',  [StatsAdminHospitalController::class, 'index']);
         Route::get('/shipments', [ExternalShipmentAdminHospitalController::class, 'index']);
    Route::get('/shipments/{id}', [ExternalShipmentAdminHospitalController::class, 'show']);

    // للواجهة الأولى (المدير)
    Route::put('/shipments/{id}/confirm', [ExternalShipmentAdminHospitalController::class, 'confirm']);
    Route::put('/shipments/{id}/reject',  [ExternalShipmentAdminHospitalController::class, 'reject']);

    // للواجهة الثانية (القسم)
    Route::put('/shipments/{id}/status',           [ExternalShipmentAdminHospitalController::class, 'updateStatus']);
    Route::post('/shipments/{id}/confirm-delivery',[ExternalShipmentAdminHospitalController::class, 'confirmDelivery']);
        // Patient Transfer Requests
        Route::get('transfer-requests', [PatientTransferAdminHospitalController::class, 'index']);
        
Route::put('transfer-requests/{id}/status', [PatientTransferAdminHospitalController::class, 'updateStatus']);
    });
        
   
     
// Doctor Dashboard Routes
Route::prefix('doctor')->middleware(['auth:sanctum'])->group(function () {
    
    // 1. Patients
    Route::get('patients', [PatientDoctorController::class, 'index']); // List
    Route::get('patients/{id}', [PatientDoctorController::class, 'show']); // Single Details (NEW)

    // 2. Drugs & Categories (UPDATED - Using DrugDoctorController)
    Route::get('drugs', [DrugDoctorController::class, 'index']);
    Route::get('drug-categories', [DrugDoctorController::class, 'categories']);

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
    // ========================================================================
    // F. Pharmacist Dashboard
    // ========================================================================

 Route::prefix('pharmacist')->group(function () {
    
    // Inventory Management
    Route::get('drugs', [DrugPharmacistController::class, 'index']); // List inventory
    Route::get('drugs/all', [DrugPharmacistController::class, 'searchAll']); // Search global
    Route::post('drugs', [DrugPharmacistController::class, 'store']); // Add to inventory
    Route::put('drugs/{id}', [DrugPharmacistController::class, 'update']); // Update stock
    Route::delete('drugs/{id}', [DrugPharmacistController::class, 'destroy']); // Remove

    // Categories
    Route::get('categories', [CategoryPharmacistController::class, 'index']);

    // Supply Requests
    Route::post('supply-requests', [SupplyRequestPharmacistController::class, 'store']);
 // Operations Log
    Route::get('operations', [DashboardPharmacistController::class, 'operations']);
 // Patient Management
    Route::get('patients', [PatientPharmacistController::class, 'index']);
    
    // Dispensing Action
    Route::post('dispense', [PatientPharmacistController::class, 'dispense']);
 // Dashboard Stats
    Route::get('dashboard/stats', [DashboardPharmacistController::class, 'stats']);
 // Shipment Management
    Route::get('shipments', 'App\Http\Controllers\Pharmacist\ShipmentPharmacistController@index');
    Route::get('shipments/{id}', 'App\Http\Controllers\Pharmacist\ShipmentPharmacistController@show');
    Route::post('shipments/{id}/confirm', 'App\Http\Controllers\Pharmacist\ShipmentPharmacistController@confirm');
      // 1. Low Stock
    Route::get('drugs/low-stock', [DrugPharmacistController::class, 'lowStock']);
    
    // 2. Specific Search
    Route::get('drugs/search', [DrugPharmacistController::class, 'search']);

    // 3. Patient History
    Route::get('patients/{fileNumber}/dispensations', [PatientPharmacistController::class, 'history']);
});

 // ونفلتر بالـ type داخل الكود


       Route::prefix('storekeeper') ->group(function () {  

       
       
        // 1) عرض مخزون مخزن المستشفى (warehouse inventory)
        Route::get('drugs',        [WarehouseInventoryController::class, 'index']);    // قائمة الأدوية وكمياتها
        Route::get('drugs/all',    [WarehouseInventoryController::class, 'allDrugs']); // كل الأدوية للتحديد في الطلب

        // هذه الثلاثة موجودة فقط لأن الواجهة تستدعيها حاليًا
        Route::post('drugs',       [WarehouseInventoryController::class, 'store']);    // (اختياري/مؤقت)
        Route::put('drugs/{id}',   [WarehouseInventoryController::class, 'update']);   // (اختياري/مؤقت)
        Route::delete('drugs/{id}',[WarehouseInventoryController::class, 'destroy']);  // (اختياري/مؤقت)

        // 2) التصنيفات مستخرجة من عمود في جدول drug
        Route::get('categories',   [CategoryStoreKeeperController::class, 'index']);

        // 3) طلب توريد خارجي (External Supply Request)
        // من store keeper إلى إدارة المستشفى (التي تتعامل مع المورد)
        Route::post('supply-requests', [ExternalSupplyRequestController::class, 'store']);
         Route::get('operations', [AuditLogStoreKeeperController::class, 'index']);
  Route::get('shipments',               [InternalSupplyRequestController::class, 'index']);
        Route::get('shipments/{id}',          [InternalSupplyRequestController::class, 'show']);
        Route::post('shipments/{id}/confirm', [InternalSupplyRequestController::class, 'confirm']);
        Route::post('shipments/{id}/reject',  [InternalSupplyRequestController::class, 'reject']);
                Route::get('dashboard/stats', [DashboardStoreKeeperController::class, 'stats']);

 });
 });
 