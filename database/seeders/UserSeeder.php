<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Super Admin
        User::create([
            'full_name' => 'المدير العام',
            'email' => 'admin@hosatie.ly',
            'phone' => '0910000001',
            'password' => Hash::make('password'),
            'type' => 'super_admin',
            'status' => 'active',
        ]);

        // Hospital Admin
        User::create([
            'full_name' => 'مدير مستشفى طرابلس',
            'email' => 'hosp_admin@hosatie.ly',
            'phone' => '0910000002',
            'password' => Hash::make('password'),
            'type' => 'hospital_admin',
            'hospital_id' => 1,
            'status' => 'active',
        ]);

        // Pharmacist
        User::create([
            'full_name' => 'الصيدلي محمد',
            'email' => 'pharma@hosatie.ly',
            'phone' => '0910000003',
            'password' => Hash::make('password'),
            'type' => 'pharmacist',
            'hospital_id' => 1,
            'pharmacy_id' => 1,
            'status' => 'active',
        ]);
        
         // Doctor
        User::create([
            'full_name' => 'الدكتور علي',
            'email' => 'doctor@hosatie.ly',
            'phone' => '0910000004',
            'password' => Hash::make('password'),
            'type' => 'doctor',
            'hospital_id' => 1,
            'department_id' => 1,
            'status' => 'active',
        ]);

         // Patient
        User::create([
            'full_name' => 'المريض سالم',
            'email' => 'patient@hosatie.ly',
            'phone' => '0910000005',
            'national_id' => '119990001234',
            'password' => Hash::make('password'),
            'type' => 'patient',
            'status' => 'active',
        ]);
    }
}
