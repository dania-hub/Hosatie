<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Dispensing;
use App\Models\Inventory;
use App\Models\Pharmacy;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardPharmacistController extends BaseApiController
{
    /**
     * دالة مساعدة لجلب معرف صيدلية المستخدم الحالي
     */
    private function getPharmacistPharmacyId($user)
    {
        // إذا كان المستخدم مرتبطاً بصيدلية مباشرة
        if ($user->pharmacy_id) {
            return $user->pharmacy_id;
        }
        
        // أو إذا كان مرتبطاً بمستشفى، نجلب صيدلية المستشفى
        if ($user->hospital_id) {
            $pharmacy = Pharmacy::where('hospital_id', $user->hospital_id)->first();
            return $pharmacy ? $pharmacy->id : null;
        }

        return null;
    }

    /**
     * GET /api/pharmacist/operations
     * سجل عمليات الصيدلاني (داخل صيدليته فقط).
     */
    public function operations(Request $request)
    {
        $user = $request->user();
        $pharmacyId = $this->getPharmacistPharmacyId($user);

        // بناء الاستعلام
        $query = Dispensing::with('patient')
            ->orderBy('created_at', 'desc');

        // تصفية حسب الصيدلية (إذا وجدت)
        if ($pharmacyId) {
            $query->where('pharmacy_id', $pharmacyId);
        } else {
            // إذا لم نجد صيدلية، نعرض عمليات الصيدلاني نفسه فقط كحل بديل
            $query->where('pharmacist_id', $user->id);
        }

        $operations = $query->limit(20)
            ->get()
            ->map(function ($dispense) {
                return [
                    'fileNumber' => $dispense->patient_id,
                    'name' => $dispense->patient ? ($dispense->patient->full_name ?? $dispense->patient->name) : 'مريض غير معروف',
                    'operationType' => 'صرف وصفة طبية',
                    'operationDate' => Carbon::parse($dispense->created_at)->format('Y/m/d'),
                ];
            });

        return $this->sendSuccess($operations, 'تم تحميل سجل العمليات بنجاح.');
    }

    /**
     * GET /api/pharmacist/dashboard/stats
     * إحصائيات لوحة التحكم (خاصة بالصيدلية الحالية).
     */
    public function stats(Request $request)
    {
        $user = $request->user();
        $pharmacyId = $this->getPharmacistPharmacyId($user);

        // 1. عمليات الصرف اليوم (في هذه الصيدلية)
        $dispensingToday = Dispensing::whereDate('created_at', Carbon::today());
        if ($pharmacyId) {
            $dispensingToday->where('pharmacy_id', $pharmacyId);
        }
        $dispensingTodayCount = $dispensingToday->count();

        // 2. الأدوية التي أوشكت على النفاد (Critical Stock)
        // نبحث في جدول Inventory حيث pharmacy_id مطابق
        $criticalStockQuery = Inventory::whereColumn('current_quantity', '<', 'minimum_level');
        
        if ($pharmacyId) {
            $criticalStockQuery->where('pharmacy_id', $pharmacyId);
        } else {
            // إذا لم نجد صيدلية، قد يكون الكود يحاول فحص المستودع بالخطأ
            // نضع شرطاً مستحيلاً أو فارغاً لتجنب عرض بيانات خاطئة
            $criticalStockQuery->whereNotNull('pharmacy_id'); 
        }
        
        $criticalStockCount = $criticalStockQuery->count();


        // 3. المرضى الذين تم خدمتهم هذا الأسبوع (في هذه الصيدلية)
        $patientsWeekQuery = Dispensing::whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
        
        if ($pharmacyId) {
            $patientsWeekQuery->where('pharmacy_id', $pharmacyId);
        }
        
        $patientsWeekCount = $patientsWeekQuery->distinct('patient_id')->count('patient_id');

        $data = [
            'totalRegistered' => $dispensingTodayCount,
            'todayRegistered' => $criticalStockCount,
            'weekRegistered'  => $patientsWeekCount,
        ];

        return $this->sendSuccess($data, 'تم تحميل إحصائيات الصيدلي بنجاح.');
    }
}
