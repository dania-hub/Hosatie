<?php

namespace App\Http\Controllers\DepartmentAdmin;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Drug;
// use App\Models\SupplyRequest; // Enable when model exists

class DashboardDepartmentAdminController extends BaseApiController
{
    /**
     * GET /api/department-admin/dashboard/stats
     * Returns stats matching the Frontend keys: totalRegistered, todayRegistered, weekRegistered
     */
    public function stats(Request $request)
    {
        // 1. totalRegistered -> Total Patients under this department's scope
        $totalPatients = User::where('type', 'patient')->count();

        // 2. todayRegistered -> Today's Activities (e.g., Requests made today)
        // $todayRequests = SupplyRequest::whereDate('created_at', now())->count();
        $todayRequests = 5; // Mock data

        // 3. weekRegistered -> Active Items (e.g., Pending Requests or Low Stock Drugs)
        // $activeRequests = SupplyRequest::where('status', 'pending')->count();
        $activeRequests = 12; // Mock data

        // Match the keys expected by the Frontend View 3
        $data = [
            'totalRegistered' => $totalPatients,
            'todayRegistered' => $todayRequests,
            'weekRegistered'  => $activeRequests,
        ];

        return $this->sendSuccess($data, 'تم جلب إحصائيات لوحة التحكم.');
    }

    /**
     * GET /api/department-admin/operations
     * (Matches View 1 Activity Log)
     */
    public function operations(Request $request)
    {
        // Same as before
        $logs = \App\Models\AuditLog::latest()
            ->take(20)
            ->get()
            ->map(function ($log) {
                return [
                    'fileNumber'    => $log->record_id ?? 'N/A',
                    'name'          => $log->user ? $log->user->full_name : 'Unknown',
                    'operationType' => $log->action,
                    'operationDate' => $log->created_at->format('Y/m/d'),
                ];
            });

        return response()->json($logs);
    }
}
