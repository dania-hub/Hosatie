<?php

namespace App\Observers;

use App\Models\PrescriptionDrug;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PrescriptionDrugObserver
{
    /**
     * Handle the PrescriptionDrug "created" event.
     */
    public function created(PrescriptionDrug $prescriptionDrug)
    {
        // ✅ إضافة منطق daily_quantity → monthly_quantity عند الإنشاء
        if ($prescriptionDrug->daily_quantity && !$prescriptionDrug->monthly_quantity) {
            $prescriptionDrug->monthly_quantity = $prescriptionDrug->daily_quantity * 30;
            $prescriptionDrug->save(); // حفظ التغيير
        }

        Log::info('Observer Created Triggered', ['id' => $prescriptionDrug->id]);
        $this->logAction('إضافة دواء', $prescriptionDrug);
    }

    /**
     * Handle the PrescriptionDrug "updated" event.
     */
    public function updated(PrescriptionDrug $prescriptionDrug)
    {
        // ✅ عند التحديث: لا نغيّر monthly_quantity حتى لو تغيّر daily_quantity
        // هذا المنطق يطبّق على الإنشاء فقط
        
        Log::info('Observer Updated Triggered', ['id' => $prescriptionDrug->id]);
        $this->logAction('تعديل دواء', $prescriptionDrug, $prescriptionDrug->getOriginal());
    }

    /**
     * Handle the PrescriptionDrug "deleted" event.
     */
    public function deleted(PrescriptionDrug $prescriptionDrug)
    {
        Log::info('Observer Deleted Triggered', ['id' => $prescriptionDrug->id]);
        
        // عند الحذف، يجب حفظ معلومات المريض قبل أن يتم حذف السجل
        $patientInfo = null;
        if ($prescriptionDrug->prescription_id) {
            $prescription = \App\Models\Prescription::with('patient')->find($prescriptionDrug->prescription_id);
            if ($prescription && $prescription->patient) {
                $patientInfo = [
                    'id' => $prescription->patient->id,
                    'full_name' => $prescription->patient->full_name,
                ];
            }
        }
        
        $this->logAction('حذف دواء', $prescriptionDrug, null, $patientInfo);
    }

    /**
     * Helper to save the log
     */
    protected function logAction($action, $record, $oldValues = null, $patientInfo = null)
    {
        // 1. Try getting user from Auth Facade
        $userId = Auth::id();

        // 2. Fallback: Try getting user from Request (API specific)
        if (!$userId) {
            $userId = request()->user() ? request()->user()->id : null;
        }

        // 3. If User ID found, Create Log
        if ($userId) {
            // إضافة معلومات المريض إلى new_values لتسهيل العرض لاحقاً
            $newValuesArray = [];
            
            // محاولة جلب معلومات المريض
            if ($patientInfo) {
                // إذا تم تمرير معلومات المريض مباشرة (في حالة الحذف)
                $newValuesArray['patient_info'] = $patientInfo;
            } elseif ($record->prescription_id) {
                // محاولة جلب معلومات المريض من الروشتة
                $prescription = \App\Models\Prescription::with('patient')->find($record->prescription_id);
                if ($prescription && $prescription->patient) {
                    $newValuesArray['patient_info'] = [
                        'id' => $prescription->patient->id,
                        'full_name' => $prescription->patient->full_name,
                    ];
                }
            }
            
            // إضافة بيانات السجل إذا كان متاحاً
            try {
                $recordArray = $record->toArray();
                $newValuesArray = array_merge($recordArray, $newValuesArray);
            } catch (\Exception $e) {
                // في حالة الحذف، قد لا تكون البيانات متاحة
                Log::info('Could not get record array, using patient info only', ['error' => $e->getMessage()]);
            }
            
            AuditLog::create([
                'user_id'    => $userId,
                'action'     => $action,
                'table_name' => 'prescription_drugs',
                'record_id'  => $record->id,
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => json_encode($newValuesArray),
                'ip_address' => request()->ip(),
            ]);
        } else {
            Log::warning("AuditLog Skipped: No User ID found for action {$action}");
        }
    }
}
