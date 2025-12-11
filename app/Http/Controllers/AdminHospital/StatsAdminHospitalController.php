<?php

namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use App\Models\User;
use App\Models\ExternalSupplyRequest;
use App\Models\Complaint;
use Illuminate\Http\Request;

class StatsAdminHospitalController extends BaseApiController
{
    public function index(Request $request)
    {
        $hospitalId = $request->user()->hospital_id;

        // 1) أعداد المستخدمين حسب النوع داخل نفس المستشفى
        $patientsCount   = User::where('type', 'patient')
            ->where('hospital_id', $hospitalId)
            ->count();

        $doctorsCount    = User::where('type', 'doctor')
            ->where('hospital_id', $hospitalId)
            ->count();

        $pharmacistsCount = User::where('type', 'pharmacist')
            ->where('hospital_id', $hospitalId)
            ->count();

        // 2) الحسابات النشطة والخاملة (لكل الأنواع داخل المستشفى)
        $activeAccountsCount = User::where('hospital_id', $hospitalId)
            ->where('status', 'active')
            ->count();

        $inactiveAccountsCount = User::where('hospital_id', $hospitalId)
            ->whereIn('status', ['inactive', 'pending_activation'])
            ->count();

        // 3) عمليات التوريد الخارجية (ExternalSupplyRequest)
        $externalTodayCount = ExternalSupplyRequest::where('hospital_id', $hospitalId)
            ->whereDate('created_at', today())
            ->count();

        $externalWeekCount = ExternalSupplyRequest::where('hospital_id', $hospitalId)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        $externalMonthCount = ExternalSupplyRequest::where('hospital_id', $hospitalId)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        // 4) الشكاوى (Complaint) الخاصة بالمستشفى
        $complaintsCount = Complaint::whereHas('patient', function ($q) use ($hospitalId) {
                $q->where('hospital_id', $hospitalId);
            })
            ->count();

        // 5) طلبات النقل (أفترض جدول transfer_request إن وجد، مؤقتاً 0)
        $transferRequestsCount = 0; // عدّليه لاحقاً مع جدول طلبات النقل

        return response()->json([
            'patientsCount'          => $patientsCount,
            'doctorsCount'           => $doctorsCount,
            'pharmacistsCount'       => $pharmacistsCount,

            'activeAccountsCount'    => $activeAccountsCount,
            'inactiveAccountsCount'  => $inactiveAccountsCount,

            'externalTodayCount'     => $externalTodayCount,
            'externalWeekCount'      => $externalWeekCount,
            'externalMonthCount'     => $externalMonthCount,

            'complaintsCount'        => $complaintsCount,
            'transferRequestsCount'  => $transferRequestsCount,
        ]);
    }
}
