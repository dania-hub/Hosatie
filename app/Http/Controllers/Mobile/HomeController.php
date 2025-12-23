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

        // الحصول على صيدلية المريض
        $patientPharmacyId = $user->pharmacy_id ?? 1;

        // الحصول على جميع الوصفات النشطة للمستخدم
        $activePrescriptions = Prescription::where('patient_id', $user->id)
            ->where('status', 'active')
            ->get();

        $drugStatus = [];
        $now = Carbon::now()->startOfDay(); // نبدأ اليوم من بدايته لتجنب مشاكل الوقت

        Log::info('===== HOME API CALCULATION START =====');
        Log::info('User ID: ' . $user->id);
        Log::info('Current date: ' . $now->format('Y-m-d'));

        foreach ($activePrescriptions as $prescription) {
            Log::info('--- Processing Prescription ID: ' . $prescription->id . ' ---');
            Log::info('Prescription start date: ' . $prescription->start_date);

            $startDate = Carbon::parse($prescription->start_date)->startOfDay();

            // حساب الأشهر المتراكمة باستخدام diffInMonths(now, startDate, false)
            $monthsAccumulated = 0;

            // diffInMonths(now, startDate, false) يعطي الفارق بالأشهر مع إشارة حسب الترتيب.
            $diff = $now->diffInMonths($startDate, false);

            // إذا كان الفارق سالباً (اليوم قبل تاريخ البدء)، فلا يوجد أشهر متراكمة بعد الآن.
            if ($diff < 0) {
                $monthsAccumulated = 0;
                Log::info('Prescription not started yet (future start date)');
            } else {
                // المريض يستحق شهر + عدد الأشهر الكاملة (مثل المنطق القديم).
                $monthsAccumulated = $diff + 1;

                // الحد الأقصى 3 أشهر فقط.
                if ($monthsAccumulated > 3) {
                    $monthsAccumulated = 3;
                }

                Log::info('Months accumulated: ' . $monthsAccumulated);
            }

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
                Log::info('Processing drug: ' . $drug->name . ' (ID: ' . $drug->id . ')');

                $monthlyDosage = (int) ($drug->monthly_quantity ?? 0);
                $dailyDosage = (int) ($drug->daily_quantity ?? 0);

                Log::info('Monthly quantity: ' . $monthlyDosage);
                Log::info('Daily quantity: ' . $dailyDosage);

                if ($monthlyDosage <= 0) {
                    Log::info('Skipping - invalid monthly dosage');
                    continue;
                }

                // حساب الكمية المصروفة لهذا الدواء
                $totalDispensed = (int) Dispensing::where('patient_id', $user->id)
                    ->where('prescription_id', $prescription->id)
                    ->where('drug_id', $drug->id)
                    ->sum('quantity_dispensed');

                Log::info('Total dispensed: ' . $totalDispensed . ' pills');

                // تحويل المصروف إلى أشهر
                $monthsDispensed = 0;
                if ($monthlyDosage > 0) {
                    $monthsDispensed = floor($totalDispensed / $monthlyDosage);
                }

                Log::info('Months dispensed: ' . $monthsDispensed);

                // حساب الرصيد المتبقي (الأشهر المتراكمة - الأشهر المصروفة)
                $remainingMonths = max(0, $monthsAccumulated - $monthsDispensed);

                Log::info('Remaining months: ' . $remainingMonths);

                // الكمية بالحبات
                $currentBalance = $remainingMonths * $monthlyDosage;

                Log::info('Current balance: ' . $currentBalance . ' pills');

                // التحقق من الصرف في آخر 4 أشهر
                $hasRecentDispensing = false;

                // إذا كان هناك رصيد متبقي وكانت الأشهر المتراكمة 3 أو أكثر،
                // نتحقق من وجود صرف خلال آخر 4 أشهر.
                if ($monthsAccumulated >= 3 && $remainingMonths > 0) {
                    $fourMonthsAgo = $now->copy()->subMonths(4);

                    $recentDispensing = Dispensing::where('patient_id', $user->id)
                        ->where('prescription_id', $prescription->id)
                        ->where('drug_id', $drug->id)
                        ->where('dispensing_date', '>=', $fourMonthsAgo)
                        ->exists();

                    if (!$recentDispensing) {
                        // إذا مرت 4 أشهر بدون صرف → الرصيد يصفر
                        $remainingMonths = 0;
                        $currentBalance = 0;
                        Log::info('No dispensing in last 4 months - resetting balance to 0');
                    }
                }

                // التحقق من التوفر في المخزون
                $isAvailable = false;
                $statusText = 'غير متوفر';
                $statusColor = '#fee2e2'; // أحمر
                $textColor = '#991b1b';

                $inventoryRecord = Inventory::where('drug_id', $drug->id)
                    ->where('pharmacy_id', $patientPharmacyId)
                    ->first();

                if ($inventoryRecord && $inventoryRecord->current_quantity > 0) {
                    $isAvailable = true;
                    $statusText = 'متوفر';
                    $statusColor = '#dcfce7'; // أخضر
                    $textColor = '#166534';
                }

                // تحديد الحالة النهائية
                if ($currentBalance == 0 && $totalDispensed > 0) {
                    $statusText = 'تم الصرف';
                    $statusColor = '#e0f2fe'; // أزرق
                    $textColor = '#075985';
                } elseif ($currentBalance == 0 && $totalDispensed == 0) {
                    $statusText = 'لا يوجد رصيد';
                    $statusColor = '#f3f4f6'; // رمادي
                    $textColor = '#374151';
                }

                // إعداد البيانات للعرض
                $drugStatus[] = [
                    'id'           => $drug->id,
                    'drug_name'    => $drug->name,
                    'duration'     => $remainingMonths . ' شهر',
                    'dosage'       => $currentBalance . ' حبة',
                    'status'       => $statusText,
                    'status_color' => $statusColor,
                    'text_color'   => $textColor,
                ];

                Log::info('Drug status added: ' . $drug->name . ' - ' . $remainingMonths . ' months - ' . $currentBalance . ' pills');
            }
        }

        // إذا لم توجد أدوية، أضف رسالة فارغة
        if (empty($drugStatus)) {
            Log::info('No drugs found for user');
        }

        Log::info('===== HOME API CALCULATION END =====');
        Log::info('Total drugs in response: ' . count($drugStatus));

        return response()->json([
            'success' => true,
            'data' => [
                'health_file' => $healthFile,
                'drug_status' => $drugStatus,
            ]
        ]);
    }
}
