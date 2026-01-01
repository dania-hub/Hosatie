<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Auth
Route::get('/', function () { return Inertia::render('auth/login'); })->name('login');
Route::get('/forgot-password', function () { return Inertia::render('auth/ForgetPassword'); });
Route::get('/otp', function () { return Inertia::render('auth/OTP'); });
Route::get('/reset-password', function () { return Inertia::render('auth/ResetPassword'); });
Route::get('/set-password', function () { return Inertia::render('auth/SetPassword'); });

// Profile
Route::get('/profile', function () { return Inertia::render('profile'); });

// Super Admin
Route::prefix('superAdmin')->group(function () {
    Route::get('/patients', function () { return Inertia::render('superAdmin/patientListd'); });
    Route::get('/medications', function () { return Inertia::render('superAdmin/medicationsList'); });
    Route::get('/operations', function () { return Inertia::render('superAdmin/operationLog'); });
    Route::get('/all-operations', function () { return Inertia::render('superAdmin/operationLogforall'); });
    Route::get('/statistics', function () { return Inertia::render('superAdmin/statistics'); });
    Route::get('/employees', function () { return Inertia::render('superAdmin/employeesList'); });
    Route::get('/AllemployeesList', function () { return Inertia::render('superAdmin/AllemployeesList'); });
    Route::get('/requests', function () { return Inertia::render('superAdmin/Requests'); });
    Route::get('/hospital', function () { return Inertia::render('superAdmin/hospital'); });
    Route::get('/Supply', function () { return Inertia::render('superAdmin/Supply'); });
   


});

// Super Admin (alias for super-admin)
Route::prefix('super-admin')->group(function () {
    Route::get('/patients', function () { return Inertia::render('HospitalAdmin/patientListd'); });
});

// Hospital Admin
Route::prefix('admin')->group(function () {
    Route::get('/patients', function () { return Inertia::render('HospitalAdmin/patientListd'); });
    Route::get('/medications', function () { return Inertia::render('HospitalAdmin/medicationsList'); });
    Route::get('/operations', function () { return Inertia::render('HospitalAdmin/operationLog'); });
    Route::get('/all-operations', function () { return Inertia::render('HospitalAdmin/operationLogforall'); });
    Route::get('/statistics', function () { return Inertia::render('HospitalAdmin/statistics'); });
    Route::get('/employees', function () { return Inertia::render('HospitalAdmin/employeesList'); });
    Route::get('/requests', function () { return Inertia::render('HospitalAdmin/Requests'); });
    Route::get('/departments', function () { return Inertia::render('HospitalAdmin/department'); });
    Route::get('/supply-requests', function () { return Inertia::render('HospitalAdmin/SuRequests'); });
    Route::get('/transfer-requests', function () { return Inertia::render('HospitalAdmin/transRequests'); });
    Route::get('/complaints', function () { return Inertia::render('HospitalAdmin/complaints'); });
});


// Pharmacist
Route::prefix('pharmacist')->group(function () {
    Route::get('/medications', function () { return Inertia::render('Pharmacist/medicationsList'); });
    Route::get('/operations', function () { return Inertia::render('Pharmacist/operationLogd'); });
    Route::get('/patients', function () { return Inertia::render('Pharmacist/patientListd'); });
    Route::get('/statistics', function () { return Inertia::render('Pharmacist/statisticsd'); });
    Route::get('/requests', function () { return Inertia::render('Pharmacist/SuRequests'); });
});

// Doctor
Route::prefix('doctor')->group(function () {
    Route::get('/patients', function () { return Inertia::render('Doctor/patientListd'); });
    Route::get('/operations', function () { return Inertia::render('Doctor/operationLogd'); });
    Route::get('/statistics', function () { return Inertia::render('Doctor/statisticsd'); });
});

// Data Entry
Route::prefix('data-entry')->group(function () {
    Route::get('/patients', function () { return Inertia::render('DataEntry/patientList'); });
    Route::get('/operations', function () { return Inertia::render('DataEntry/operationLog'); });
    Route::get('/statistics', function () { return Inertia::render('DataEntry/statistics'); });
});

// Department Manager
Route::prefix('department')->group(function () {
    Route::get('/patients', function () { return Inertia::render('DepartmentMa/patientListd'); });
    Route::get('/operations', function () { return Inertia::render('DepartmentMa/operationLogd'); });
    Route::get('/statistics', function () { return Inertia::render('DepartmentMa/statisticsd'); });
    Route::get('/requests', function () { return Inertia::render('DepartmentMa/SuRequests'); });
});

// Department Head (alias for department-head)
Route::prefix('department-head')->group(function () {
    Route::get('/patients', function () { return Inertia::render('DepartmentMa/patientListd'); });
});

// Storekeeper
Route::prefix('storekeeper')->group(function () {
    Route::get('/medications', function () { return Inertia::render('storekeeper/medicationsList'); });
    Route::get('/operations', function () { return Inertia::render('storekeeper/operationLogd'); });
    Route::get('/requests', function () { return Inertia::render('storekeeper/Requests'); });
    Route::get('/statistics', function () { return Inertia::render('storekeeper/statistics'); });
    Route::get('/supply-requests', function () { return Inertia::render('storekeeper/SuRequests'); });
});

//Supplier
Route::prefix('Supplier')->group(function () {
    Route::get('/medications', function () { return Inertia::render('Supplier/medicationsList'); });
    Route::get('/operations', function () { return Inertia::render('Supplier/operationLogd'); });
    Route::get('/requests', function () { return Inertia::render('Supplier/Requests'); });
    Route::get('/statistics', function () { return Inertia::render('Supplier/statistics'); });
    Route::get('/supply-requests', function () { return Inertia::render('Supplier/SuRequests'); });
});