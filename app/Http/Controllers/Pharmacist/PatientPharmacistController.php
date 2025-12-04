<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Dispensing;
use App\Models\Inventory;
use App\Models\Drug;
use App\Models\Prescription;
use App\Models\Pharmacy;
use Illuminate\Support\Facades\DB;

class PatientPharmacistController extends BaseApiController
{
    /**
     * GET /api/pharmacist/patients
     * قائمة المرضى (للقراءة فقط - لا تغيير في المنطق).
     */
    public function index(Request $request)
    {
        $patients = User::where('type', 'patient')
            ->get()
            ->map(function ($p) {
                return [
                    'fileNumber' => $p->id,
                    'name' => $p->full_name ?? $p->name,
                    'nationalId' => $p->national_id,
                    'birthDate' => $p->birth_date,
                    'phone' => $p->phone,
                    'lastUpdated' => $p->updated_at,
                    'medications' => [],
                    'dispensationHistory' => []
                ];
            });

        return $this->sendSuccess($patients, 'تم تحديث قائمة المرضى بنجاح.');
    }

    /**
     * POST /api/pharmacist/dispense
     * صرف الأدوية (يخصم من مخزون صيدلية المستشفى تحديداً).
     */
      /**
     * POST /api/pharmacist/dispense
     * صرف الأدوية (يخصم من مخزون الصيدلية + يتحقق من وجود الدواء في الوصفة).
     */
    public function dispense(Request $request)
    {
        $request->validate([
            'patientFileNumber' => 'required|exists:users,id',
            'dispensedItems' => 'required|array',
            'dispensedItems.*.drugName' => 'required|string',
            'dispensedItems.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $patient = User::findOrFail($request->patientFileNumber);
            $pharmacist = $request->user();

            // 1. تحديد الصيدلية (مصدر الصرف)
            $pharmacyId = null;

            if ($pharmacist->pharmacy_id) {
                $pharmacyId = $pharmacist->pharmacy_id;
            } elseif ($patient->hospital_id) {
                $pharmacy = Pharmacy::where('hospital_id', $patient->hospital_id)->first();
                $pharmacyId = $pharmacy ? $pharmacy->id : null;
            }
            
            // حل مؤقت للتجربة
            if (!$pharmacyId) $pharmacyId = 1; 

            if (!$pharmacyId) {
                throw new \Exception("لا توجد صيدلية محددة لخصم المخزون منها.");
            }

            // 2. العثور على الوصفة النشطة
            $prescription = Prescription::where('patient_id', $patient->id)
                ->where('status', 'active')
                ->latest()
                ->first();

            if (!$prescription) {
                throw new \Exception("لا توجد وصفة طبية نشطة لهذا المريض. يرجى مراجعة الطبيب.");
            }

            // 3. صرف الأدوية
            foreach ($request->dispensedItems as $item) {
                
                // أ. العثور على الدواء
                $drug = Drug::where('name', $item['drugName'])->first();
                if (!$drug) throw new \Exception("الدواء غير موجود في النظام: " . $item['drugName']);

                // =========================================================
                // ب. التحقق الأمني: هل الدواء موجود في الوصفة فعلاً؟
                // =========================================================
                $isPrescribed = \App\Models\PrescriptionDrug::where('prescription_id', $prescription->id)
                    ->where('drug_id', $drug->id)
                    ->exists();

                if (!$isPrescribed) {
                    throw new \Exception("عفواً، الدواء (" . $item['drugName'] . ") غير موجود في وصفة المريض الحالية.");
                }

                // ج. فحص المخزون في الصيدلية المحددة
                $inventory = Inventory::where('drug_id', $drug->id)
                    ->where('pharmacy_id', $pharmacyId)
                    ->first();

                if (!$inventory || $inventory->current_quantity < $item['quantity']) {
                    throw new \Exception("الكمية غير كافية للدواء: " . $item['drugName'] . " في هذه الصيدلية.");
                }
                
                // د. خصم الكمية والحفظ
                $inventory->current_quantity -= $item['quantity'];
                $inventory->save();

                Dispensing::create([
                    'prescription_id' => $prescription->id,
                    'patient_id' => $patient->id,
                    'drug_id' => $drug->id,
                    'pharmacist_id' => $pharmacist->id,
                    'pharmacy_id' => $pharmacyId,
                    'dispense_month' => now()->format('Y-m-d'),
                    'quantity_dispensed' => $item['quantity'],
                ]);
            }

            DB::commit();
            return $this->sendSuccess([], 'تم صرف الأدوية الموصوفة بنجاح.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('فشل في العملية: ' . $e->getMessage());
        }
    }


    /**
     * GET /api/pharmacist/patients/{fileNumber}/dispensations
     * سجل صرف المريض.
     */
    public function history($fileNumber)
    {
        $patient = User::where('id', $fileNumber)->where('type', 'patient')->first();
        if (!$patient) return $this->sendError('المريض غير موجود.');

        $dispensations = Dispensing::with(['drug', 'pharmacist'])
            ->where('patient_id', $patient->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedHistory = $dispensations->map(function ($d) {
            return [
                'id' => $d->id,
                'date' => $d->created_at,
                'pharmacistName' => $d->pharmacist ? ($d->pharmacist->full_name ?? $d->pharmacist->name) : 'غير معروف',
                'totalItems' => 1,
                'items' => [
                    [
                        'drugName' => $d->drug ? $d->drug->name : 'غير معروف', 
                        'quantity' => $d->quantity_dispensed,
                        'unit' => $d->drug->unit ?? 'علبة'
                    ]
                ]
            ];
        });

        return $this->sendSuccess([
            'patientInfo' => [
                'fileNumber' => $patient->id,
                'name' => $patient->full_name ?? $patient->name,
                'nationalId' => $patient->national_id
            ],
            'dispensations' => $formattedHistory
        ], 'تم جلب سجل المريض بنجاح.');
    }
}
