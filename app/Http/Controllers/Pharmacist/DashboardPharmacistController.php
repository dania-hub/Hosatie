<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Dispensing;
use App\Models\Inventory; // Using Inventory model
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardPharmacistController extends BaseApiController
{
    /**
     * GET /api/pharmacist/operations
     * Log of pharmacist actions.
     */
    public function operations(Request $request)
    {
        $operations = Dispensing::with('patient')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($dispense) {
                return [
                    'fileNumber' => $dispense->patient_id,
                    'name' => $dispense->patient ? $dispense->patient->name : 'مريض غير معروف',
                    'operationType' => 'صرف وصفة طبية',
                    'operationDate' => Carbon::parse($dispense->created_at)->format('Y/m/d'),
                ];
            });

        return $this->sendSuccess($operations, 'تم تحميل سجل العمليات بنجاح.');
    }

    /**
     * GET /api/pharmacist/dashboard/stats
     * Statistics for dashboard cards.
     */
    public function stats(Request $request)
    {
        // 1. Dispensing operations today
        $dispensingToday = Dispensing::whereDate('created_at', Carbon::today())->count();

        // 2. Critical Stock (Using Inventory table)
        // Count items where current quantity is less than the minimum level
        $criticalStock = Inventory::whereColumn('current_quantity', '<', 'minimum_level')->count();

        // 3. Patients served this week
        $patientsWeek = Dispensing::whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->distinct('patient_id')
            ->count('patient_id');

        $data = [
            'totalRegistered' => $dispensingToday,
            'todayRegistered' => $criticalStock,
            'weekRegistered'  => $patientsWeek,
        ];

        return $this->sendSuccess($data, 'تم تحميل إحصائيات الصيدلي بنجاح.');
    }
}
