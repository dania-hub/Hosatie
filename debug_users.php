<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$users = User::whereIn('type', ['supplier_admin', 'super_admin'])->get(['id', 'name', 'type', 'supplier_id', 'hospital_id']);

foreach ($users as $user) {
    echo "ID: {$user->id}, Name: {$user->name}, Type: {$user->type}, Supplier: {$user->supplier_id}, Hospital: {$user->hospital_id}\n";
}
