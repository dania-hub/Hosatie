<?php

namespace Database\Seeders;

use App\Models\PatientTransferRequest;
use Illuminate\Database\Seeder;

class PatientTransferRequestSeeder extends Seeder
{
    public function run()
    {
        PatientTransferRequest::create([
            'patient_id'        => 5,
            'from_hospital_id'  => 1,
            'to_hospital_id'    => 2,
            'requested_by'      => 2,
            'status'            => 'approved',
            'reason'            => 'تحويل لمزيد من الرعاية الطبية',
            'handeled_by'       => 1,
            'handeled_at'       => now(),
        ]);
    }
}
