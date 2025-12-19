<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use App\Models\Hospital;
use App\Models\Drug;
use App\Models\User;
use App\Models\Supplier;
use App\Models\Inventory;
use App\Models\Prescription;
use App\Models\Dispensing;
use App\Models\InternalSupplyRequest;
use App\Models\ExternalSupplyRequest;
use App\Models\PatientTransferRequest;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardSuperController extends BaseApiController
{
    /**
     * FR-98: لوحة التحكم الرئيسية - الإحصائيات العامة
     * GET /api/super-admin/dashboard/stats
     */
    public function stats(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            // 1. إحصائيات المؤسسات
            $hospitalsStats = [
                'total' => Hospital::count(),
                'active' => Hospital::where('status', 'active')->count(),
                'inactive' => Hospital::where('status', 'inactive')->count(),
                'byType' => [
                    'hospital' => Hospital::where('type', 'hospital')->count(),
                    'healthCenter' => Hospital::where('type', 'health_center')->count(),
                    'clinic' => Hospital::where('type', 'clinic')->count(),
                ],
                'byCity' => Hospital::select('city', DB::raw('count(*) as count'))
                    ->groupBy('city')
                    ->get()
                    ->mapWithKeys(fn($item) => [$item->city => $item->count]),
            ];

            // 2. إحصائيات المستخدمين
            $usersStats = [
                'total' => User::whereNotIn('type', ['patient'])->count(),
                'active' => User::whereNotIn('type', ['patient'])->where('status', 'active')->count(),
                'inactive' => User::whereNotIn('type', ['patient'])->where('status', 'inactive')->count(),
                'pendingActivation' => User::whereNotIn('type', ['patient'])->where('status', 'pending_activation')->count(),
                'byType' => User::whereNotIn('type', ['patient'])
                    ->select('type', DB::raw('count(*) as count'))
                    ->groupBy('type')
                    ->get()
                    ->mapWithKeys(fn($item) => [$item->type => $item->count]),
            ];

            // 3. إحصائيات المرضى
            $patientsStats = [
                'total' => User::where('type', 'patient')->count(),
                'active' => User::where('type', 'patient')->where('status', 'active')->count(),
                'withPrescriptions' => Prescription::where('status', 'active')->distinct('patient_id')->count(),
                'newThisMonth' => User::where('type', 'patient')
                    ->whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year)
                    ->count(),
            ];

            // 4. إحصائيات الأدوية
            $drugsStats = [
                'total' => Drug::count(),
                'available' => Drug::where('status', 'متوفر')->count(),
                'unavailable' => Drug::where('status', 'غير متوفر')->count(),
                'lowStock' => Inventory::whereRaw('current_quantity < minimum_level')->count(),
                'byCategory' => Drug::whereNotNull('category')
                    ->select('category', DB::raw('count(*) as count'))
                    ->groupBy('category')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get()
                    ->map(fn($item) => [
                        'name' => $item->category,
                        'count' => $item->count,
                    ]),
            ];

            // 5. إحصائيات الوصفات
            $prescriptionsStats = [
                'total' => Prescription::count(),
                'active' => Prescription::where('status', 'active')->count(),
                'cancelled' => Prescription::where('status', 'cancelled')->count(),
                'suspended' => Prescription::where('status', 'suspended')->count(),
                'thisMonth' => Prescription::whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year)
                    ->count(),
            ];

            // 6. إحصائيات الصرف
            $dispensingStats = [
                'total' => Dispensing::count(),
                'thisMonth' => Dispensing::whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year)
                    ->count(),
                'reverted' => Dispensing::where('reverted', true)->count(),
            ];

            // 7. إحصائيات الطلبات
            $requestsStats = [
                'internal' => [
                    'total' => InternalSupplyRequest::count(),
                    'pending' => InternalSupplyRequest::where('status', 'pending')->count(),
                    'approved' => InternalSupplyRequest::where('status', 'approved')->count(),
                    'rejected' => InternalSupplyRequest::where('status', 'rejected')->count(),
                    'fulfilled' => InternalSupplyRequest::where('status', 'fulfilled')->count(),
                ],
                'external' => [
                    'total' => ExternalSupplyRequest::count(),
                    'pending' => ExternalSupplyRequest::where('status', 'pending')->count(),
                    'approved' => ExternalSupplyRequest::where('status', 'approved')->count(),
                    'rejected' => ExternalSupplyRequest::where('status', 'rejected')->count(),
                    'fulfilled' => ExternalSupplyRequest::where('status', 'fulfilled')->count(),
                ],
                'transfer' => [
                    'total' => PatientTransferRequest::count(),
                    'pending' => PatientTransferRequest::where('status', 'pending')->count(),
                    'approved' => PatientTransferRequest::where('status', 'approved')->count(),
                    'rejected' => PatientTransferRequest::where('status', 'rejected')->count(),
                ],
            ];

            // 8. إحصائيات الشكاوى
            $complaintsStats = [
                'total' => Complaint::count(),
                'pending' => Complaint::where('status', 'قيد المراجعة')->count(),
                'resolved' => Complaint::where('status', 'تم الرد')->count(),
                'thisMonth' => Complaint::whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year)
                    ->count(),
            ];

            // 9. إحصائيات الموردين
            $suppliersStats = [
                'total' => Supplier::count(),
                'active' => Supplier::where('status', 'active')->count(),
                'inactive' => Supplier::where('status', 'inactive')->count(),
            ];

            $data = [
                'hospitals' => $hospitalsStats,
                'users' => $usersStats,
                'patients' => $patientsStats,
                'drugs' => $drugsStats,
                'prescriptions' => $prescriptionsStats,
                'dispensing' => $dispensingStats,
                'requests' => $requestsStats,
                'complaints' => $complaintsStats,
                'suppliers' => $suppliersStats,
            ];

            return $this->sendSuccess($data, 'تم جلب الإحصائيات بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Dashboard Stats Error');
        }
    }

    /**
     * FR-99: تقرير المؤسسات الصحية
     * GET /api/super-admin/reports/hospitals
     */
    public function hospitalsReport(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $hospitals = Hospital::with(['supplier'])
                ->withCount([
                    'users as staff_count',
                    'warehouses as warehouses_count',
                ])
                ->get()
                ->map(function ($hospital) {
                    // عدد المرضى في المستشفى
                    $patientsCount = Prescription::where('hospital_id', $hospital->id)
                        ->distinct('patient_id')
                        ->count();

                    // عدد الوصفات النشطة
                    $activePrescriptions = Prescription::where('hospital_id', $hospital->id)
                        ->where('status', 'active')
                        ->count();

                    // طلبات التوريد الداخلية
                    $internalRequests = InternalSupplyRequest::whereHas('pharmacy', function($q) use ($hospital) {
                        $q->where('hospital_id', $hospital->id);
                    })->count();

                    return [
                        'id' => $hospital->id,
                        'name' => $hospital->name,
                        'code' => $hospital->code,
                        'type' => $hospital->type,
                        'typeArabic' => $this->translateType($hospital->type),
                        'city' => $hospital->city,
                        'status' => $hospital->status,
                        'statusArabic' => $hospital->status === 'active' ? 'نشط' : 'غير نشط',
                        'supplier' => $hospital->supplier ? [
                            'id' => $hospital->supplier->id,
                            'name' => $hospital->supplier->name,
                        ] : null,
                        'statistics' => [
                            'staffCount' => $hospital->staff_count,
                            'warehousesCount' => $hospital->warehouses_count,
                            'patientsCount' => $patientsCount,
                            'activePrescriptions' => $activePrescriptions,
                            'internalRequests' => $internalRequests,
                        ],
                        'createdAt' => optional($hospital->created_at)->format('Y-m-d'),
                    ];
                });

            return $this->sendSuccess($hospitals, 'تم جلب تقرير المؤسسات بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Hospitals Report Error');
        }
    }

    /**
     * FR-100: تقرير الأدوية
     * GET /api/super-admin/reports/drugs
     */
    public function drugsReport(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $drugs = Drug::withCount([
                    'prescriptions as prescriptions_count',
                ])
                ->get()
                ->map(function ($drug) {
                    // إجمالي الكميات في المخازن
                    $totalStock = Inventory::where('drug_id', $drug->id)
                        ->sum('current_quantity');

                    // عدد المخازن التي تحتوي على الدواء
                    $warehousesCount = Inventory::where('drug_id', $drug->id)
                        ->where('current_quantity', '>', 0)
                        ->count();

                    // عدد مرات الصرف
                    $dispensingCount = Dispensing::whereHas('prescription.drugs', function($q) use ($drug) {
                        $q->where('drug_id', $drug->id);
                    })->count();

                    return [
                        'id' => $drug->id,
                        'name' => $drug->name,
                        'genericName' => $drug->generic_name,
                        'strength' => $drug->strength,
                        'form' => $drug->form,
                        'category' => $drug->category,
                        'unit' => $drug->unit,
                        'status' => $drug->status,
                        'manufacturer' => $drug->manufacturer,
                        'country' => $drug->country,
                        'expiryDate' => $drug->expiry_date,
                        'statistics' => [
                            'totalStock' => $totalStock,
                            'warehousesCount' => $warehousesCount,
                            'prescriptionsCount' => $drug->prescriptions_count ?? 0,
                            'dispensingCount' => $dispensingCount,
                        ],
                        'createdAt' => optional($drug->created_at)->format('Y-m-d'),
                    ];
                });

            return $this->sendSuccess($drugs, 'تم جلب تقرير الأدوية بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Drugs Report Error');
        }
    }

    /**
     * FR-101: تقرير المستخدمين
     * GET /api/super-admin/reports/users
     */
    public function usersReport(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $users = User::whereNotIn('type', ['patient'])
                ->with(['hospital', 'supplier', 'warehouse', 'pharmacy', 'department'])
                ->get()
                ->map(function ($u) {
                    $lastLogin = $u->tokens()->latest('created_at')->first();

                    return [
                        'id' => $u->id,
                        'fullName' => $u->full_name,
                        'email' => $u->email,
                        'phone' => $u->phone,
                        'nationalId' => $u->national_id,
                        'type' => $u->type,
                        'typeArabic' => $this->translateUserType($u->type),
                        'status' => $u->status,
                        'statusArabic' => $this->translateStatus($u->status),
                        'hospital' => $u->hospital ? [
                            'id' => $u->hospital->id,
                            'name' => $u->hospital->name,
                        ] : null,
                        'supplier' => $u->supplier ? [
                            'id' => $u->supplier->id,
                            'name' => $u->supplier->name,
                        ] : null,
                        'warehouse' => $u->warehouse ? [
                            'id' => $u->warehouse->id,
                            'name' => $u->warehouse->name,
                        ] : null,
                        'pharmacy' => $u->pharmacy ? [
                            'id' => $u->pharmacy->id,
                            'name' => $u->pharmacy->name,
                        ] : null,
                        'department' => $u->department ? [
                            'id' => $u->department->id,
                            'name' => $u->department->name,
                        ] : null,
                        'lastLogin' => $lastLogin ? $lastLogin->created_at->format('Y-m-d H:i') : null,
                        'createdAt' => optional($u->created_at)->format('Y-m-d'),
                    ];
                });

            return $this->sendSuccess($users, 'تم جلب تقرير المستخدمين بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Users Report Error');
        }
    }

    /**
     * FR-102: تقرير الطلبات الشهري (آخر 12 شهر)
     * GET /api/super-admin/reports/requests-monthly
     */
    public function requestsMonthlyReport(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $months = [];
            for ($i = 11; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $monthKey = $date->format('Y-m');
                $monthName = $date->locale('ar')->translatedFormat('F Y');

                $internalRequests = InternalSupplyRequest::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count();

                $externalRequests = ExternalSupplyRequest::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count();

                $transferRequests = PatientTransferRequest::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count();

                $months[] = [
                    'month' => $monthKey,
                    'monthName' => $monthName,
                    'internalRequests' => $internalRequests,
                    'externalRequests' => $externalRequests,
                    'transferRequests' => $transferRequests,
                    'total' => $internalRequests + $externalRequests + $transferRequests,
                ];
            }

            return $this->sendSuccess($months, 'تم جلب التقرير الشهري بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Monthly Report Error');
        }
    }

    /**
     * FR-103: تقرير الأنشطة (Audit Log)
     * GET /api/super-admin/reports/activities
     */
    public function activitiesReport(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $query = DB::table('audit_log')
                ->join('users', 'audit_log.user_id', '=', 'users.id')
                ->select(
                    'audit_log.id',
                    'audit_log.action',
                    'audit_log.table_name',
                    'audit_log.record_id',
                    'audit_log.created_at',
                    'users.full_name as user_name',
                    'users.type as user_type'
                );

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('audit_log.created_at', '>=', $request->input('start_date'));
            }

            if ($request->has('end_date')) {
                $query->whereDate('audit_log.created_at', '<=', $request->input('end_date'));
            }

            // Filter by user type
            if ($request->has('user_type')) {
                $query->where('users.type', $request->input('user_type'));
            }

            // Filter by action
            if ($request->has('action')) {
                $query->where('audit_log.action', $request->input('action'));
            }

            $activities = $query->orderBy('audit_log.created_at', 'desc')
                ->limit(100)
                ->get()
                ->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'action' => $activity->action,
                        'actionArabic' => $this->translateAction($activity->action),
                        'tableName' => $activity->table_name,
                        'recordId' => $activity->record_id,
                        'userName' => $activity->user_name,
                        'userType' => $activity->user_type,
                        'userTypeArabic' => $this->translateUserType($activity->user_type),
                        'createdAt' => Carbon::parse($activity->created_at)->format('Y-m-d H:i:s'),
                    ];
                });

            return $this->sendSuccess($activities, 'تم جلب تقرير الأنشطة بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Activities Report Error');
        }
    }

    /**
     * Helper: ترجمة نوع المؤسسة
     */
    private function translateType($type)
    {
        return match($type) {
            'hospital' => 'مستشفى',
            'health_center' => 'مركز صحي',
            'clinic' => 'عيادة',
            default => $type,
        };
    }

    /**
     * Helper: ترجمة نوع المستخدم
     */
    private function translateUserType($type)
    {
        return match($type) {
            'hospital_admin' => 'مدير نظام المستشفى',
            'supplier_admin' => 'مدير المورد',
            'super_admin' => 'المدير الأعلى',
            'warehouse_manager' => 'مسؤول المخزن',
            'pharmacist' => 'صيدلي',
            'doctor' => 'طبيب',
            'department_head' => 'مدير القسم',
            'patient' => 'مريض',
            default => $type,
        };
    }

    /**
     * Helper: ترجمة الحالة
     */
    private function translateStatus($status)
    {
        return match($status) {
            'active' => 'نشط',
            'inactive' => 'معطل',
            'pending_activation' => 'بانتظار التفعيل',
            default => $status,
        };
    }

    /**
     * Helper: ترجمة الإجراء
     */
    private function translateAction($action)
    {
        return match($action) {
            'created' => 'إنشاء',
            'updated' => 'تعديل',
            'deleted' => 'حذف',
            'approved' => 'موافقة',
            'rejected' => 'رفض',
            'dispensed' => 'صرف',
            default => $action,
        };
    }
}
