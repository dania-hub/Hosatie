<?php

namespace Database\Seeders;

use App\Models\Dispensing;
use App\Models\Prescription;
use App\Models\Drug;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Seeder;

class DispensingSeeder extends Seeder
{
    public function run()
    {
        $prescription = Prescription::first();
        $drug = Drug::first();
        $pharmacist = User::first();
        $pharmacy = Pharmacy::first();

        if (! $prescription || ! $drug || ! $pharmacist || ! $pharmacy) {
            // Required related data missing; skip to avoid FK/default errors
            return;
        }

        Dispensing::create([
            'prescription_id'   => $prescription->id,
            'patient_id'        => $prescription->patient_id ?? $pharmacist->id,
            'pharmacist_id'     => $pharmacist->id,
            'pharmacy_id'       => $pharmacy->id,
            'drug_id'           => $drug->id,
            'dispense_month'    => date('Y-m-01'),
            'quantity_dispensed'=> 30,
            'reverted'          => false,
            'created_at'        => now(),
        ]);
    }
}
