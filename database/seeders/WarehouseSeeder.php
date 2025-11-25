<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run()
    {
        Warehouse::create([
            'hospital_id' => 1,
            'name' => 'مخزن مستشفى طرابلس الجامعي',
            'status' => 'active',
        ]);

        Warehouse::create([
            'hospital_id' => 2,
            'name' => 'المخزن الرئيسي - بنغازي',
            'status' => 'active',
        ]);
    }
}
