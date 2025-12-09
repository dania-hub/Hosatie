<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Prescription;
use App\Models\Dispensing;
use App\Models\Inventory;
use Carbon\Carbon;

class HomeController extends BaseApiController
{
    public function mobileIndex(Request $request)
    {
        $user = $request->user();
        $user->load('hospital');
        
        $hospitalName = $user->hospital ? $user->hospital->name : 'غير محدد';

        $healthFile = [
            'full_name'   => $user->full_name,
            'file_number' => $user->id,
            'national_id' => $user->national_id,
            'hospital'    => $hospitalName, 
        ];

        $targetPharmacyId = 1; 

        $activePrescriptions = Prescription::with(['drugs'])
            ->where('patient_id', $user->id)
            ->where('status', 'active')
            ->get();

        $drugStatus = [];
        $now = Carbon::now()->startOfDay();

        foreach ($activePrescriptions as $prescription) {
            $startDate = Carbon::parse($prescription->start_date)->startOfDay();
            
            // 1. الأشهر المستحقة
            $monthsPassed = (int) $startDate->diffInMonths($now); 
            $totalMonthsEntitled = $monthsPassed + 1; 

            foreach ($prescription->drugs as $drug) {
                $monthlyDosage = (int) ($drug->pivot->monthly_quantity ?? 0);

                if ($monthlyDosage <= 0) continue; 

                // 2. إجمالي الكمية المستحقة
                $totalQuantityEntitled = $totalMonthsEntitled * $monthlyDosage;

                // 3. إجمالي الكمية المصروفة
                $totalDispensed = (int) Dispensing::where('patient_id', $user->id)
                    ->where('prescription_id', $prescription->id)
                    ->where('drug_id', $drug->id)
                    ->sum('quantity_dispensed');

                // 4. الرصيد الحالي
                $currentBalance = max(0, $totalQuantityEntitled - $totalDispensed);

                // ============================================================
                // حساب المدة (الرصيد بالأشهر)
                // ============================================================
                if ($currentBalance == 0) {
                    $durationInMonths = 0;
                } else {
                    $durationInMonths = (int) ceil($currentBalance / $monthlyDosage);
                }
                
                $monthsPendingExact = floor($currentBalance / $monthlyDosage);

                // قاعدة: إيقاف الصرف إذا تجاوز التراكم 3 أشهر
                if ($monthsPendingExact > 3) { 
                    $drugStatus[] = [
                        'id'           => $drug->id,
                        'drug_name'    => $drug->name,
                        'duration'     => 'منتهية الصلاحية',
                        'dosage'       => 'تم الإيقاف',
                        'status'       => 'تجاوزت 3 أشهر',
                        'status_color' => '#f3f4f6', // رمادي
                        'text_color'   => '#374151',
                    ];
                    continue; 
                }

                // توليد نص المدة
                if ($durationInMonths == 0) {
                    $durationLabel = '0 شهر';
                } else {
                    $durationLabel = $durationInMonths . ' شهر';
                }

                // التحقق من التوفر في الصيدلية
                $isAvailable = false;
                $statusText = 'غير متوفر';
                $statusColor = '#fee2e2'; // أحمر
                $textColor = '#991b1b';

                $inventoryRecord = Inventory::where('drug_id', $drug->id)
                    ->where('pharmacy_id', $targetPharmacyId)
                    ->first();

                if ($inventoryRecord && $inventoryRecord->current_quantity > 0) {
                    $isAvailable = true;
                    $statusText = 'متوفر';
                    $statusColor = '#dcfce7'; // أخضر
                    $textColor = '#166534';
                }

                if ($currentBalance == 0) {
                    $statusText = 'تم الصرف'; 
                    $statusColor = '#e0f2fe'; // أزرق
                    $textColor = '#075985';
                    $dosageLabel = '0 حبة'; 
                } else {
                    $dosageLabel = $currentBalance . ' حبة'; 
                }

                $drugStatus[] = [
                    'id'           => $drug->id,
                    'drug_name'    => $drug->name,
                    'duration'     => $durationLabel, 
                    'dosage'       => $dosageLabel,   
                    'status'       => $statusText,    
                    'status_color' => $statusColor,
                    'text_color'   => $textColor,
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
