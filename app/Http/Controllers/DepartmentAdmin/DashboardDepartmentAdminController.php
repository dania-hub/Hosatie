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
                    
                    // محاولة جلب معلومات الدواء
                    $drugName = null;
                    $quantity = null;
                    
                    try {
                        $prescriptionDrug = \App\Models\PrescriptionDrug::with(['prescription.patient', 'drug'])
                            ->find($log->record_id);
                        
                        if ($prescriptionDrug) {
                            // جلب معلومات الدواء
                            if ($prescriptionDrug->drug) {
                                $drugName = $prescriptionDrug->drug->name ?? null;
                            }
                            $quantity = $prescriptionDrug->monthly_quantity ?? null;
                            
                            // جلب معلومات المريض
                            if ($prescriptionDrug->prescription && $prescriptionDrug->prescription->patient) {
                                $patient = $prescriptionDrug->prescription->patient;
                                $operationData['fileNumber'] = $patient->id;
                                $operationData['name'] = $patient->full_name;
                            }
                        } else {
                            // في حالة الحذف، محاولة جلب المعلومات من old_values أو new_values
                            $oldValues = $log->old_values ? json_decode($log->old_values, true) : null;
                            
                            // محاولة جلب معلومات الدواء من new_values أو old_values
                            if ($newValues && isset($newValues['drug_id'])) {
                                $drug = \App\Models\Drug::find($newValues['drug_id']);
                                if ($drug) {
                                    $drugName = $drug->name;
                                }
                                $quantity = $newValues['monthly_quantity'] ?? null;
                            } elseif ($oldValues && isset($oldValues['drug_id'])) {
                                $drug = \App\Models\Drug::find($oldValues['drug_id']);
                                if ($drug) {
                                    $drugName = $drug->name;
                                }
                                $quantity = $oldValues['monthly_quantity'] ?? null;
                            }
                            
                            // محاولة جلب معلومات المريض
                            if ($oldValues && isset($oldValues['prescription_id'])) {
                                $prescription = \App\Models\Prescription::with('patient')->find($oldValues['prescription_id']);
                                if ($prescription && $prescription->patient) {
                                    $operationData['fileNumber'] = $prescription->patient->id;
                                    $operationData['name'] = $prescription->patient->full_name;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // في حالة الخطأ، نحاول جلب معلومات المريض من patient_info
                        if ($patientInfo && isset($patientInfo['id']) && isset($patientInfo['full_name'])) {
                            $operationData['fileNumber'] = $patientInfo['id'];
                            $operationData['name'] = $patientInfo['full_name'];
                        } else {
                            $operationData['fileNumber'] = $log->record_id ?? 'N/A';
                            $operationData['name'] = 'غير محدد';
                        }
                    }
                    
                    // إضافة معلومات الدواء إذا كانت متوفرة
                    if ($drugName) {
                        $operationData['drugName'] = $drugName;
                    }
                    if ($quantity !== null) {
                        $operationData['quantity'] = $quantity;
                    }
                    
                    // إذا لم يتم تعيين معلومات المريض بعد، نحاول من patient_info
                    if (!isset($operationData['fileNumber']) || $operationData['fileNumber'] === 'N/A') {
                        if ($patientInfo && isset($patientInfo['id']) && isset($patientInfo['full_name'])) {
                            $operationData['fileNumber'] = $patientInfo['id'];
                            $operationData['name'] = $patientInfo['full_name'];
                        } else {
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
