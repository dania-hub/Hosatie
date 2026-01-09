<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use App\Models\Hospital;
use App\Models\Drug;
use App\Models\User;
use App\Models\Supplier;
use App\Models\Inventory;
use App\Models\Department;
use App\Models\Pharmacy;
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
                    'departments as departments_count',
                    'pharmacies as pharmacies_count'
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
                            'departmentsCount' => $hospital->departments_count,
                            'pharmaciesCount' => $hospital->pharmacies_count,
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
     * FR-101: تقرير عمليات الصرف
     * GET /api/super-admin/reports/dispensings
     */
    public function dispensingsReport(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $dispensings = Dispensing::with(['pharmacy', 'patient', 'pharmacist', 'drug', 'prescription'])
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get()
                ->map(function ($d) {
                    return [
                        'id' => $d->id,
                        'pharmacy' => $d->pharmacy ? $d->pharmacy->name : 'غير محدد',
                        'patient' => $d->patient ? $d->patient->full_name : 'غير محدد',
                        'pharmacist' => $d->pharmacist ? $d->pharmacist->full_name : 'غير محدد',
                        'drug' => $d->drug ? $d->drug->name : 'غير محدد',
                        'prescriptionId' => $d->prescription ? $d->prescription->id : '-',
                         'quantity' => $d->quantity_dispensed,
                         // Use dispense_month or created_at if available
                         'date' => $d->created_at ? $d->created_at->format('Y-m-d H:i') : ($d->dispense_month ? $d->dispense_month->format('Y-m-d') : '-'),
                         'status' => $d->reverted ? 'reverted' : 'completed',
                         'statusArabic' => $d->reverted ? 'تم الإرجاع' : 'تم الصرف',
                    ];
                });

            return $this->sendSuccess($dispensings, 'تم جلب تقرير الصرف بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Dispensings Report Error');
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
     * FR-102-Detail: تفاصيل الطلبات الشهرية
     * GET /api/super-admin/reports/requests-monthly/details
     */
    public function requestsMonthlyDetails(Request $request)
    {
        try {
            $user = $request->user();
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $month = $request->input('month'); // YYYY-MM
            if (!$month) {
                return $this->sendError('يرجى تحديد الشهر', null, 400);
            }

            $date = Carbon::createFromFormat('Y-m', $month);
            $year = $date->year;
            $monthNum = $date->month;

            // 1. Internal Requests (Pharmacy -> Warehouse)
            $internal = InternalSupplyRequest::with(['pharmacy.hospital', 'requester'])
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $monthNum)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'displayId' => 'INT-' . $item->id,
                        'type' => 'internal',
                        'typeArabic' => 'طلب داخلي',
                        'sender' => $item->pharmacy ? $item->pharmacy->name : 'صيدلية',
                        'receiver' => $item->pharmacy && $item->pharmacy->hospital ? $item->pharmacy->hospital->name : 'مخزن',
                        'date' => $item->created_at->format('Y-m-d'),
                        'status' => $item->status,
                        'statusArabic' => $this->translateStatus($item->status),
                        'itemsCount' => $item->items()->count(),
                    ];
                });

            // 2. External Requests (Hospital -> Supplier)
            $external = ExternalSupplyRequest::with(['hospital', 'supplier', 'requester'])
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $monthNum)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'displayId' => 'EXT-' . $item->id,
                        'type' => 'external',
                        'typeArabic' => 'طلب خارجي',
                        'sender' => $item->hospital ? $item->hospital->name : 'مستشفى',
                        'receiver' => $item->supplier ? $item->supplier->name : 'مورد',
                        'date' => $item->created_at->format('Y-m-d'),
                        'status' => $item->status,
                        'statusArabic' => $this->translateStatus($item->status),
                        'itemsCount' => $item->items()->count(),
                    ];
                });

             // 3. Transfer Requests
             $transfers = PatientTransferRequest::with(['fromHospital', 'toHospital'])
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $monthNum)
                ->get()
                ->map(function ($item) {
                     return [
                        'id' => $item->id,
                        'displayId' => 'TRF-' . $item->id,
                        'type' => 'transfer',
                        'typeArabic' => 'طلب تحويل',
                        'sender' => $item->fromHospital ? $item->fromHospital->name : '-',
                        'receiver' => $item->toHospital ? $item->toHospital->name : '-',
                        'date' => $item->created_at->format('Y-m-d'),
                        'status' => $item->status,
                        'statusArabic' => $this->translateStatus($item->status),
                        'itemsCount' => 1,
                     ];
                });

            $all = $internal->concat($external)->concat($transfers)->sortByDesc('date')->values();

            return $this->sendSuccess($all, 'تم جلب تفاصيل الطلبات');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Error fetching monthly details');
        }
    }

    /**
     * FR-102-Items: جلب عناصر الطلب
     * GET /api/super-admin/reports/request-items
     */
    public function getRequestItems(Request $request)
    {
        try {
            $user = $request->user();
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $type = $request->input('type');
            $id = $request->input('id');

            if (!$type || !$id) {
                return $this->sendError('بيانات غير مكتملة', null, 400);
            }

            $items = [];

            if ($type === 'internal') {
                $requestModel = InternalSupplyRequest::with('items.drug')->find($id);
                if ($requestModel) {
                    $items = $requestModel->items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->drug ? $item->drug->name : 'دواء غير معروف',
                            'type' => 'drug',
                            'qty' => $item->requested_qty,
                            'approved_qty' => $item->approved_qty ?? '-',
                            'status' => 'success' 
                        ];
                    });
                }
            } elseif ($type === 'external') {
                $requestModel = ExternalSupplyRequest::with('items.drug')->find($id);
                if ($requestModel) {
                    $items = $requestModel->items->map(function($item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->drug ? $item->drug->name : 'دواء غير معروف',
                            'type' => 'drug',
                            'qty' => $item->requested_qty,
                            'approved_qty' => $item->approved_qty ?? '-',
                        ];
                    });
                }
            } elseif ($type === 'transfer') {
                $requestModel = PatientTransferRequest::with('patient')->find($id);
                if ($requestModel && $requestModel->patient) {
                    $items[] = [
                        'id' => $requestModel->patient->id,
                        'name' => $requestModel->patient->full_name,
                        'type' => 'patient',
                        'qty' => 1,
                        'details' => 'تحويل مريض: ' . ($requestModel->reason ?? 'لا يوجد سبب'),
                    ];
                }
            }

            return $this->sendSuccess($items, 'تم جلب العناصر بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Error fetching request items');
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

            $query = DB::table('audit_logs')
                ->join('users', 'audit_logs.user_id', '=', 'users.id')
                ->select(
                    'audit_logs.id',
                    'audit_logs.action',
                    'audit_logs.table_name',
                    'audit_logs.record_id',
                    'audit_logs.old_values',
                    'audit_logs.new_values',
                    'audit_logs.created_at',
                    'users.full_name as user_name',
                    'users.type as user_type'
                );

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('audit_logs.created_at', '>=', $request->input('start_date'));
            }

            if ($request->has('end_date')) {
                $query->whereDate('audit_logs.created_at', '<=', $request->input('end_date'));
            }

            // Filter by user type
            if ($request->has('user_type')) {
                $query->where('users.type', $request->input('user_type'));
            }

            // Filter by action
            if ($request->has('action')) {
                $query->where('audit_logs.action', $request->input('action'));
            }

            $activities = $query->orderBy('audit_logs.created_at', 'desc')
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
                        'details' => [
                            'old' => json_decode($activity->old_values, true),
                            'new' => json_decode($activity->new_values, true)
                        ]
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
            // General Actions
            'created' => 'إنشاء',
            'create' => 'إنشاء',
            'updated' => 'تعديل',
            'update' => 'تعديل',
            'deleted' => 'حذف',
            'delete' => 'حذف',
            'approved' => 'موافقة',
            'rejected' => 'رفض',
            'dispensed' => 'صرف',
            'login' => 'تسجيل دخول',
            'logout' => 'تسجيل خروج',
            'view' => 'عرض',
            'restore' => 'استعادة',
            'force_delete' => 'حذف نهائي',
            'confirmed' => 'تأكيد',
            'cancelled' => 'إلغاء',
            'activated' => 'تفعيل',
            'deactivated' => 'تعطيل',
            'suspended' => 'إيقاف',
            
            // Patient Actions
            'create_patient' => 'إضافة ملف مريض',
            'update_patient' => 'تعديل بيانات مريض',
            'delete_patient' => 'حذف ملف مريض',
            'view_patient' => 'عرض ملف مريض',
            
            // Drug Actions
            'create_drug' => 'إضافة دواء جديد',
            'update_drug' => 'تعديل بيانات دواء',
            'delete_drug' => 'حذف دواء',
            'dispense_drug' => 'صرف دواء',
            
            // User Actions
            'create_user' => 'إضافة مستخدم',
            'update_user' => 'تعديل بيانات مستخدم',
            'delete_user' => 'حذف مستخدم',
            'block_user' => 'حظر مستخدم',
            
            // Hospital/Supplier Actions
            'create_hospital' => 'إضافة مؤسسة صحية',
            'update_hospital' => 'تعديل بيانات مؤسسة',
            'create_supplier' => 'إضافة مورد',
            'update_supplier' => 'تعديل بيانات مورد',
            
            // Request/Order Actions
            'create_order' => 'إنشاء طلب',
            'update_order' => 'تعديل طلب',
            'cancel_order' => 'إلغاء طلب',
            'approve_order' => 'الموافقة على طلب',
            'reject_order' => 'رفض طلب',

             // External Supply Requests (General/StoreKeeper)
            'create_external_supply_request' => 'إنشاء طلب توريد خارجي',
            'update_external_supply_request' => 'تعديل طلب توريد خارجي',
            'delete_external_supply_request' => 'حذف طلب توريد خارجي',
            'approve_external_supply_request' => 'الموافقة على طلب توريد خارجي',
            'reject_external_supply_request' => 'رفض طلب توريد خارجي',
            'cancel_external_supply_request' => 'إلغاء طلب توريد خارجي',

            // Supplier Specific Actions
            'supplier_create_external_supply_request' => 'إنشاء طلب توريد (مورد)',
            'supplier_update_external_supply_request' => 'تعديل طلب توريد (مورد)',
            'supplier_cancel_external_supply_request' => 'إلغاء طلب توريد (مورد)',
            'supplier_ship_external_supply_request' => 'شحن طلب توريد',
            'super_admin_confirm_external_supply_request' => 'تأكيد استلام طلب توريد (إدارة)',

            // Internal Supply Requests
            'create_internal_supply_request' => 'إنشاء طلب صرف داخلي',
            'update_internal_supply_request' => 'تعديل طلب صرف داخلي',
            'delete_internal_supply_request' => 'حذف طلب صرف داخلي',
            'approve_internal_supply_request' => 'الموافقة على طلب صرف داخلي',
            'reject_internal_supply_request' => 'رفض طلب صرف داخلي',
            'dispense_internal_supply_request' => 'صرف طلب داخلي',
            'acknowledge_internal_supply_request' => 'استلام طلب صرف داخلي',

            // Patient Transfer Requests
            'create_patient_transfer_request' => 'إنشاء طلب نقل مريض',
            'update_patient_transfer_request' => 'تعديل طلب نقل مريض',
            'approve_patient_transfer_request' => 'الموافقة على نقل مريض',
            'reject_patient_transfer_request' => 'رفض نقل مريض',

            default => $action,
        };
    }

    /**
     * تفاصيل أقسام المستشفى
     */
    public function getHospitalDepartments(Request $request)
    {
        try {
            $hospitalId = $request->input('hospital_id');
            if (!$hospitalId) {
                return $this->sendError('Hospital ID is required');
            }

            $departments = Department::where('hospital_id', $hospitalId)
                ->with('head')
                ->get()
                ->map(function($dept) {
                    return [
                        'id' => $dept->id,
                        'name' => $dept->name,
                        'status' => $dept->status,
                        'head' => ($dept->head && $dept->head->full_name) ? $dept->head->full_name : 'غير محدد',
                    ];
                });

            return $this->sendSuccess($departments, 'Department details retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving departments: ' . $e->getMessage());
        }
    }

    /**
     * تفاصيل صيدليات المستشفى ومخزونها
     */
    public function getHospitalPharmacies(Request $request)
    {
        try {
            $hospitalId = $request->input('hospital_id');
            if (!$hospitalId) {
                return $this->sendError('Hospital ID is required');
            }

            $pharmacies = Pharmacy::where('hospital_id', $hospitalId)
                ->with(['inventories.drug'])
                ->get()
                ->map(function($pharmacy) {
                   
                    // تحضير قائمة الجرد
                    $inventoryItems = $pharmacy->inventories->map(function($inv) {
                         // Check for trade_name first, then fallback to name, then generic_name
                         $drugName = 'Unknown Drug';
                         if ($inv->drug) {
                             $drugName = $inv->drug->trade_name ?? $inv->drug->name ?? $inv->drug->generic_name ?? 'Unknown Drug';
                         }
                         
                         return [
                            'drug_name' => $drugName,
                            'quantity' => $inv->current_quantity ?? $inv->quantity ?? 0,
                            'batch_number' => $inv->batch_number ?? '-',
                            'expiry_date' => $inv->drug ? $inv->drug->expiry_date : '-',
                         ];
                    })->values(); // Reset keys to ensure JSON array

                    return [
                        'id' => $pharmacy->id,
                        'name' => $pharmacy->name,
                        'inventory' => $inventoryItems
                    ];
                });

            return $this->sendSuccess($pharmacies, 'Pharmacy details retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving pharmacies: ' . $e->getMessage());
        }
    }
}
