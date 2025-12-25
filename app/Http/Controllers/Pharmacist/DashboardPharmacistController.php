<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Dispensing;
use App\Models\Inventory;
use App\Models\Pharmacy;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\PrescriptionDrug;
use App\Models\InternalSupplyRequest;
use App\Models\InternalSupplyRequestItem;
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
     * سجل جميع عمليات الصيدلاني:
     * - صرف وصفة طبية (من Dispensing)
     * - إنشاء طلب توريد (من AuditLog)
     * - استلام شحنة (من AuditLog)
     * - إسناد دواء للمريض (من AuditLog)
     */
    public function operations(Request $request)
    {
        $user = $request->user();
        $pharmacyId = $this->getPharmacistPharmacyId($user);

        $operations = collect();

        // 1. جلب عمليات الصرف من جدول Dispensing
        $dispensingQuery = Dispensing::with(['patient', 'drug'])
            ->where('pharmacist_id', $user->id)
            ->orderBy('created_at', 'desc');
        
        if ($pharmacyId) {
            $dispensingQuery->where('pharmacy_id', $pharmacyId);
        }

        $dispensingOperations = $dispensingQuery->get()->map(function ($dispense) {
            $drugName = $dispense->drug ? ($dispense->drug->name ?? 'غير محدد') : 'غير محدد';
            $quantity = $dispense->quantity_dispensed ?? 0;
            
            return [
                'fileNumber' => $dispense->patient_id,
                'name' => $dispense->patient ? ($dispense->patient->full_name ?? $dispense->patient->name) : 'مريض غير معروف',
                'operationType' => 'صرف وصفة طبية',
                'operationDate' => Carbon::parse($dispense->created_at)->format('Y/m/d'),
                'operationDateTime' => Carbon::parse($dispense->created_at)->format('Y/m/d H:i'),
                'drugName' => $drugName,
                'quantity' => $quantity,
                'details' => "صرف {$quantity} من {$drugName}",
                'created_at' => $dispense->created_at,
            ];
        });

        $operations = $operations->merge($dispensingOperations);

        // 2. جلب طلبات التوريد مباشرة من جدول InternalSupplyRequest
        $supplyRequestQuery = InternalSupplyRequest::where('requested_by', $user->id)
            ->orderBy('created_at', 'desc');
        
        if ($pharmacyId) {
            $supplyRequestQuery->where('pharmacy_id', $pharmacyId);
        }

        $supplyRequestOperations = $supplyRequestQuery->get()->map(function ($request) {
            $itemCount = InternalSupplyRequestItem::where('request_id', $request->id)->count();
            
            // التأكد من أن created_at موجود وصحيح
            $createdAt = $request->created_at;
            if (!$createdAt) {
                $createdAt = $request->updated_at ?? Carbon::now();
            }
            
            return [
                'fileNumber' => 'REQ-' . $request->id,
                'name' => "طلب توريد ({$itemCount} عنصر)",
                'operationType' => 'إنشاء طلب توريد',
                'operationDate' => Carbon::parse($createdAt)->format('Y/m/d'),
                'operationDateTime' => Carbon::parse($createdAt)->format('Y/m/d H:i'),
                'details' => "طلب توريد يحتوي على {$itemCount} عنصر",
                'created_at' => $createdAt,
            ];
        });

        $operations = $operations->merge($supplyRequestOperations);

        // 3. جلب جميع العمليات من AuditLog للصيدلي
        // نستخدم orWhere بشكل منفصل لضمان التقاط جميع السجلات
        $auditLogs = AuditLog::where('user_id', $user->id)
            ->where(function($query) {
                // طلبات التوريد
                $query->where(function($q) {
                    $q->where('table_name', 'internal_supply_request')
                      ->orWhere('action', 'إنشاء طلب توريد')
                      ->orWhere('action', 'like', '%طلب%')
                      ->orWhere('action', 'like', '%توريد%');
                })
                // استلام الشحنات
                ->orWhere(function($q) {
                    $q->where('action', 'pharmacist_confirm_internal_receipt')
                      ->orWhere('action', 'استلام شحنة')
                      ->orWhere('action', 'like', '%استلام%')
                      ->orWhere('action', 'like', '%confirm%');
                })
                // إسناد الأدوية
                ->orWhere('table_name', 'prescription_drug')
                // التراجع عن صرف وصفة طبية
                ->orWhere(function($q) {
                    $q->where('action', 'تراجع عن صرف وصفة طبية')
                      ->orWhere('action', 'like', '%تراجع%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $auditOperations = $auditLogs->map(function ($log) {
            $operationData = [
                'operationType' => $this->translateAction($log->action),
                'operationDate' => $log->created_at->format('Y/m/d'),
                'operationDateTime' => $log->created_at->format('Y/m/d H:i'),
                'created_at' => $log->created_at,
            ];

            // معالجة إنشاء طلب توريد واستلام الشحنة
            $isSupplyRequest = $log->table_name === 'internal_supply_request';
            $actionContainsRequest = strpos($log->action, 'طلب') !== false || strpos($log->action, 'توريد') !== false;
            $actionContainsReceive = strpos($log->action, 'استلام') !== false || strpos($log->action, 'confirm') !== false;
            
            if ($isSupplyRequest || 
                $log->action === 'إنشاء طلب توريد' || 
                $log->action === 'pharmacist_confirm_internal_receipt' ||
                $actionContainsRequest ||
                $actionContainsReceive) {
                
                $newValues = $log->new_values ? json_decode($log->new_values, true) : null;
                $requestId = $newValues['request_id'] ?? $log->record_id ?? null;
                
                $operationData['fileNumber'] = $requestId ? 'REQ-' . $requestId : 'N/A';
                
                // تحديد نوع العملية
                if ($log->action === 'إنشاء طلب توريد' || 
                    $actionContainsRequest ||
                    ($isSupplyRequest && !$actionContainsReceive)) {
                    $itemCount = $newValues['item_count'] ?? 0;
                    $operationData['name'] = "طلب توريد ({$itemCount} عنصر)";
                    $operationData['operationType'] = 'إنشاء طلب توريد';
                } elseif ($log->action === 'pharmacist_confirm_internal_receipt' || 
                          $log->action === 'استلام شحنة' ||
                          $actionContainsReceive) {
                    $operationData['name'] = "استلام شحنة #{$requestId}";
                    $operationData['operationType'] = 'استلام شحنة';
                } else {
                    $operationData['name'] = 'طلب توريد';
                }
            }
            // معالجة التراجع عن صرف وصفة طبية
            elseif ($log->action === 'تراجع عن صرف وصفة طبية' || strpos($log->action, 'تراجع') !== false) {
                $newValues = $log->new_values ? json_decode($log->new_values, true) : null;
                
                if ($newValues) {
                    $operationData['fileNumber'] = $newValues['patient_id'] ?? $log->record_id ?? 'N/A';
                    $operationData['name'] = $newValues['patient_name'] ?? 'غير محدد';
                    
                    // جلب معلومات الأدوية
                    $drugsInfo = $newValues['drugs'] ?? [];
                    if (count($drugsInfo) > 0) {
                        $drugsText = collect($drugsInfo)->map(function($drug) {
                            $drugName = $drug['drug_name'] ?? 'غير محدد';
                            $quantity = $drug['quantity'] ?? 0;
                            return "{$drugName} ({$quantity})";
                        })->implode('، ');
                        
                        $operationData['drugName'] = $drugsText;
                        $operationData['details'] = "تراجع عن صرف: {$drugsText}";
                        
                        // إذا كان هناك دواء واحد فقط، نعرضه بشكل مباشر
                        if (count($drugsInfo) === 1) {
                            $operationData['drugName'] = $drugsInfo[0]['drug_name'] ?? 'غير محدد';
                            $operationData['quantity'] = $drugsInfo[0]['quantity'] ?? 0;
                        }
                    }
                } else {
                    $operationData['fileNumber'] = $log->record_id ?? 'N/A';
                    $operationData['name'] = 'غير محدد';
                }
                
                $operationData['operationType'] = 'تراجع عن صرف وصفة طبية';
            }
            // معالجة إسناد دواء للمريض
            elseif ($log->table_name === 'prescription_drug') {
                $newValues = $log->new_values ? json_decode($log->new_values, true) : null;
                $patientInfo = $newValues['patient_info'] ?? null;
                
                // محاولة جلب معلومات الدواء
                $drugName = null;
                $quantity = null;
                
                try {
                    $prescriptionDrug = PrescriptionDrug::with(['prescription.patient', 'drug'])
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
                            $operationData['name'] = $patient->full_name ?? $patient->name;
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
                                $operationData['name'] = $prescription->patient->full_name ?? $prescription->patient->name;
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
            // في حالة عدم تطابق أي نوع
            else {
                $operationData['fileNumber'] = $log->record_id ?? 'N/A';
                $operationData['name'] = 'غير محدد';
            }

            return $operationData;
        });

        $operations = $operations->merge($auditOperations);

        // دمج وفرز جميع العمليات حسب التاريخ والوقت الدقيق (الأحدث أولاً)
        $allOperations = $operations
            ->filter(function ($op) {
                // التأكد من وجود created_at للترتيب
                return isset($op['created_at']) && $op['created_at'] !== null;
            })
            ->map(function ($op) {
                // تحويل created_at إلى Carbon object ثم timestamp للترتيب الدقيق
                $carbonDate = null;
                try {
                    if (is_string($op['created_at'])) {
                        $carbonDate = Carbon::parse($op['created_at']);
                    } elseif (is_object($op['created_at'])) {
                        // إذا كان Carbon object مباشرة
                        if ($op['created_at'] instanceof \Carbon\Carbon) {
                            $carbonDate = $op['created_at'];
                        } elseif (method_exists($op['created_at'], 'timestamp')) {
                            $carbonDate = $op['created_at'];
                        } elseif (method_exists($op['created_at'], '__toString')) {
                            $carbonDate = Carbon::parse($op['created_at']->__toString());
                        } else {
                            $carbonDate = Carbon::parse((string)$op['created_at']);
                        }
                    } else {
                        $carbonDate = Carbon::now();
                    }
                } catch (\Exception $e) {
                    $carbonDate = Carbon::now();
                }
                
                // استخدام timestamp + microseconds للحصول على قيمة ترتيب دقيقة
                // نستخدم getTimestamp() للحصول على timestamp الدقيق
                $timestamp = $carbonDate->getTimestamp();
                // إضافة microseconds كجزء عشري
                $microseconds = $carbonDate->micro ?? 0;
                // دمج timestamp و microseconds في قيمة عائمة للترتيب الدقيق
                $op['_sortValue'] = (float)$timestamp + ((float)$microseconds / 1000000);
                return $op;
            })
            ->sortByDesc('_sortValue')
            ->values()
            ->map(function ($op) {
                // إزالة الحقول المؤقتة
                unset($op['created_at']);
                unset($op['_sortValue']);
                return $op;
            })
            ->take(100) // حد أقصى 100 عملية
            ->toArray();

        return $this->sendSuccess($allOperations, 'تم تحميل سجل العمليات بنجاح.');
    }

    /**
     * ترجمة نوع العملية إلى نص عربي واضح
     */
    private function translateAction($action)
    {
        $translations = [
            'إنشاء طلب توريد' => 'إنشاء طلب توريد',
            'pharmacist_confirm_internal_receipt' => 'استلام شحنة',
            'استلام شحنة' => 'استلام شحنة',
            'إضافة دواء' => 'إسناد دواء للمريض',
            'تعديل دواء' => 'تعديل دواء للمريض',
            'حذف دواء' => 'حذف دواء من المريض',
            'تراجع عن صرف وصفة طبية' => 'تراجع عن صرف وصفة طبية',
            'create' => 'إضافة',
            'update' => 'تعديل',
            'delete' => 'حذف',
            'assign' => 'إسناد دواء',
            'dispense' => 'صرف وصفة طبية',
            'undo' => 'تراجع',
        ];

        return $translations[$action] ?? $action;
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

        // 4. عدد طلبات التوريد (التي أنشأها الصيدلي)
        $supplyRequestsQuery = InternalSupplyRequest::where('requested_by', $user->id);
        
        if ($pharmacyId) {
            $supplyRequestsQuery->where('pharmacy_id', $pharmacyId);
        }
        
        $supplyRequestsCount = $supplyRequestsQuery->count();

        // 5. عدد عمليات استلام طلبات التوريد (الطلبات التي تم استلامها - status = 'fulfilled')
        $receivedRequestsQuery = InternalSupplyRequest::where('requested_by', $user->id)
            ->where('status', 'fulfilled');
        
        if ($pharmacyId) {
            $receivedRequestsQuery->where('pharmacy_id', $pharmacyId);
        }
        
        $receivedRequestsCount = $receivedRequestsQuery->count();

        $data = [
            'totalRegistered' => $dispensingTodayCount,
            'todayRegistered' => $criticalStockCount,
            'weekRegistered'  => $patientsWeekCount,
            'supplyRequestsCount' => $supplyRequestsCount,
            'receivedRequestsCount' => $receivedRequestsCount,
        ];

        return $this->sendSuccess($data, 'تم تحميل إحصائيات الصيدلي بنجاح.');
    }
}
