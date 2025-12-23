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
        
        Log::info('======= HOME API - MONTH CALCULATION =======');

        foreach ($activePrescriptions as $prescription) {
            Log::info('Prescription: ' . $prescription->id . ', Start: ' . $prescription->start_date);
            
            $startDate = Carbon::parse($prescription->start_date)->startOfDay();
            
            // الحصول على أدوية الوصفة
            $drugs = DB::table('prescription_drug')
                ->join('drug', 'prescription_drug.drug_id', '=', 'drug.id')
                ->where('prescription_drug.prescription_id', $prescription->id)
                ->select(
                    'drug.id',
                    'drug.name',
                    'prescription_drug.monthly_quantity',
                    'prescription_drug.daily_quantity'
                )
                ->get();

            foreach ($drugs as $drug) {
                $monthlyDosage = (int) ($drug->monthly_quantity ?? 0);
                $dailyDosage = (int) ($drug->daily_quantity ?? 1);
                
                Log::info('Drug: ' . $drug->name . ', Monthly: ' . $monthlyDosage . ', Daily: ' . $dailyDosage);
                
                if ($monthlyDosage <= 0 || $dailyDosage <= 0) {
                    Log::info('Skipping - invalid dosage');
                    continue;
                }

                // ==============================================
                // 1. حساب عدد الأشهر المتراكمة (ليس الأيام!)
                // ==============================================
                $accumulatedMonths = 0;
                
                if ($now->greaterThanOrEqualTo($startDate)) {
                    // حساب الأشهر منذ بداية الوصفة
                    $monthsSinceStart = $startDate->diffInMonths($now);
                    
                    // المريض يستحق الشهر الحالي أيضاً
                    $accumulatedMonths = $monthsSinceStart + 1;
                    
                    Log::info('Months since start: ' . $monthsSinceStart);
                    Log::info('Accumulated months (+1): ' . $accumulatedMonths);
                    
                    // الحد الأقصى: 3 أشهر فقط
                    $maxMonths = 3;
                    if ($accumulatedMonths > $maxMonths) {
                        $accumulatedMonths = $maxMonths;
                        Log::info('Capped at max ' . $maxMonths . ' months');
                    }
                } else {
                    Log::info('Prescription not started yet');
                    $accumulatedMonths = 0;
                }

                // ==============================================
                // 2. حساب الصرفيات بالأشهر
                // ==============================================
                $totalDispensed = (int) Dispensing::where('patient_id', $user->id)
                    ->where('prescription_id', $prescription->id)
                    ->where('drug_id', $drug->id)
                    ->sum('quantity_dispensed');
                    
                Log::info('Total dispensed: ' . $totalDispensed . ' pills');
                
                // تحويل المصروف إلى أشهر (كمية شهرية ÷ المصروف)
                $monthsDispensed = 0;
                if ($monthlyDosage > 0) {
                    $monthsDispensed = floor($totalDispensed / $monthlyDosage);
                }
                
                Log::info('Months dispensed: ' . $monthsDispensed);

                // ==============================================
                // 3. حساب الأشهر المتبقية
                // ==============================================
                $remainingMonths = max(0, $accumulatedMonths - $monthsDispensed);
                
                Log::info('Remaining months: ' . $remainingMonths);

                // ==============================================
                // 4. قاعدة: 4 أشهر بدون صرف → يصفر
                // ==============================================
                if ($accumulatedMonths >= 4 && $monthsDispensed == 0) {
                    $remainingMonths = 0;
                    Log::info('Reset to 0 - no dispensing in 4 months');
                }

                // ==============================================
                // 5. حساب الرصيد بالحبات
                // ==============================================
                // طريقة 1: من الأشهر المتبقية × الكمية الشهرية
                $currentBalance = $remainingMonths * $monthlyDosage;
                
                // طريقة 2 (بديلة): من الأيام (لتجنب التقريب)
                // $remainingDays = $remainingMonths * 30;
                // $currentBalance = $remainingDays * $dailyDosage;
                
                Log::info('Current balance in pills: ' . $currentBalance);

                // ==============================================
                // 6. تنسيق المدة للعرض (بالأشهر فقط!)
                // ==============================================
                $durationLabel = $this->formatMonthsOnly($remainingMonths);
                Log::info('Duration label: ' . $durationLabel);

                // ==============================================
                // 7. التحقق من التوفر
                // ==============================================
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

                // ==============================================
                // 8. تحديد الحالة النهائية
                // ==============================================
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

                // ==============================================
                // 9. إضافة النتيجة
                // ==============================================
                $drugStatus[] = [
                    'id'           => $drug->id,
                    'drug_name'    => $drug->name,
                    'duration'     => $durationLabel, // مثال: "شهران"
                    'dosage'       => $currentBalance . ' حبة',
                    'daily_dosage' => $dailyDosage,
                    'status'       => $statusText,
                    'status_color' => $statusColor,
                    'text_color'   => $textColor,
                ];
                
                Log::info('Final: ' . $durationLabel . ', ' . $currentBalance . ' pills');
            }
        }

        Log::info('======= END =======');
        Log::info('Total drugs: ' . count($drugStatus));

        return response()->json([
            'success' => true,
            'data' => [
                'health_file' => $healthFile,
                'drug_status' => $drugStatus,
            ]
        ]);
    }

    /**
     * تنسيق الأشهر فقط (بدون أيام)
     */
    private function formatMonthsOnly(int $months): string
    {
        if ($months == 0) {
            return '0 شهر';
        } elseif ($months == 1) {
            return 'شهر واحد';
        } elseif ($months == 2) {
            return 'شهران';
        } elseif ($months <= 10) {
            return $months . ' أشهر';
        } else {
            return $months . ' شهرًا';
        }
    }
}