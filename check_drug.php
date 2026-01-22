<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$drug = \App\Models\Drug::where('name', 'like', '%أسبرين%')->first();
if ($drug) {
    echo "ID: " . $drug->id . "\n";
    echo "Name: " . $drug->name . "\n";
    echo "Unit: " . $drug->unit . "\n";
    echo "Units per box: " . $drug->units_per_box . "\n";
} else {
    echo "Drug not found\n";
}
