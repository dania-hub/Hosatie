<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Prescription;

class HomeController extends BaseApiController
{
    public function mobileIndex(Request $request)
    {
        $user = $request->user();

        $healthFile = [
            'full_name'   => $user->full_name,
            'file_number' => $user->id, // أو عمود آخر لو أضفته لاحقاً
            'national_id' => $user->national_id,
        ];

        // جلب الوصفات النشطة مع الأدوية المرتبطة بها
        // العلاقة: Prescription -> belongsToMany Drug (via prescription_drug)
        $activePrescriptions = Prescription::with(['drugs'])
            ->where('patient_id', $user->id)
            ->where('status', 'active')
            ->get();

        $drugStatus = [];

        foreach ($activePrescriptions as $prescription) {
            foreach ($prescription->drugs as $drug) {
                // الوصول للكمية الشهرية من الجدول الوسيط (Pivot)
                $monthlyQty = $drug->pivot->monthly_quantity ?? 0;
                
                $drugStatus[] = [
                    'drug_name' => $drug->name,
                    'strength'  => $drug->strength,
                    'status'    => 'متاح للصرف', // منطق بسيط حالياً
                    'quantity'  => $monthlyQty,
                    'next_refill' => date('Y-m-d', strtotime('+1 month')), // مثال
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
