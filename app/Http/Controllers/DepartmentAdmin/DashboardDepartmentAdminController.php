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
     * Returns all operations performed by the current user: drug operations, supply requests, and shipments
     */
    public function operations(Request $request)
    {
        $user = $request->user();

        // جلب جميع العمليات التي قام بها المستخدم
        $logs = \App\Models\AuditLog::where('user_id', $user->id)
            ->where(function($query) {
                $query->where('table_name', 'prescription_drug')
                      ->orWhere('table_name', 'internal_supply_request');
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($log) {
                $operationData = [
                    'operationType' => $log->action,
                    'operationDate' => $log->created_at->format('Y/m/d'),
                    'operationDateTime' => $log->created_at->format('Y/m/d H:i'),
                ];

                // معالجة عمليات الأدوية (إضافة/تعديل/حذف دواء)
                if ($log->table_name === 'prescription_drug') {
                    $newValues = $log->new_values ? json_decode($log->new_values, true) : null;
                    $patientInfo = $newValues['patient_info'] ?? null;
                    
                    // محاولة جلب معلومات المريض من new_values (الأولوية)
                    if ($patientInfo && isset($patientInfo['id']) && isset($patientInfo['full_name'])) {
                        $operationData['fileNumber'] = $patientInfo['id'];
                        $operationData['name'] = $patientInfo['full_name'];
                    } else {
                        // محاولة جلب معلومات المريض من prescription_drug (إذا كان السجل موجوداً)
                        try {
                            $prescriptionDrug = \App\Models\PrescriptionDrug::with('prescription.patient')
                                ->find($log->record_id);
                            if ($prescriptionDrug && $prescriptionDrug->prescription && $prescriptionDrug->prescription->patient) {
                                $patient = $prescriptionDrug->prescription->patient;
                                $operationData['fileNumber'] = $patient->id;
                                $operationData['name'] = $patient->full_name;
                            } else {
                                // في حالة الحذف، قد لا يكون السجل موجوداً، نستخدم old_values
                                $oldValues = $log->old_values ? json_decode($log->old_values, true) : null;
                                if ($oldValues && isset($oldValues['prescription_id'])) {
                                    $prescription = \App\Models\Prescription::with('patient')->find($oldValues['prescription_id']);
                                    if ($prescription && $prescription->patient) {
                                        $operationData['fileNumber'] = $prescription->patient->id;
                                        $operationData['name'] = $prescription->patient->full_name;
                                    } else {
                                        $operationData['fileNumber'] = $log->record_id ?? 'N/A';
                                        $operationData['name'] = 'غير محدد';
                                    }
                                } else {
                                    $operationData['fileNumber'] = $log->record_id ?? 'N/A';
                                    $operationData['name'] = 'غير محدد';
                                }
                            }
                        } catch (\Exception $e) {
                            // في حالة الخطأ، نستخدم القيم الافتراضية
                            $operationData['fileNumber'] = $log->record_id ?? 'N/A';
                            $operationData['name'] = 'غير محدد';
                        }
                    }
                }
                // معالجة عمليات طلبات التوريد والشحنات
                elseif ($log->table_name === 'internal_supply_request') {
                    $newValues = $log->new_values ? json_decode($log->new_values, true) : null;
                    // استخدام record_id مباشرة إذا لم يكن request_id موجوداً في new_values
                    $requestId = $newValues['request_id'] ?? $log->record_id ?? null;
                    
                    // للطلبات والشحنات، نعرض رقم الطلب كـ fileNumber
                    $operationData['fileNumber'] = $requestId ? 'REQ-' . $requestId : 'N/A';
                    
                    // عرض معلومات إضافية حسب نوع العملية
                    if ($log->action === 'إنشاء طلب توريد') {
                        $itemCount = $newValues['item_count'] ?? 0;
                        $operationData['name'] = "طلب توريد ({$itemCount} عنصر)";
                    } elseif ($log->action === 'استلام شحنة') {
                        $operationData['name'] = "استلام شحنة #{$requestId}";
                    } else {
                        $operationData['name'] = 'طلب توريد';
                    }
                }
                // في حالة عدم تطابق أي نوع
                else {
                    $operationData['fileNumber'] = $log->record_id ?? 'N/A';
                    $operationData['name'] = 'غير محدد';
                }

                return $operationData;
            });

        return $this->sendSuccess($logs, 'تم جلب سجل العمليات بنجاح.');
    }
}
