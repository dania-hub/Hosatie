<?php

namespace Database\Seeders;

use App\Models\Complaint;
use Illuminate\Database\Seeder;

class ComplaintSeeder extends Seeder
{
    public function run()
    {
        Complaint::create([
            'patient_id'   => 5,
            'message'      => 'هناك تأخير في صرف الدواء.',
            'status'       => 'قيد المراجعة',
            'created_at'   => now(),
        ]);
    }
}
