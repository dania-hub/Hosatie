<?php

namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use App\Models\User;
use App\Models\ExternalSupplyRequest;
use App\Models\Complaint;
use App\Models\PatientTransferRequest;
use App\Models\Department;
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

        $dataEntryCount = User::where('type', 'data_entry')
            ->where('hospital_id', $hospitalId)
            ->count();

        // 2) عدد الأقسام
        $departmentsCount = Department::where('hospital_id', $hospitalId)
            ->count();

        // 3) الحسابات النشطة والخاملة (لكل الأنواع داخل المستشفى)
        $activeAccountsCount = User::where('hospital_id', $hospitalId)
            ->where('status', 'active')
            ->count();

        $inactiveAccountsCount = User::where('hospital_id', $hospitalId)
            ->whereIn('status', ['inactive', 'pending_activation'])
            ->count();

        // 4) عمليات التوريد الخارجية (ExternalSupplyRequest)
        $externalTodayCount = ExternalSupplyRequest::where('hospital_id', $hospitalId)
            ->whereDate('created_at', today())
            ->count();

        $externalWeekCount = ExternalSupplyRequest::where('hospital_id', $hospitalId)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        $externalMonthCount = ExternalSupplyRequest::where('hospital_id', $hospitalId)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        // 5) الشكاوى (Complaint) الخاصة بالمستشفى
        $complaintsCount = Complaint::whereHas('patient', function ($q) use ($hospitalId) {
                $q->where('hospital_id', $hospitalId);
            })
            ->count();

        // 6) طلبات النقل (الطلبات الموجهة لهذا المستشفى)
        $transferRequestsCount = PatientTransferRequest::where('to_hospital_id', $hospitalId)
            ->where('status', 'pending')
            ->count();

        return response()->json([
            'patientsCount'          => $patientsCount,
            'doctorsCount'           => $doctorsCount,
            'pharmacistsCount'       => $pharmacistsCount,
            'dataEntryCount'        => $dataEntryCount,
            'departmentsCount'      => $departmentsCount,

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
