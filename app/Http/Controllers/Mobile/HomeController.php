<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\baseApiController;
use Illuminate\Http\Request;
use App\Models\Prescription;
use App\Models\Dispensing; // We need this to check history
use Carbon\Carbon;

class HomeController extends baseApiController
{
    public function mobileIndex(Request $request)
    {
        $user = $request->user();

        // 1. Health File Data
        $healthFile = [
            'full_name'   => $user->full_name,
            'file_number' => $user->id,
            'national_id' => $user->national_id,
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
                // 1. Find the LAST time this drug was dispensed to this patient
                $lastDispensation = Dispensing::where('patient_id', $user->id)
                    ->where('drug_id', $drug->id)
                    ->latest('created_at')
                    ->first();

                $durationLabel = 'جديد'; // Default if never dispensed
                
                if ($lastDispensation) {
                    // Calculate difference in months from last dispensation until now
                    $lastDate = Carbon::parse($lastDispensation->created_at);
                    $diffInMonths = (int) $lastDate->diffInMonths(Carbon::now());

                    if ($diffInMonths >= 1 && $diffInMonths < 4) {
                        $durationLabel = $diffInMonths . ' شهر';
                    } elseif ($diffInMonths >= 4) {
                        // If 4+ months, logically the account might be inactive, 
                        // but for display we show the delay.
                        $durationLabel = '+3 أشهر'; 
                    } else {
                        $durationLabel = 'تم الصرف مؤخراً'; // Less than 1 month
                    }
                } else {
                    // If never dispensed, check when prescription started
                    $startDate = Carbon::parse($prescription->start_date);
                    $diffInMonths = (int) $startDate->diffInMonths(Carbon::now());
                     if ($diffInMonths >= 1) {
                        $durationLabel = $diffInMonths . ' شهر';
                     }
                }

                // B. Monthly Quantity
                $monthlyQty = $drug->pivot->monthly_quantity ?? 0;

                $drugStatus[] = [
                    'id'        => $drug->id,
                    'drug_name' => $drug->name,
                    
                    // The Calculated Duration
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
