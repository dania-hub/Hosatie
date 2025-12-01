<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Prescription;
use App\Models\PrescriptionDrug;
use Illuminate\Support\Facades\DB;

class PrescriptionDoctorController extends BaseApiController
{
    /**
     * Add Drugs (Auto-create Prescription if missing)
     */
    public function store(Request $request, $patientId)
    {
        $request->validate([
            'medications' => 'required|array',
            'medications.*.drug_id' => 'required|exists:drug,id',
            'medications.*.quantity' => 'required|integer|min:1',
            'medications.*.note' => 'nullable|string',
        ]);

        try {
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
            foreach ($request->medications as $med) {
                $exists = PrescriptionDrug::where('prescription_id', $prescription->id)
                            ->where('drug_id', $med['drug_id'])
                            ->exists();
                
                if (!$exists) {
                    PrescriptionDrug::create([
                        'prescription_id' => $prescription->id,
                        'drug_id'         => $med['drug_id'],
                        'monthly_quantity'=> $med['quantity'],
                        'note'            => $med['note'] ?? null,
                    ]);
                }
            }

            DB::commit();
            return $this->sendSuccess([], 'تم إضافة الأدوية بنجاح.');

        } catch (\Exception $e) {
            DB::rollBack();
            // Handle general exception
            return $this->sendError('حدث خطأ أثناء حفظ البيانات.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Edit Drug Quantity
     */
    public function update(Request $request, $patientId, $pivotId)
    {
        $request->validate(['dosage' => 'required|integer|min:1']);
        $hospitalId = $request->user()->hospital_id;

        $item = PrescriptionDrug::where('id', $pivotId)->first();
        if (!$item) return $this->sendError('الدواء غير موجود في السجل.', [], 404);

        // Security: Ensure same hospital
        $prescription = Prescription::find($item->prescription_id);
        if ($prescription->hospital_id !== $hospitalId) {
             return $this->sendError('غير مصرح لك بتعديل هذا السجل (مستشفى مختلف).', [], 403);
        }

        $item->monthly_quantity = $request->dosage;
        $item->save();

        return $this->sendSuccess($item, 'تم تحديث جرعة الدواء بنجاح.');
    }

    /**
     * Remove Drug (Auto-delete Prescription if empty)
     */
    public function destroy(Request $request, $patientId, $pivotId)
    {
        $hospitalId = $request->user()->hospital_id;

        $item = PrescriptionDrug::where('id', $pivotId)->first();
        if (!$item) return $this->sendError('الدواء غير موجود في السجل.', [], 404);

        // Security: Ensure same hospital
        $prescription = Prescription::find($item->prescription_id);
        if ($prescription->hospital_id !== $hospitalId) {
             return $this->sendError('غير مصرح لك بحذف هذا السجل (مستشفى مختلف).', [], 403);
        }

        // 1. Delete the Drug
        $item->delete();

        // 2. Check if Prescription is empty -> Delete it (End of lifecycle)
        if ($prescription->drugs()->count() == 0) {
            $prescription->delete();
        }

        return $this->sendSuccess([], 'تم حذف الدواء بنجاح.');
    }
}
