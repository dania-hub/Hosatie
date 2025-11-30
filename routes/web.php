<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
 Route::get('/', function () {
   return Inertia::render('DepartmentMa/patientListd');
});

 Route::get('/OperationLog', function () {
    return Inertia::render('DepartmentMa/operationLogd');
});

Route::get('/Statistics', function () {
    return Inertia::render('DepartmentMa/statisticsd');
});

Route::get('/SuRequests', function () {
    return Inertia::render('DepartmentMa/SuRequests');
});

Route::get('/profile', function () {
    return Inertia::render('profile');
});





// Route::get('/', function () {
//     return Inertia::render('DataEntry/patientList');
// });

// Route::get('/OperationLog', function () {
//     return Inertia::render('DataEntry/operationLog');
// });

// Route::get('/Statistics', function () {
//     return Inertia::render('DataEntry/statistics');
// });

// Route::get('/profile', function () {
//     return Inertia::render('profile');
// });



//  Route::get('/', function () {
//     return Inertia::render('Pharmacist/medicationsList');
//  });

// // Route::get('/OperationLog', function () {
// //      return Inertia::render('Doctor/operationLogd');
// //  });

// //  Route::get('/Statistics', function () {
// //      return Inertia::render('Doctor/statisticsd');
// //  });

//  Route::get('/profile', function () {
//      return Inertia::render('profile');
//  });