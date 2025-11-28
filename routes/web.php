<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
Route::get('/', function () {
    return Inertia::render('DataEntry/patientList');
});

Route::get('/OperationLog', function () {
    return Inertia::render('DataEntry/operationLog');
});

Route::get('/Statistics', function () {
    return Inertia::render('DataEntry/statistics');
});

Route::get('/profile', function () {
    return Inertia::render('profile');
});