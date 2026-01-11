<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ExternalSupplyRequest;
use App\Models\Hospital;
use App\Models\User;

echo "--- External Supply Requests Summary ---\n";
$requests = ExternalSupplyRequest::select('id', 'hospital_id', 'supplier_id', 'status', 'created_at')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

foreach ($requests as $req) {
    $hospitalName = Hospital::find($req->hospital_id)->name ?? 'Unknown';
    $supplierName = $req->supplier_id ? (\App\Models\Supplier::find($req->supplier_id)->name ?? 'Unknown') : 'None (Unassigned)';
    echo "ID: {$req->id} | Hospital: {$hospitalName} | Supplier: {$supplierName} | Status: {$req->status} | Created: {$req->created_at}\n";
}

echo "\n--- Hospital-Supplier Linkage ---\n";
$hospitals = Hospital::all();
foreach ($hospitals as $h) {
    $sName = $h->supplier_id ? (\App\Models\Supplier::find($h->supplier_id)->name ?? 'Unknown') : 'No Supplier Assigned';
    echo "Hospital: {$h->name} (ID: {$h->id}) -> Supplier: {$sName} (ID: {$h->supplier_id})\n";
}

echo "\n--- Supplier Admin Users ---\n";
$suppliers = User::where('type', 'supplier_admin')->get();
foreach ($suppliers as $s) {
    echo "User: {$s->full_name} (ID: {$s->id}) | SupplierID: {$s->supplier_id}\n";
}
