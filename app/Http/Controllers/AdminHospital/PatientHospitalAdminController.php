<?php

namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use App\Models\User;
use App\Models\Prescription;
use App\Models\PrescriptionDrug;
use App\Models\Dispensing;
use App\Models\Drug;
use Illuminate\Http\Request;

class PatientHospitalAdminController extends BaseApiController
{
    // 1) قائمة المرضى
    public function index(Request $request)
    {
        $hospitalId = $request->user()->hospital_id;

        $patients = User::where('type', 'patient')
            ->where('hospital_id', $hospitalId)
            ->latest()
            ->get()
            ->map(function ($u) {
                return [
                    'id'         => $u->id,
                    'fileNumber' => $u->id,
                    'name'       => $u->full_name,
                    'nationalId' => $u->national_id,
                    'birth'      => optional($u->birth_date)->toDateString(),
                    'phone'      => $u->phone,
                    'lastUpdated'=> optional($u->updated_at)->toIso8601String(),
                ];
            });

        return response()->json($patients);
    }

    // 2) تفاصيل مريض + أدوية من prescription_drug
    public function show(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;

        $u = User::where('type', 'patient')
            ->where('hospital_id', $hospitalId)
            ->findOrFail($id);

        // آخر وصفة للمريض في هذا المستشفى (يمكنك إضافة شرط status لو عندك قيم محددة)
        $prescription = Prescription::where('patient_id', $u->id)
            ->where('hospital_id', $hospitalId)
            ->latest('start_date')
            ->first();

        $medications = [];

        if ($prescription) {
            $medications = PrescriptionDrug::with('drug')
                ->where('prescription_id', $prescription->id)
                ->get()
                ->map(function ($pd) {
                    return [
                        'id'             => $pd->id,
                        'drugId'         => $pd->drug_id,
                        'drugName'       => $pd->drug?->name ?? 'دواء غير معروف',
                        'dosage'         => $pd->drug?->strength ?? '',   // استخدم strength كجرعة تقريبية
                        'monthlyQuantity'=> $pd->monthly_quantity,
                        'note'           => $pd->note,
                    ];
                })->values();
        }

        return response()->json([
            'id'            => $u->id,
            'fileNumber'    => $u->id,
            'name'          => $u->full_name,
            'nationalId'    => $u->national_id,
            'birth'         => optional($u->birth_date)->toDateString(),
            'phone'         => $u->phone,
            'prescriptionId'=> $prescription?->id,
            'medications'   => $medications,
        ]);
    }

    // 3) تعديل أدوية الوصفة (على prescription_drug)
    public function updateMedications(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;

        $patient = User::where('type', 'patient')
            ->where('hospital_id', $hospitalId)
            ->findOrFail($id);

        $data = $request->validate([
            'medications'                    => 'array',
            'medications.*.drugId'           => 'nullable|integer|exists:drug,id',
            'medications.*.drugName'         => 'required_without:medications.*.drugId|string|max:255',
            'medications.*.dosage'           => 'nullable|string|max:50',
            'medications.*.monthlyQuantity'  => 'nullable|string|max:50',
            'medications.*.note'             => 'nullable|string|max:500',
        ]);

        // نبحث/ننشيء وصفة للمريض
        $prescription = Prescription::firstOrCreate(
            [
                'patient_id'  => $patient->id,
                'hospital_id' => $hospitalId,
                'status'      => 'active', // عدّل حسب قيم status عندك
            ],
            [
                'doctor_id'  => $request->user()->id ?? null,
                'start_date' => now()->toDateString(),
            ]
        );

        // نحذف البنود القديمة
        PrescriptionDrug::where('prescription_id', $prescription->id)->delete();

        $created = [];

        foreach ($data['medications'] ?? [] as $med) {
            // نحدد الدواء
            if (!empty($med['drugId'])) {
                $drug = Drug::find($med['drugId']);
            } else {
                $drug = Drug::where('name', $med['drugName'])->first();
                if (!$drug) {
                    $drug = Drug::create([
                        'name'        => $med['drugName'],
                        'generic_name'=> $med['drugName'],
                        'strength'    => $med['dosage'] ?? '',
                        'form'        => '',
                        'category'    => '',
                        'unit'        => '',
                        'max_monthly_dose' => null,
                        'status'      => 'active',
                        'manufacturer'=> '',
                        'country'     => '',
                        'utilization_type' => '',
                        'warnings'    => '',
                        'expiry_date' => null,
                    ]);
                }
            }

            $pd = PrescriptionDrug::create([
                'prescription_id'  => $prescription->id,
                'drug_id'          => $drug->id,
                'monthly_quantity' => $med['monthlyQuantity'] ?? null,
                'note'             => $med['note'] ?? null,
            ]);

            $created[] = [
                'id'             => $pd->id,
                'drugId'         => $drug->id,
                'drugName'       => $drug->name,
                'dosage'         => $drug->strength,
                'monthlyQuantity'=> $pd->monthly_quantity,
                'note'           => $pd->note,
            ];
        }

        return response()->json([
            'medications' => $created,
        ]);
    }

    // 4) سجل الصرف من dispensing
    public function dispensationHistory(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;

        $patient = User::where('type', 'patient')
            ->where('hospital_id', $hospitalId)
            ->findOrFail($id);

        $history = Dispensing::with('drug', 'pharmacist', 'pharmacy')
            ->where('patient_id', $patient->id)
            ->latest('dispense_month')
            ->get()
            ->map(function ($d) {
                return [
                    'id'          => $d->id,
                    'drugName'    => $d->drug?->name ?? 'دواء غير معروف',
                    'quantity'    => $d->quantity_dispensed,
                    'dispensedAt' => optional($d->dispense_month)->toDateString(),
                    'pharmacy'    => $d->pharmacy?->name ?? null,
                    'pharmacist'  => $d->pharmacist?->full_name ?? null,
                    'reverted'    => (bool) $d->reverted,
                    'revertedAt'  => optional($d->reverted_at)->toIso8601String(),
                ];
            });

        return response()->json($history);
    }
}
