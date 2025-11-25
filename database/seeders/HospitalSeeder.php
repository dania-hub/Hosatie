<?php

namespace Database\Seeders;

use App\Models\Hospital;
use Illuminate\Database\Seeder;

class HospitalSeeder extends Seeder
{
    public function run()
    {
        Hospital::create([
            'supplier_id' => 1, // Assuming Supplier ID 1 exists
            'name' => 'مستشفى طرابلس المركزي',
            'code' => 'HOSP-001',
            'type' => 'hospital',
            'city' => 'طرابلس',
            'address' => 'الظهرة',
            'phone' => '0213334455',
            'status' => 'active',
        ]);

        Hospital::create([
            'supplier_id' => 2,
            'name' => 'مركز بنغازي الطبي',
            'code' => 'HOSP-002',
            'type' => 'hospital',
            'city' => 'بنغازي',
            'address' => 'الهواري',
            'phone' => '0612223344',
            'status' => 'active',
        ]);
    }
}
