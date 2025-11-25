<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run()
    {
        Department::create([
            'hospital_id' => 1,
            'name' => 'قسم الطوارئ',
            'status' => 'active',
        ]);

        Department::create([
            'hospital_id' => 1,
            'name' => 'قسم الباطنة',
            'status' => 'active',
        ]);
    }
}
