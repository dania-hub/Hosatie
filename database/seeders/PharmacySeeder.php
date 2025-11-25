<?php

namespace Database\Seeders;

use App\Models\Pharmacy;
use Illuminate\Database\Seeder;

class PharmacySeeder extends Seeder
{
    public function run()
    {
        Pharmacy::create([
            'hospital_id' => 1,
            'name' => 'صيدلية الطوارئ',
            'status' => 'active',
        ]);

        Pharmacy::create([
            'hospital_id' => 1,
            'name' => 'الصيدلية الخارجية',
            'status' => 'active',
        ]);
    }
}
