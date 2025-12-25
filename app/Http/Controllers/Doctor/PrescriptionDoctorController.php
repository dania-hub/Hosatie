<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Prescription;
use App\Models\PrescriptionDrug;
use Illuminate\Support\Facades\DB;
use App\Services\PatientNotificationService;

class PrescriptionDoctorController extends BaseApiController
{
    /**
     * Add Drugs (Auto-create Prescription if missing)
     */
    public function store(Request $request, $patientId, PatientNotificationService $notifications)
    {
        try {
            $request->validate([
                'medications' => 'required|array',
                'medications.*.drug_id' => 'required|exists:drugs,id',
                'medications.*.quantity' => 'required|integer|min:1',
            ]);
            
            DB::beginTransaction();

            $hospitalId = $request->user()->hospital_id;
            $doctorId   = $request->user()->id;

            // 1. Find existing active prescription
            $prescription = Prescription::where('patient_id', $patientId)
                ->where('hospital_id', $hospitalId)
                ->where('status', 'active')
                ->first();

            // 2. If NO prescription, Create one (Start of lifecycle)
            if (!$prescription) {
                $prescription = Prescription::create([
                    'patient_id' => $patientId,
                    'hospital_id'=> $hospitalId,
                    'doctor_id'  => $doctorId,
                    'start_date' => now(),
                    'status'     => 'active',
                ]);
            }

            // 3. Add Drugs
            $createdDrugs = [];

            foreach ($request->medications as $med) {
                $exists = PrescriptionDrug::where('prescription_id', $prescription->id)
                            ->where('drug_id', $med['drug_id'])
                            ->exists();
                
                if (!$exists) {
                    $monthlyQuantity = $med['quantity'];
                    // استخدام daily_quantity المرسلة من Frontend، أو null إذا لم يتم إرسالها
                    $dailyQuantity = isset($med['daily_quantity']) && $med['daily_quantity'] !== null 
                        ? (int)$med['daily_quantity'] 
                        : null;
                    
                    $createdDrugs[] = PrescriptionDrug::create([
                        'prescription_id' => $prescription->id,
                        'drug_id'         => $med['drug_id'],
                        'monthly_quantity'=> $monthlyQuantity,
                        'daily_quantity'  => $dailyQuantity,
                    ]);
                }
            }

            DB::commit();
            if (!empty($createdDrugs)) {
                $prescription->loadMissing('patient');
                foreach ($createdDrugs as $drug) {
                    $drug->loadMissing('drug');
                    $notifications->notifyDrugAssigned(
                        $prescription->patient,
                        $prescription,
                        $drug->drug
                    );
                }
            }
            return $this->sendSuccess([], 'تم إضافة الأدوية بنجاح.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            // لا حاجة لـ rollback إذا فشل validation قبل بدء transaction
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return $this->sendError('خطأ في التحقق من البيانات.', $e->errors(), 422);
        } catch (\Exception $e) {
            // التراجع عن transaction فقط إذا كان موجوداً
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            // Handle general exception - إظهار الخطأ الفعلي للتطوير
            \Log::error('Error adding medications: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return $this->sendError('حدث خطأ أثناء حفظ البيانات.', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
        }
    }

    /**
     * Edit Drug Quantity
     */
    public function update(Request $request, $patientId, $pivotId, PatientNotificationService $notifications)
    {
        $request->validate([
            'dosage' => 'required|integer|min:1',
            'daily_quantity' => 'nullable|integer|min:1',
        ]);
        $hospitalId = $request->user()->hospital_id;

        $item = PrescriptionDrug::where('id', $pivotId)->first();
        if (!$item) return $this->sendError('الدواء غير موجود في السجل.', [], 404);

        // Security: Ensure same hospital
        $prescription = Prescription::find($item->prescription_id);
        if ($prescription->hospital_id !== $hospitalId) {
             return $this->sendError('غير مصرح لك بتعديل هذا السجل (مستشفى مختلف).', [], 403);
        }

        // تحديث الكمية الشهرية واليومية (استخدام القيمة المرسلة من Frontend أو الإبقاء على القيمة الحالية)
        $item->monthly_quantity = $request->dosage;
        if ($request->has('daily_quantity')) {
            $item->daily_quantity = (int)$request->daily_quantity;
        }
        // إذا لم يتم إرسال daily_quantity، نترك القيمة الحالية كما هي (لا نحسبها من monthly_quantity)
        $item->save();

        $item->loadMissing(['prescription.patient', 'drug']);
        $notifications->notifyDrugUpdated(
            $item->prescription->patient,
            $item->prescription,
            $item->drug
        );

        return $this->sendSuccess($item, 'تم تحديث جرعة الدواء بنجاح.');
    }

    /**
     * Remove Drug (Auto-delete Prescription if empty)
     */
    public function destroy(Request $request, $patientId, $pivotId, PatientNotificationService $notifications)
    {
        $hospitalId = $request->user()->hospital_id;

        $item = PrescriptionDrug::where('id', $pivotId)->first();
        if (!$item) return $this->sendError('الدواء غير موجود في السجل.', [], 404);

        // Security: Ensure same hospital
        $prescription = Prescription::find($item->prescription_id);
        if ($prescription->hospital_id !== $hospitalId) {
             return $this->sendError('غير مصرح لك بحذف هذا السجل (مستشفى مختلف).', [], 403);
        }

        $item->loadMissing(['prescription.patient', 'drug']);
        $notifications->notifyDrugDeleted(
            $item->prescription->patient,
            $item->prescription,
            $item->drug
        );

        // 1. Delete the Drug
        $item->delete();

        // 2. Check if Prescription is empty -> Delete it (End of lifecycle)
        if ($prescription->drugs()->count() == 0) {
            $prescription->delete();
        }

        return $this->sendSuccess([], 'تم حذف الدواء بنجاح.');
    }
}
