<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Prescription;
use App\Models\Dispensing;
use App\Models\Inventory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HomeController extends BaseApiController
{
    public function mobileIndex(Request $request)
    {
        $user = $request->user();
        $user->load('hospital');
        
        $hospitalName = $user->hospital ? $user->hospital->name : 'غير محدد';

        $healthFile = [
            'file_number' => $user->id,
            'hospital'    => $hospitalName, 
        ];

        $patientPharmacyId = $user->pharmacy_id ?? 1;

        $activePrescriptions = Prescription::where('patient_id', $user->id)
            ->where('status', 'active')
            ->get();

        $drugStatus = [];
        $now = Carbon::now()->startOfDay();
        
        Log::info('======= HOME API - V2 DYNAMIC DURATION CALCULATION =======');
        Log::info('User ID: ' . $user->id);
        Log::info('Current date: ' . $now->format('Y-m-d'));

        foreach ($activePrescriptions as $prescription) {
            Log::info('=== Prescription ID: ' . $prescription->id . ' ===');
            
            $drugs = DB::table('prescription_drugs')
                ->join('drugs', 'prescription_drugs.drug_id', '=', 'drugs.id')
                ->where('prescription_drugs.prescription_id', $prescription->id)
                ->select(
                    'drugs.id',
                    'drugs.name',
                    'prescription_drugs.monthly_quantity',
                    'prescription_drugs.daily_quantity',
                    'prescription_drugs.created_at as drug_created_at'
                )
                ->get();

            foreach ($drugs as $drug) {
                $monthlyQuantityInDB = (int) ($drug->monthly_quantity ?? 0);
                $dailyDosage = (int) ($drug->daily_quantity ?? 1);

                Log::info('--- Drug: ' . $drug->name . ' ---');
                Log::info('Monthly in DB: ' . $monthlyQuantityInDB . ' pills/month');
                Log::info('Daily dosage: ' . $dailyDosage . ' pills/day');

                // =================================================================
                // تعديل جديد: إذا كانت الكمية الشهرية في قاعدة البيانات 0، نعتبره "تم الصرف"
                // =================================================================
                if ($monthlyQuantityInDB <= 0) {
                    Log::info('Monthly quantity is 0. Setting balance to 0 and status to "تم الصرف".');
                    $drugStatus[] = [
                        'id'           => $drug->id,
                        'drug_name'    => $drug->name,
                        'duration'     => '0 يوم',
                        'dosage'       => '0 حبة',
                        'daily_dosage' => $dailyDosage,
                        'status'       => 'تم الصرف',
                        'status_color' => '#e0f2fe', // لون "تم الصرف"
                        'text_color'   => '#075985',
                    ];
                    continue; // ننتقل للدواء التالي
                }
                
                if ($dailyDosage <= 0) {
                    Log::info('Skipping - invalid daily dosage');
                    continue;
                }

                // 1. حساب الكمية الشهرية الصحيحة (30 يومًا)
                $monthlyDosage = $dailyDosage * 30;
                Log::info('Calculated monthly quantity: ' . $monthlyDosage);

                // 2. تحديد تاريخ بدء الحساب
                $startCalculationDate = Carbon::parse($drug->drug_created_at)->startOfDay();
                Log::info('Start calculation date: ' . $startCalculationDate->format('Y-m-d'));

                // 3. حساب إجمالي الكمية المستحقة
                $daysPassed = $startCalculationDate->diffInDays($now);
                $accumulatedMonths = floor($daysPassed / 30) + 1;
                $accumulatedMonths = min($accumulatedMonths, 3); // حد أقصى 3 أشهر
                Log::info('Days passed: ' . $daysPassed . ' -> Accumulated months (max 3): ' . $accumulatedMonths);

                $totalDeservedQuantity = $accumulatedMonths * $monthlyDosage;
                Log::info('Total deserved: ' . $accumulatedMonths . ' months × ' . $monthlyDosage . ' = ' . $totalDeservedQuantity . ' pills');

                // 4. حساب إجمالي الكمية المصروفة
                $totalDispensed = (int) Dispensing::where('patient_id', $user->id)
                    ->where('prescription_id', $prescription->id)
                    ->where('drug_id', $drug->id)
                    ->sum('quantity_dispensed');
                Log::info('Total dispensed: ' . $totalDispensed . ' pills');

                // 5. حساب الرصيد الفعلي المتبقي
                $currentBalance = max(0, $totalDeservedQuantity - $totalDispensed);
                Log::info('Current balance: ' . $totalDeservedQuantity . ' - ' . $totalDispensed . ' = ' . $currentBalance . ' pills');

                // 6. التحقق من قاعدة الـ 4 أشهر بدون صرف
                $lastDispensing = Dispensing::where('patient_id', $user->id)
                    ->where('prescription_id', $prescription->id)
                    ->where('drug_id', $drug->id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $monthsSinceStart = $startCalculationDate->diffInMonths($now);

                if ($lastDispensing) {
                    $monthsSinceLastDispense = Carbon::parse($lastDispensing->created_at)->diffInMonths($now);
                    if ($monthsSinceLastDispense >= 4) {
                        $currentBalance = 0;
                        Log::info('Reset to 0 - no dispensing in 4+ months.');
                    }
                } elseif ($totalDispensed == 0 && $monthsSinceStart >= 4) {
                    $currentBalance = 0;
                    Log::info('Reset to 0 - never dispensed in 4+ months.');
                }

                // 7. حساب المدة المتبقية بالايام
                $remainingDays = ($dailyDosage > 0) ? floor($currentBalance / $dailyDosage) : 0;
                Log::info('Remaining days calculated: ' . $remainingDays);

                // 8. تنسيق المدة للعرض
                $durationLabel = $this->formatDuration($remainingDays);
                Log::info('Duration label: ' . $durationLabel);

                // 9. التحقق من توفر الدواء في المخزون
                $statusText = 'غير متوفر';
                $statusColor = '#fee2e2';
                $textColor = '#991b1b';

                $inventoryRecord = Inventory::where('drug_id', $drug->id)
                    ->where('pharmacy_id', $patientPharmacyId)
                    ->first();

                if ($inventoryRecord && $inventoryRecord->current_quantity > 0) {
                    $statusText = 'متوفر';
                    $statusColor = '#dcfce7';
                    $textColor = '#166534';
                }

                // 10. تحديد الحالة النهائية للعرض
                if ($currentBalance == 0) {
                    if ($totalDispensed > 0) {
                        $statusText = 'تم الصرف';
                        $statusColor = '#e0f2fe';
                        $textColor = '#075985';
                    } else {
                        $statusText = 'لا يوجد رصيد';
                        $statusColor = '#f3f4f6';
                        $textColor = '#374151';
                    }
                }

                // 11. إضافة النتيجة النهائية
                $drugStatus[] = [
                    'id'           => $drug->id,
                    'drug_name'    => $drug->name,
                    'duration'     => $durationLabel,
                    'dosage'       => (int) $currentBalance . ' حبة',
                    'daily_dosage' => $dailyDosage,
                    'status'       => $statusText,
                    'status_color' => $statusColor,
                    'text_color'   => $textColor,
                ];
                
                Log::info('--- FINAL RESULT ---');
                Log::info('Drug: ' . $drug->name . ', Balance: ' . $currentBalance . ' pills, Duration: ' . $durationLabel . ', Status: ' . $statusText);
                Log::info('--------------------');
            }
        }

        Log::info('======= END =======');

        return response()->json([
            'success' => true,
            'data' => [
                'health_file' => $healthFile,
                'drug_status' => $drugStatus,
            ]
        ]);
    }

    /**
     * تنسيق المدة بالشهور والأيام
     */
    private function formatDuration(int $days): string
    {
        if ($days <= 0) {
            return '0 يوم';
        }

        $months = floor($days / 30);
        $remainingDays = $days % 30;

        $parts = [];

        if ($months > 0) {
            if ($months == 1) {
                $parts[] = 'شهر واحد';
            } elseif ($months == 2) {
                $parts[] = 'شهران';
            } elseif ($months <= 10) {
                $parts[] = $months . ' أشهر';
            } else {
                $parts[] = $months . ' شهرًا';
            }
        }

        if ($remainingDays > 0) {
            if ($remainingDays == 1) {
                $parts[] = 'يوم واحد';
            } elseif ($remainingDays == 2) {
                $parts[] = 'يومان';
            } else {
                $parts[] = $remainingDays . ' يومًا';
            }
        }
        
        if (empty($parts)) {
            return '0 يوم';
        }

        return implode(' و ', $parts);
    }
}
