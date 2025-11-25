<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run()
    {
        Supplier::create([
            'name' => 'شركة الأدوية الوطنية',
            'code' => 'SUP-001',
            'phone' => '0911234567',
            'address' => 'شارع الجمهورية',
            'city' => 'طرابلس',
            'status' => 'active',
        ]);

        Supplier::create([
            'name' => 'شركة الشفاء الطبية',
            'code' => 'SUP-002',
            'phone' => '0929876543',
            'address' => 'حي الاندلس',
            'city' => 'طرابلس',
            'status' => 'active',
        ]);
    }
}
