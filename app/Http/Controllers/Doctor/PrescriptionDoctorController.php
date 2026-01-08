<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Prescription;
use App\Models\PrescriptionDrug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Observers\PrescriptionDrugObserver;
use App\Services\ResalaService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Services\PatientNotificationService;

class PrescriptionDoctorController extends BaseApiController
{
    /**
     * Add Drugs (Auto-create Prescription if missing)
     */
    public function store(Request $request, $patientId, ResalaService $resalaService, PatientNotificationService $notificationService)
    {
        Log::info('🔄 ========== START store() ==========', ['patientId' => $patientId]);
        
        try {
            $request->validate([
                'medications' => 'required|array',
                'medications.*.drug_id' => 'required|exists:drugs,id',
                'medications.*.quantity' => 'required|integer|min:1',
            ]);
            
            DB::beginTransaction();

            $hospitalId = $request->user()->hospital_id;
            $doctorId   = $request->user()->id;

            $patient = \App\Models\User::where('type', 'patient')
                ->where('hospital_id', $hospitalId)
                ->where('id', $patientId)
                ->first();

            if (!$patient) {
                DB::rollBack();
                Log::error('❌ Patient not found');
                return $this->sendError('المريض غير موجود أو غير مرتبط بنفس المستشفى.', [], 404);
            }

            // إرسال رسالة التفعيل إذا كان بانتظار التفعيل (دون تغيير الحالة)
            if ($patient->status === 'pending_activation') {
                $plainPassword = Str::random(8); // إنشاء كلمة مرور عشوائية
                
                $patient->update([
                    'password' => Hash::make($plainPassword),
                    // 'status'   => 'active' // تم التعطيل بناءً على طلب المستخدم
                ]);

                // إرسال رسالة التفعيل
                $resalaService->sendActivationSms($patient->phone, $plainPassword);
                
                Log::info('✅ Activation SMS sent (status remains pending_activation)', [
                    'patient_id' => $patient->id,
                    'phone' => $patient->phone
                ]);
            }

            $prescription = Prescription::where('patient_id', $patientId)
                ->where('status', 'active')
                ->first();

            if (!$prescription) {
                try {
                    if (!$hospitalId) {
                        DB::rollBack();
                        return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
                    }
                    
                    if (!$doctorId) {
                        DB::rollBack();
                        return $this->sendError('المستخدم غير موجود.', [], 400);
                    }
                    
                    $prescription = Prescription::create([
                        'patient_id' => (int)$patientId,
                        'hospital_id'=> (int)$hospitalId,
                        'doctor_id'  => (int)$doctorId,
                        'start_date' => \Carbon\Carbon::today()->format('Y-m-d'),
                        'status'     => 'active',
                    ]);
                    Log::info('✅ Prescription created', ['id' => $prescription->id]);
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Error creating prescription: ' . $e->getMessage());
                    return $this->sendError('حدث خطأ أثناء إنشاء الوصفة.', [
                        'error' => config('app.debug') ? $e->getMessage() . ' في ' . $e->getFile() . ':' . $e->getLine() : 'خطأ في إنشاء الوصفة'
                    ], 500);
                }
            } else {
                if ($prescription->hospital_id != $hospitalId) {
                    $prescription->hospital_id = (int)$hospitalId;
                    $prescription->doctor_id = (int)$doctorId;
                    $prescription->save();
                }
            }

            $createdDrugs = [];
            
            // ✅ منع Observer من إرسال إشعار مكرر - قبل إنشاء الأدوية مباشرة
            Log::info('🔧 Setting skipNotification = TRUE before creating drugs');
            PrescriptionDrugObserver::$skipNotification = true;
            Log::info('🔧 skipNotification after setting', ['value' => PrescriptionDrugObserver::$skipNotification]);

            foreach ($request->medications as $med) {
                $exists = PrescriptionDrug::where('prescription_id', $prescription->id)
                            ->where('drug_id', $med['drug_id'])
                            ->exists();
                
                if (!$exists) {
                    try {
                        $monthlyQuantity = $med['quantity'];
                        $dailyQuantity = isset($med['daily_quantity']) && $med['daily_quantity'] !== null 
                            ? (int)$med['daily_quantity'] 
                            : null;
                        
                        Log::info('💊 Creating prescription drug', [
                            'prescription_id' => $prescription->id,
                            'drug_id' => $med['drug_id'],
                            'skipNotification' => PrescriptionDrugObserver::$skipNotification
                        ]);
                        
                        $createdDrugs[] = PrescriptionDrug::create([
                            'prescription_id' => $prescription->id,
                            'drug_id'         => $med['drug_id'],
                            'monthly_quantity'=> $monthlyQuantity,
                            'daily_quantity'  => $dailyQuantity,
                        ]);
                        
                        Log::info('✅ Drug created successfully', ['drug_id' => $med['drug_id']]);
                    } catch (\Exception $e) {
                        PrescriptionDrugObserver::$skipNotification = false;
                        DB::rollBack();
                        Log::error('Error creating prescription drug: ' . $e->getMessage());
                        return $this->sendError('حدث خطأ أثناء إضافة الدواء.', [
                            'error' => config('app.debug') ? $e->getMessage() : 'خطأ في إضافة الدواء',
                            'drug_id' => $med['drug_id'] ?? null
                        ], 500);
                    }
                }
            }

            DB::commit();
            
            // تحديث updated_at للمريض ليظهر في بداية القائمة
            $patient->touch();
            
            if (!empty($createdDrugs)) {
                $prescription->loadMissing('patient');
                Log::info('✅ Drugs created successfully');

                // Trigger Push Notifications
                foreach ($createdDrugs as $pd) {
                    $pd->loadMissing('drug');
                    $notificationService->notifyDrugAssigned($patient, $prescription, $pd->drug);
                }
            }
            
            // ✅ إعادة تعيين الفلاغ بعد الإرسال مباشرة
            Log::info('🔧 Resetting skipNotification = FALSE after sending notifications');
            PrescriptionDrugObserver::$skipNotification = false;
            Log::info('🔧 skipNotification after resetting', ['value' => PrescriptionDrugObserver::$skipNotification]);
            
            Log::info('✅ ========== END store() - SUCCESS ==========');
            return $this->sendSuccess([], 'تم إضافة الأدوية بنجاح.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            PrescriptionDrugObserver::$skipNotification = false;
            Log::error('❌ Validation error in store()');
            return $this->sendError('خطأ في التحقق من البيانات.', $e->errors(), 422);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            PrescriptionDrugObserver::$skipNotification = false;
            
            Log::error('❌ Error adding medications: ' . $e->getMessage());
            $errorMessage = config('app.debug') 
                ? $e->getMessage() . ' في ' . $e->getFile() . ':' . $e->getLine()
                : 'حدث خطأ أثناء حفظ البيانات.';
            return $this->sendError('حدث خطأ أثناء حفظ البيانات.', [
                'error' => $errorMessage,
                'details' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ] : null
            ], 500);
        }
    }

    /**
     * Edit Drug Quantity
     */
    public function update(Request $request, $patientId, $pivotId, PatientNotificationService $notificationService)
    {
        Log::info('🔄 ========== START update() ==========', [
            'patientId' => $patientId,
            'pivotId' => $pivotId
        ]);
        
        $request->validate([
            'dosage' => 'required|integer|min:1',
            'daily_quantity' => 'nullable|integer|min:1',
        ]);
        $hospitalId = $request->user()->hospital_id;

        $item = PrescriptionDrug::where('id', $pivotId)->first();
        if (!$item) {
            Log::error('❌ Drug not found in update()', ['pivotId' => $pivotId]);
            return $this->sendError('الدواء غير موجود في السجل.', [], 404);
        }

        $prescription = Prescription::with('patient')->find($item->prescription_id);
        if (!$prescription) {
            Log::error('❌ Prescription not found in update()', ['prescription_id' => $item->prescription_id]);
            return $this->sendError('الوصفة غير موجودة.', [], 404);
        }

        $patient = $prescription->patient;
        if (!$patient || $patient->hospital_id !== $hospitalId) {
            Log::error('❌ Patient access error in update()', [
                'patient_hospital_id' => $patient ? $patient->hospital_id : null,
                'doctor_hospital_id' => $hospitalId
            ]);
            return $this->sendError('المريض غير موجود أو غير مرتبط بنفس المستشفى.', [], 404);
        }

        DB::beginTransaction();
        try {
            // ✅ أولاً: منع Observer من إرسال إشعار مكرر
            Log::info('🔧 Setting skipNotification = TRUE before update');
            PrescriptionDrugObserver::$skipNotification = true;
            Log::info('🔧 skipNotification after setting', ['value' => PrescriptionDrugObserver::$skipNotification]);
            
            // ✅ ثانياً: تحديث البيانات
            Log::info('📝 Updating prescription drug', [
                'id' => $item->id,
                'monthly_quantity' => $request->dosage,
                'daily_quantity' => $request->has('daily_quantity') ? $request->daily_quantity : 'not changed',
                'skipNotification' => PrescriptionDrugObserver::$skipNotification
            ]);
            
            $item->monthly_quantity = $request->dosage;
            if ($request->has('daily_quantity')) {
                $item->daily_quantity = (int)$request->daily_quantity;
            }
            
            $item->save();
            Log::info('✅ Prescription drug updated successfully');
            
            // تحديث updated_at للمريض ليظهر في بداية القائمة
            $patient->touch();
            
            // Trigger Push Notification
            $item->loadMissing('drug');
            $notificationService->notifyDrugUpdated($patient, $prescription, $item->drug);

            DB::commit();
            
            Log::info('✅ ========== END update() - SUCCESS ==========');
            return $this->sendSuccess($item, 'تم تحديث جرعة الدواء بنجاح.');
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Error updating prescription drug: ' . $e->getMessage(), [
                'pivot_id' => $pivotId,
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('حدث خطأ أثناء تحديث جرعة الدواء.', [
                'error' => config('app.debug') ? $e->getMessage() : 'خطأ في التحديث'
            ], 500);
        } finally {
            // ✅ رابعاً: إعادة تعيين الفلاغ في كل الحالات
            Log::info('🔧 Resetting skipNotification = FALSE in finally block');
            PrescriptionDrugObserver::$skipNotification = false;
            Log::info('🔧 skipNotification after resetting', ['value' => PrescriptionDrugObserver::$skipNotification]);
        }
    }

    /**
     * Remove Drug (Auto-delete Prescription if empty)
     */
    public function destroy(Request $request, $patientId, $pivotId, PatientNotificationService $notificationService)
    {
        Log::info('🔄 ========== START destroy() ==========', [
            'patientId' => $patientId,
            'pivotId' => $pivotId
        ]);
        
        $hospitalId = $request->user()->hospital_id;

        $item = PrescriptionDrug::where('id', $pivotId)->first();
        if (!$item) {
            Log::error('❌ Drug not found in destroy()', ['pivotId' => $pivotId]);
            return $this->sendError('الدواء غير موجود في السجل.', [], 404);
        }

        $prescription = Prescription::with('patient')->find($item->prescription_id);
        if (!$prescription) {
            Log::error('❌ Prescription not found in destroy()', ['prescription_id' => $item->prescription_id]);
            return $this->sendError('الوصفة غير موجودة.', [], 404);
        }

        $patient = $prescription->patient;
        if (!$patient || $patient->hospital_id !== $hospitalId) {
            Log::error('❌ Patient access error in destroy()', [
                'patient_hospital_id' => $patient ? $patient->hospital_id : null,
                'doctor_hospital_id' => $hospitalId
            ]);
            return $this->sendError('المريض غير موجود أو غير مرتبط بنفس المستشفى.', [], 404);
        }

        DB::beginTransaction();
        try {
            $item->loadMissing(['prescription.patient', 'drug']);
            
            // ✅ منع Observer من إرسال إشعار مكرر - قبل الحذف مباشرة
            Log::info('🔧 Setting skipNotification = TRUE before deletion');
            PrescriptionDrugObserver::$skipNotification = true;
            Log::info('🔧 skipNotification after setting', ['value' => PrescriptionDrugObserver::$skipNotification]);
            
            
            Log::info('🗑️ Deleting prescription drug', [
                'id' => $item->id,
                'skipNotification' => PrescriptionDrugObserver::$skipNotification
            ]);
            
            // 1. Delete the Drug
            $item->delete();
            Log::info('✅ Drug deleted successfully');

            // تحديث updated_at للمريض ليظهر في بداية القائمة
            $patient->touch();

            // Trigger Push Notification
            $notificationService->notifyDrugDeleted($patient, $prescription, $item->drug);

            // 2. Check if Prescription is empty -> Delete it (End of lifecycle)
            if ($prescription->drugs()->count() == 0) {
                $prescription->delete();
                Log::info('✅ Prescription deleted (empty)');
            }
            
            DB::commit();
            
            Log::info('✅ ========== END destroy() - SUCCESS ==========');
            return $this->sendSuccess([], 'تم حذف الدواء بنجاح.');
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Error deleting prescription drug: ' . e->getMessage(), [
                'pivot_id' => $pivotId,
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('حدث خطأ أثناء حذف الدواء.', [
                'error' => config('app.debug') ? $e->getMessage() : 'خطأ في الحذف'
            ], 500);
        } finally {
            // ✅ إعادة تعيين الفلاغ بعد العملية - في كل الحالات
            Log::info('🔧 Resetting skipNotification = FALSE in finally block');
            PrescriptionDrugObserver::$skipNotification = false;
            Log::info('🔧 skipNotification after resetting', ['value' => PrescriptionDrugObserver::$skipNotification]);
        }
    }
}