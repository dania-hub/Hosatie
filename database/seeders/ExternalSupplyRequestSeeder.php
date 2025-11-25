<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ExternalSupplyRequest;
use App\Models\Hospital;
use App\Models\Supplier;
use App\Models\User;

class ExternalSupplyRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a parent external supply request if prerequisites exist.
        // This relies on HospitalSeeder, SupplierSeeder and UserSeeder running before this seeder
        $hospital = Hospital::first();
        $supplier = Supplier::first();
        $user = User::first();

        if (! $hospital || ! $supplier || ! $user) {
            // required related data is missing; skip creating the external request
            return;
        }

        ExternalSupplyRequest::create([
            'hospital_id' => $hospital->id,
            'supplier_id' => $supplier->id,
            'requested_by' => $user->id,
            // approved_by left null, status defaults to 'pending'
        ]);
    }
}
