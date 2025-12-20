<?php

use Illuminate\Support\Facades\Cache;

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

// --- Supplier Controllers ---
use App\Http\Controllers\Supplier\ShipmentSupplierController;
use App\Http\Controllers\Supplier\DrugSupplierController;
use App\Http\Controllers\Supplier\SupplyRequestSupplierController;
use App\Http\Controllers\Supplier\DashboardSupplierController;





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
Route::post('forgot-password/mobile', [ForgotPasswordController::class, 'sendOtpMobile']);
Route::post('reset-password/mobile', [ForgotPasswordController::class, 'resetPasswordMobile']);
// اختبار Resala API
Route::post('test-resala', [ForgotPasswordController::class, 'testResala']);
Route::post('test-resala-cache', function (Request $request) {
    $phone = $request->input('phone', '0925263736');

    $service = new \App\Services\ResalaService();

    // 1. أرسل عبر Resala (بدون OTP محلي)
    $result = $service->sendOtp($phone);

    // 2. تحقق من Cache
    $formats = [
        $phone,
        '218' . substr($phone, 1),
        '+218' . substr($phone, 1),
    ];

    $cacheResults = [];
    foreach ($formats as $format) {
        $key = 'otp_mobile_' . $format;
        $cacheResults[$key] = Cache::get($key);
    }

    return response()->json([
        'resala_sent' => $result['success'],
        'otp_from_resala' => $result['otp'] ?? null,
        'cache_check' => $cacheResults,
        'note' => 'Check if OTP is in cache'
    ]);
});
Route::get('check-database-cache', function (Request $request) {
    $phone = $request->input('phone', '0912939198');

    // جميع المفاتيح الممكنة
    $controller = new \App\Http\Controllers\ForgotPasswordController();
    $possibleKeys = $controller->generateOtpKeys($phone);

    $results = [];

    foreach ($possibleKeys as $key) {
        // 1. جرب Cache::get() العادي
        $cacheValue = Cache::get($key);

        // 2. ابحث في قاعدة البيانات
        $dbEntry = \DB::table('cache')
            ->where('key', 'like', '%' . $key . '%')
            ->first();

        $dbValue = null;
        if ($dbEntry) {
            $dbValue = @unserialize($dbEntry->value);
            if ($dbValue === false) {
                $dbValue = 'Serialized: ' . substr($dbEntry->value, 0, 50);
            }
        }

        $results[] = [
            'key' => $key,
            'cache_get' => $cacheValue,
            'db_entry' => $dbEntry ? [
                'key' => $dbEntry->key,
                'value_preview' => substr($dbEntry->value, 0, 100),
                'expiration' => $dbEntry->expiration,
                'unserialized' => $dbValue
            ] : null
        ];
    }

    return response()->json([
        'phone' => $phone,
        'cache_driver' => config('cache.default'),
        'cache_prefix' => config('cache.prefix'),
        'results' => $results
    ]);
});
Route::get('check-current-otp', function (Request $request) {
    $phone = $request->input('phone', '0912939198');

    $results = [];

    // 1. تحقق من جدول otp_verifications
    $dbRecord = \DB::table('otp_verifications')
        ->where('phone', $phone)
        ->first();

    $results['database_table'] = $dbRecord ? [
        'phone' => $dbRecord->phone,
        'otp' => $dbRecord->otp,
        'expires_at' => $dbRecord->expires_at,
        'is_valid' => $dbRecord->expires_at > now()
    ] : 'No record found';

    // 2. تحقق من Laravel Cache
    $cacheKeys = [
        'otp_mobile_' . $phone,
        'otp_mobile_218' . substr($phone, 1),
        'otp_mobile_' . ltrim($phone, '0'),
    ];

    foreach ($cacheKeys as $key) {
        $value = Cache::get($key);
        $results['cache'][$key] = $value ?: 'NULL';
    }

    // 3. آخر رسالة في سجل Resala
    $results['last_resala_message'] = 'Check Resala dashboard for: ' . $phone;

    return response()->json([
        'phone' => $phone,
        'current_time' => now()->format('Y-m-d H:i:s'),
        'results' => $results
    ]);
});
Route::get('debug-cache', function (Request $request) {
    $phone = $request->input('phone', '0925263736');

    $formats = [
        $phone,
        '218' . substr($phone, 1),
        '+218' . substr($phone, 1),
        ltrim($phone, '0'),
        '0' . ltrim($phone, '218'),
    ];

    $results = [];
    foreach ($formats as $format) {
        $key = 'otp_mobile_' . $format;
        $results[$key] = Cache::get($key);
    }

    return response()->json([
        'phone' => $phone,
        'cache_check' => $results,
        'all_cache_keys' => array_keys(Cache::getStore()->get('*') ?? [])
    ]);
});
Route::get('check-otp-cache', function (Request $request) {
    $phone = $request->input('phone');

    if (!$phone) {
        return response()->json(['error' => 'Phone required'], 400);
    }

    // جميع التنسيقات
    $formats = [
        $phone,
        '218' . substr($phone, 1),
        '+218' . substr($phone, 1),
        ltrim($phone, '0'),
        '0' . ltrim($phone, '218'),
        '218' . ltrim($phone, '09'),
    ];

    $results = [];
    foreach ($formats as $format) {
        $key = 'otp_mobile_' . $format;
        $value = Cache::get($key);
        $results[$key] = $value ? "✅ $value" : "❌ NULL";
    }

    // ابحث في جميع المفاتيح
    $allOtpKeys = [];
    try {
        if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            $redis = Cache::getRedis();
            $allKeys = $redis->keys('*');
            foreach ($allKeys as $key) {
                if (strpos($key, 'otp_mobile') !== false) {
                    $value = Cache::get(str_replace('laravel_database_', '', $key));
                    $allOtpKeys[$key] = $value;
                }
            }
        }
    } catch (\Exception $e) {
        $allOtpKeys = ['error' => $e->getMessage()];
    }

    return response()->json([
        'phone' => $phone,
        'specific_keys' => $results,
        'all_otp_keys_in_cache' => $allOtpKeys,
    ]);
});
Route::post('forgot-password/dashboard', [ForgotPasswordController::class, 'sendOtpDashboard']);
Route::post('reset-password/dashboard', [ForgotPasswordController::class, 'resetPasswordDashboard']);
// Staff Account Activation (Email Link)
Route::post('activate-account', [AuthController::class, 'activateAccount']);

// ========================================================================
// 2. Protected Routes (Token Required: Sanctum)
// ========================================================================
Route::middleware('auth:sanctum')->group(function () {

    // =====================================================================
    // A. General Auth & Profile
    // =====================================================================
    Route::post('logout/mobile', [AuthController::class, 'logoutMobile']);
    Route::post('logout/dashboard', [AuthController::class, 'logoutDashboard']);
    Route::post('fcm-token', [AuthController::class, 'updateFcmToken']);

    Route::get('profile/mobile', [AuthController::class, 'profileMobile']);
    Route::put('profile/mobile', [AuthController::class, 'updateProfileMobile']);
    Route::put('profile/password/mobile', [AuthController::class, 'changePasswordMobile']);
    Route::post('force-change-password', [AuthController::class, 'forceChangePassword']);

    Route::get('profile/dashboard', [AuthController::class, 'profileDashboard']);
    Route::put('profile/dashboard', [AuthController::class, 'updateProfileDashboard']);
    Route::put('profile/password/dashboard', [AuthController::class, 'changePasswordDashboard']);

    // =====================================================================
    // B. Mobile App
    // =====================================================================
    Route::get('home/mobile', [HomeController::class, 'mobileIndex']);

    Route::get('orders/mobile', [OrderController::class, 'index']);
    Route::post('orders/mobile', [OrderController::class, 'store']);
    Route::get('orders/mobile/{id}', [OrderController::class, 'show']);
    Route::get('hospitals', [OrderController::class, 'hospitals']);

    Route::get('notifications/mobile', [NotificationController::class, 'index']);
    Route::post('notifications/mark-as-read', [NotificationController::class, 'markAsRead']);

    Route::get('drugs/{id}', [DrugController::class, 'show']);
    Route::get('prescriptions/history', [PrescriptionController::class, 'history']);
    Route::get('prescriptions/current', [PrescriptionController::class, 'current']);

    // =====================================================================
    // C. Data Entry
    // =====================================================================
    Route::prefix('data-entry')->group(function () {
        Route::get('patients', [PatientDataEntryController::class, 'index']);
        Route::post('patients', [PatientDataEntryController::class, 'store']);
        Route::get('patients/{id}', [PatientDataEntryController::class, 'show']);
        Route::put('patients/{id}', [PatientDataEntryController::class, 'update']);
        Route::delete('patients/{id}', [PatientDataEntryController::class, 'destroy']);
        Route::get('activity-log', [PatientDataEntryController::class, 'activityLog']);
        Route::get('stats', [PatientDataEntryController::class, 'stats']);
    });

    // =====================================================================
    // D. Doctor
    // =====================================================================
    Route::prefix('doctor')->group(function () {
        Route::get('patients', [PatientDoctorController::class, 'index']);
        Route::get('patients/{id}', [PatientDoctorController::class, 'show']);
        Route::get('drugs', [DrugDoctorController::class, 'index']);
        Route::get('drug-categories', [DrugDoctorController::class, 'categories']);
        Route::post('patients/{id}/medications', [PrescriptionDoctorController::class, 'store']);
        Route::put('patients/{id}/medications/{pivotId}', [PrescriptionDoctorController::class, 'update']);
        Route::delete('patients/{id}/medications/{pivotId}', [PrescriptionDoctorController::class, 'destroy']);
        Route::get('patients/{id}/dispensations', [DispensationDoctorController::class, 'history']);
        Route::get('dashboard/stats', [DashboardDoctorController::class, 'stats']);
        Route::get('dashboard/activity-log', [DashboardDoctorController::class, 'activityLog']);
    });

    // =====================================================================
    // F. Pharmacist  ✅ (تم إصلاحها)
    // =====================================================================
    Route::prefix('pharmacist')->group(function () {
        
        Route::get('drugs', [DrugPharmacistController::class, 'index']);
        Route::get('drugs/all', [DrugPharmacistController::class, 'searchAll']);
        Route::post('drugs', [DrugPharmacistController::class, 'store']);
        Route::put('drugs/{id}', [DrugPharmacistController::class, 'update']);
        Route::delete('drugs/{id}', [DrugPharmacistController::class, 'destroy']);

        Route::get('categories', [CategoryPharmacistController::class, 'index']);
        Route::post('supply-requests', [SupplyRequestPharmacistController::class, 'store']);

        Route::get('operations', [DashboardPharmacistController::class, 'operations']);
        Route::get('patients', [PatientPharmacistController::class, 'index']);
        Route::get('patients/{fileNumber}', [PatientPharmacistController::class, 'show']);
        Route::post('dispense', [PatientPharmacistController::class, 'dispense']);

        Route::get('dashboard/stats', [DashboardPharmacistController::class, 'stats']);

        Route::get('shipments', 'App\Http\Controllers\Pharmacist\ShipmentPharmacistController@index');
        Route::get('shipments/{id}', 'App\Http\Controllers\Pharmacist\ShipmentPharmacistController@show');
        Route::post('shipments/{id}/confirm', 'App\Http\Controllers\Pharmacist\ShipmentPharmacistController@confirm');

        Route::get('drugs/low-stock', [DrugPharmacistController::class, 'lowStock']);
        Route::get('drugs/search', [DrugPharmacistController::class, 'search']);
        Route::get('patients/{fileNumber}/dispensations', [PatientPharmacistController::class, 'history']);
    });

    // =====================================================================
    // Store Keeper
    // =====================================================================
    Route::prefix('storekeeper')->group(function () {

        Route::get('drugs', [WarehouseInventoryController::class, 'index']);
        Route::get('drugs/all', [WarehouseInventoryController::class, 'allDrugs']);

        Route::post('drugs', [WarehouseInventoryController::class, 'store']);
        Route::put('drugs/{id}', [WarehouseInventoryController::class, 'update']);
        Route::delete('drugs/{id}', [WarehouseInventoryController::class, 'destroy']);

        Route::get('categories', [CategoryStoreKeeperController::class, 'index']);
        Route::post('supply-requests', [ExternalSupplyRequestController::class, 'store']);

        Route::get('operations', [AuditLogStoreKeeperController::class, 'index']);
        Route::get('shipments', [InternalSupplyRequestController::class, 'index']);
        Route::get('shipments/{id}', [InternalSupplyRequestController::class, 'show']);
        Route::post('shipments/{id}/confirm', [InternalSupplyRequestController::class, 'confirm']);
        Route::post('shipments/{id}/reject', [InternalSupplyRequestController::class, 'reject']);

        Route::get('dashboard/stats', [DashboardStoreKeeperController::class, 'stats']);
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
        Route::post('requests/{id}/respond', [ComplaintHospitalAdminController::class, 'respond']);
        Route::post('requests/{id}/reject', [ComplaintHospitalAdminController::class, 'reject']);

        Route::get('/admin-hospital/stats',  [StatsAdminHospitalController::class, 'index']);
        Route::get('/shipments', [ExternalShipmentAdminHospitalController::class, 'index']);
        Route::get('/shipments/{id}', [ExternalShipmentAdminHospitalController::class, 'show']);

        // للواجهة الأولى (المدير)
        Route::put('/shipments/{id}/confirm', [ExternalShipmentAdminHospitalController::class, 'confirm']);
        Route::put('/shipments/{id}/reject',  [ExternalShipmentAdminHospitalController::class, 'reject']);

        // للواجهة الثانية (القسم)
        Route::put('/shipments/{id}/status',           [ExternalShipmentAdminHospitalController::class, 'updateStatus']);
        Route::post('/shipments/{id}/confirm-delivery', [ExternalShipmentAdminHospitalController::class, 'confirmDelivery']);
        // Patient Transfer Requests
        Route::get('transfer-requests', [PatientTransferAdminHospitalController::class, 'index']);

        Route::put('transfer-requests/{id}/status', [PatientTransferAdminHospitalController::class, 'updateStatus']);
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
        Route::put('patients/{id}/medications/{pivotId}', [PatientDepartmentAdminController::class, 'update']);
        Route::delete('patients/{id}/medications/{pivotId}', [PatientDepartmentAdminController::class, 'destroy']);
        Route::get('patients/{id}/dispensation-history', [PatientDepartmentAdminController::class, 'dispensationHistory']);
    });
    // ========================================================================
    // F. Pharmacist Dashboard
    // ========================================================================

    // =====================================================================
    // Supplier APIs
    // =====================================================================
    Route::prefix('supplier')->middleware('auth:sanctum')->group(function () {

        // 1. Shipments Management (إدارة الشحنات)
        Route::get('shipments', [ShipmentSupplierController::class, 'index']);
        Route::get('shipments/{id}', [ShipmentSupplierController::class, 'show']);
        Route::post('shipments/{id}/confirm', [ShipmentSupplierController::class, 'confirm']);
        Route::post('shipments/{id}/reject', [ShipmentSupplierController::class, 'reject']);

        // 2. Drugs & Categories (الأدوية والفئات)
        Route::get('drugs', [DrugSupplierController::class, 'index']);
        Route::get('drugs/all', [DrugSupplierController::class, 'all']);
        Route::get('drugs/search', [DrugSupplierController::class, 'search']);
        Route::get('categories', [DrugSupplierController::class, 'categories']);

        // 3. Supply Requests (طلبات التوريد)
        Route::get('supply-requests', [SupplyRequestSupplierController::class, 'index']);
        Route::get('supply-requests/{id}', [SupplyRequestSupplierController::class, 'show']);
        Route::post('supply-requests', [SupplyRequestSupplierController::class, 'store']);

        // 4. Hospitals List (قائمة المستشفيات)
        Route::get('hospitals', [SupplyRequestSupplierController::class, 'hospitals']);
        // 5. Dashboard & Statistics (لوحة التحكم والإحصائيات)
        Route::get('dashboard/stats', [DashboardSupplierController::class, 'stats']);
        Route::get('operations', [DashboardSupplierController::class, 'operations']);
    });
});
