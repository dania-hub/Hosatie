<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\baseApiController;
use Illuminate\Http\Request;
use App\Models\Prescription;
use App\Models\Dispensing;
use Carbon\Carbon;

class HomeController extends baseApiController
{
    public function mobileIndex(Request $request)
    {
        $user = $request->user();

        $user->load('hospital');
        
        $hospitalName = 'غير محدد';
        if ($user->hospital) {
            $hospitalName = $user->hospital->name;
        }
        // ==================================================

        $healthFile = [
            'full_name'   => $user->full_name,
            'file_number' => $user->id,
            'national_id' => $user->national_id,
            'hospital'    => $hospitalName, 
        ];

        // 2. Active Prescriptions
        $activePrescriptions = Prescription::with(['drugs'])
            ->where('patient_id', $user->id)
            ->where('status', 'active')
            ->get();

        $drugStatus = [];

        foreach ($activePrescriptions as $prescription) {
            foreach ($prescription->drugs as $drug) {
                
                // A. Calculate Duration (Months not taken)
                $lastDispensation = Dispensing::where('patient_id', $user->id)
                    ->where('drug_id', $drug->id)
                    ->latest('created_at')
                    ->first();

                $durationLabel = 'جديد';
                
                if ($lastDispensation) {
                    $lastDate = Carbon::parse($lastDispensation->created_at);
                    $diffInMonths = (int) $lastDate->diffInMonths(Carbon::now());

                    if ($diffInMonths >= 1 && $diffInMonths < 4) {
                        $durationLabel = $diffInMonths . ' شهر';
                    } elseif ($diffInMonths >= 4) {
                        $durationLabel = '+3 أشهر'; 
                    } else {
                        $durationLabel = 'تم الصرف مؤخراً';
                    }
                } else {
                    $startDate = Carbon::parse($prescription->start_date);
                    $diffInMonths = (int) $startDate->diffInMonths(Carbon::now());
                     if ($diffInMonths >= 1) {
                        $durationLabel = $diffInMonths . ' شهر';
                     }
                }

                $monthlyQty = $drug->pivot->monthly_quantity ?? 0;

                $drugStatus[] = [
                    'id'        => $drug->id,
                    'drug_name' => $drug->name,
                    'duration'  => $durationLabel,
                    'dosage'    => $monthlyQty . ' حبة',
                    'status'    => ($drug->status === 'متوفر') ? 'متوفر' : 'غير متوفر',
                    'status_color' => ($drug->status === 'متوفر') ? '#dcfce7' : '#fee2e2',
                    'text_color' => ($drug->status === 'متوفر') ? '#166534' : '#991b1b',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'health_file' => $healthFile,
                'drug_status' => $drugStatus,
            ]
        ]);
    }
}