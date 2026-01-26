<?php

namespace App\Observers;

use App\Models\PrescriptionDrug;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PrescriptionDrugObserver
{
    /**
     * متغير ثابت (static) لمنع الإشعارات المكررة
     * عندما يكون true، يتخطى Observer إرسال الإشعارات
     * يقوم Controller بتعيينه مؤقتاً أثناء إرسال الإشعار
     */
    public static $skipNotification = false;
    public static $skipLogging = false;

    /**
     * Handle the PrescriptionDrug "creating" event.
     * يتم تنفيذ هذا الحدث قبل حفظ السجل في قاعدة البيانات
     */
    public function creating(PrescriptionDrug $prescriptionDrug)
    {
        // ✅ حساب monthly_quantity قبل الإنشاء
        if ($prescriptionDrug->daily_quantity && !$prescriptionDrug->monthly_quantity) {
            $prescriptionDrug->monthly_quantity = $prescriptionDrug->daily_quantity * 30;
        }
    }

    /**
     * Handle the PrescriptionDrug "created" event.
     */
    public function created(PrescriptionDrug $prescriptionDrug)
    {
        Log::info('Observer Created Triggered', ['id' => $prescriptionDrug->id]);
        if (!self::$skipLogging) {
            $this->logAction('إضافة دواء', $prescriptionDrug);
        }

        // ✅ التحقق من حالة skipNotification لتجنب الإشعار المكرر
        if (self::$skipNotification) {
            Log::info('Observer Created: SKIPPING notification - Controller will send it', [
                'id' => $prescriptionDrug->id,
                'skipNotification' => self::$skipNotification
            ]);
            return;
        }

        Log::info('Observer Created: Sending notification', ['id' => $prescriptionDrug->id]);
    }

    /**
     * Handle the PrescriptionDrug "updated" event.
     */
    public function updated(PrescriptionDrug $prescriptionDrug)
    {
        Log::info('Observer Updated Triggered', ['id' => $prescriptionDrug->id]);
        if (!self::$skipLogging) {
            $this->logAction('تعديل دواء', $prescriptionDrug, $prescriptionDrug->getOriginal());
        }

        // ✅ التحقق من حالة skipNotification لتجنب الإشعار المكرر
        if (self::$skipNotification) {
            Log::info('Observer Updated: SKIPPING notification - Controller will send it', [
                'id' => $prescriptionDrug->id,
                'skipNotification' => self::$skipNotification
            ]);
            return;
        }

        // ✅ عند التحديث: لا نغيّر monthly_quantity حتى لو تغيّر daily_quantity
        // هذا المنطق يطبّق على الإنشاء فقط
        
        Log::info('Observer Updated: Sending notification', ['id' => $prescriptionDrug->id]);
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
        
        if (!self::$skipLogging) {
            $this->logAction('حذف دواء', $prescriptionDrug, null, $patientInfo);
        }

        // ✅ التحقق من حالة skipNotification لتجنب الإشعار المكرر
        if (self::$skipNotification) {
            Log::info('Observer Deleted: SKIPPING notification - Controller will send it', [
                'id' => $prescriptionDrug->id,
                'skipNotification' => self::$skipNotification
            ]);
            return;
        }
        
        Log::info('Observer Deleted: Sending notification', ['id' => $prescriptionDrug->id]);
    }

    /**
     * Helper to save the log
     */
    protected function logAction($action, $record, $oldValues = null, $patientInfo = null)
    {
        // 1. Try getting user from Auth Facade
        $user = Auth::user();
        $userId = $user ? $user->id : null;

        // 2. Fallback: Try getting user from Request (API specific)
        if (!$userId) {
            $user = request()->user();
            $userId = $user ? $user->id : null;
        }

       
        if ($user && $user->type === 'pharmacist' && $action === 'تعديل دواء') {
            $action = 'صرف دواء';
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
                
                // حساب الكمية المصروفة بدقة
                if ($action === 'صرف دواء' && $oldValues && isset($oldValues['monthly_quantity'])) {
                    $oldQty = (int)$oldValues['monthly_quantity'];
                    $newQty = (int)($recordArray['monthly_quantity'] ?? 0);
                    
                    // الصرف يعني بالضرورة نقص الكمية
                    if ($newQty < $oldQty) {
                        $newValuesArray['dispensed_quantity'] = $oldQty - $newQty;
                    } else {
                        // إذا زادت الكمية أو لم تتغير، فهي عملية تعديل وليست صرف
                        $action = 'تعديل دواء';
                        $newValuesArray['dispensed_quantity'] = 0;
                    }
                }
            } catch (\Exception $e) {
                // في حالة الحذف، قد لا تكون البيانات متاحة
                Log::info('Could not get record array, using patient info only', ['error' => $e->getMessage()]);
            }
            
            AuditLog::create([
                'user_id'    => $userId,
                'hospital_id' => $user->hospital_id ?? null,
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
