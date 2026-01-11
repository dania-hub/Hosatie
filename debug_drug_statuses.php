<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Drug;

echo "--- Drug Statuses ---\n";
$drugs = Drug::select('id', 'name', 'status')->get();
foreach ($drugs as $drug) {
    echo "ID: {$drug->id} | Name: {$drug->name} | Status: [{$drug->status}]\n";
}

echo "\n--- Constants in Drug Model ---\n";
echo "AVAILABLE: [" . Drug::STATUS_AVAILABLE . "]\n";
echo "PHASING_OUT: [" . Drug::STATUS_PHASING_OUT . "]\n";
echo "ARCHIVED: [" . Drug::STATUS_ARCHIVED . "]\n";
echo "UNAVAILABLE: [" . Drug::STATUS_UNAVAILABLE . "]\n";
