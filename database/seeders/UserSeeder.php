<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Super Admin (idempotent)
        User::updateOrCreate(
            ['email' => 'admin@hosatie.ly'],
            [
                'full_name' => 'المدير العام',
                'phone' => '0910000001',
                'password' => Hash::make('password'),
                'type' => 'super_admin',
                'status' => 'active',
            ]
        );

        // Hospital Admin (idempotent)
        User::updateOrCreate(
            ['email' => 'hosp_admin@hosatie.ly'],
            [
                'full_name' => 'مدير مستشفى طرابلس',
                'phone' => '0910000002',
                'password' => Hash::make('password'),
                'type' => 'hospital_admin',
                'hospital_id' => 1,
                'status' => 'active',
            ]
        );

        // Pharmacist (idempotent)
        User::updateOrCreate(
            ['email' => 'pharma@hosatie.ly'],
            [
                'full_name' => 'الصيدلي محمد',
                'phone' => '0910000003',
                'password' => Hash::make('password'),
                'type' => 'pharmacist',
                'hospital_id' => 1,
                'pharmacy_id' => 1,
                'status' => 'active',
            ]
        );

        // Warehouse Manager (idempotent)
        User::updateOrCreate(
            ['email' => 'warehouse@hosatie.ly'],
            [
                'full_name' => 'مسؤول المخزن',
                'phone' => '0910000007',
                'password' => Hash::make('password'),
                'type' => 'warehouse_manager',
                'warehouse_id' => 1,
                'status' => 'active',
            ]
        );

        // Supplier Admin (idempotent)
        User::updateOrCreate(
            ['email' => 'supplier@hosatie.ly'],
            [
                'full_name' => 'مسؤول المورد',
                'phone' => '0910000008',
                'password' => Hash::make('password'),
                'type' => 'supplier_admin',
                'supplier_id' => 1,
                'status' => 'active',
            ]
        );
        
         // Doctor (idempotent)
        User::updateOrCreate(
            ['email' => 'doctor@hosatie.ly'],
            [
                'full_name' => 'الدكتور علي',
                'phone' => '0910000004',
                'password' => Hash::make('password'),
                'type' => 'doctor',
                'hospital_id' => 1,
                'status' => 'active',
            ]
        );

        // Patient (idempotent) - lookup by national_id to avoid unique constraint conflicts
        User::updateOrCreate(
            ['national_id' => '119990001234'],
            [
                'email' => 'patient@hosatie.ly',
                'full_name' => 'المريض سالم',
                'phone' => '0910000005',
                'national_id' => '119990001234',
                'password' => Hash::make('password'),
                'type' => 'patient',
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['national_id' => '119990001237'],
            [
                'email' => 'data@hosatie.ly',
                'full_name' => 'مدخل البيانات احمد',
                'phone' => '0910000000',
                'national_id' => '119990001237',
                'password' => Hash::make('password'),
                'type' => 'data_entry',
                'status' => 'active',
            ]
        );

        // Department Admin (idempotent)
        User::updateOrCreate(
            ['email' => 'dept_admin@hosatie.ly'],
            [
                'full_name' => 'مدير القسم',
                'phone' => '0910000006',
                'password' => Hash::make('password'),
                'type' => 'department_head',
                'hospital_id' => 1,
                'status' => 'active',
            ]
        );
    }
}
