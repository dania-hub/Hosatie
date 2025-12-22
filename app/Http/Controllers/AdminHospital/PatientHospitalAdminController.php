<?php

namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use App\Models\User;
use App\Models\Prescription;
use App\Models\PrescriptionDrug;
use App\Models\Dispensing;
use App\Models\Drug;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PatientHospitalAdminController extends BaseApiController
{
    // 1) قائمة المرضى
    public function index(Request $request)
    {
        $user = $request->user();
        $hospitalId = $user->hospital_id;

        // التأكد من أن المستخدم لديه hospital_id
        if (!$hospitalId) {
            return response()->json([
                'error' => 'المستخدم غير مرتبط بمستشفى'
            ], 400);
        }

        // عرض المرضى الذين لديهم نفس hospital_id فقط
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
        $user = $request->user();
        $hospitalId = $user->hospital_id;

        if (!$hospitalId) {
            return response()->json([
                'error' => 'المستخدم غير مرتبط بمستشفى'
            ], 400);
        }

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
                ->map(function ($pd) use ($u, $prescription) {
                    $drug = $pd->drug;
                    $monthlyQty = (int)($pd->monthly_quantity ?? 0);
                    
                    // تحديد وحدة القياس من بيانات الدواء
                    $unit = $drug?->unit ?? 'حبة';
                    
                    // تاريخ الإسناد (من pivot created_at أو start_date من الوصفة)
                    $assignmentDate = $pd->created_at
                        ? Carbon::parse($pd->created_at)->format('Y/m/d')
                        : ($prescription->start_date ? Carbon::parse($prescription->start_date)->format('Y/m/d') : null);
                    
                    // آخر عملية صرف لهذا الدواء
                    $lastDispense = Dispensing::where('patient_id', $u->id)
                        ->where('drug_id', $pd->drug_id)
                        ->latest('created_at')
                        ->first();
                    
                    $lastDispenseDate = $lastDispense && $lastDispense->created_at
                        ? Carbon::parse($lastDispense->created_at)->format('Y/m/d')
                        : null;
                    
                    // نعرض تاريخ "آخر إستلام" إن وجد، وإلا تاريخ الإسناد
                    $displayDate = $lastDispenseDate ?? $assignmentDate;
                    
                    // حساب الكمية المصروفة في الشهر الحالي
                    $startOfMonth = Carbon::now()->startOfMonth();
                    $endOfMonth = Carbon::now()->endOfMonth();
                    
                    $totalDispensedThisMonth = (int)Dispensing::where('patient_id', $u->id)
                        ->where('drug_id', $pd->drug_id)
                        ->whereBetween('dispense_month', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
                        ->sum('quantity_dispensed');
                    
                    // حساب الكمية المتبقية
                    $remainingQuantity = max(0, $monthlyQty - $totalDispensedThisMonth);
                    
                    // تنسيق الكمية الشهرية كنص مع الوحدة
                    $monthlyQuantityText = $monthlyQty > 0 ? $monthlyQty . ' ' . $unit : 'غير محدد';
                    
                    // اسم من قام بالإسناد (من audit_log أو اسم الطبيب كبديل)
                    $latestLog = \App\Models\AuditLog::where('table_name', 'prescription_drug')
                        ->where('record_id', $pd->id)
                        ->whereIn('action', ['إضافة دواء', 'تعديل دواء'])
                        ->with('user')
                        ->latest()
                        ->first();
                    
                    $assignedBy = $latestLog && $latestLog->user 
                        ? $latestLog->user->full_name 
                        : ($prescription->doctor 
                            ? ($prescription->doctor->full_name ?? $prescription->doctor->name ?? 'غير محدد')
                            : 'غير محدد');
                    
                    // حساب الجرعة اليومية من الكمية الشهرية
                    $dailyQty = $monthlyQty > 0 ? round($monthlyQty / 30, 1) : 0;
                    
                    // تحويل الجرعة اليومية إلى نص (مثل: "5 قرص" أو "قرصين")
                    if ($dailyQty > 0) {
                        $dosageInt = (int)$dailyQty;
                        if ($dosageInt == 1) {
                            $dosageText = 'قرص واحد';
                        } elseif ($dosageInt == 2) {
                            $dosageText = 'قرصين';
                        } else {
                            $dosageText = $dosageInt . ' قرص';
                        }
                    } else {
                        $dosageText = $drug?->strength ?? 'غير محدد';
                    }
                    
                    return [
                        'id'             => $pd->id,
                        'drugId'         => $pd->drug_id,
                        'drugName'       => $drug?->name ?? 'دواء غير معروف',
                        'dosage'         => $dosageText,
                        'monthlyQuantity'=> $monthlyQuantityText,
                        'monthlyQuantityNum' => $monthlyQty,
                        'unit'           => $unit,
                        'assignmentDate' => $displayDate,
                        'assignedBy'     => $assignedBy,
                        'totalDispensedThisMonth' => $totalDispensedThisMonth,
                        'remainingQuantity' => $remainingQuantity,
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
        $user = $request->user();
        $hospitalId = $user->hospital_id;

        if (!$hospitalId) {
            return response()->json([
                'error' => 'المستخدم غير مرتبط بمستشفى'
            ], 400);
        }

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
        $user = $request->user();
        $hospitalId = $user->hospital_id;

        if (!$hospitalId) {
            return response()->json([
                'error' => 'المستخدم غير مرتبط بمستشفى'
            ], 400);
        }

        $patient = User::where('type', 'patient')
            ->where('hospital_id', $hospitalId)
            ->findOrFail($id);

        $history = Dispensing::with('drug', 'pharmacist', 'pharmacy')
            ->where('patient_id', $patient->id)
            ->where('reverted', false) // استبعاد السجلات الملغاة
            ->latest('dispense_month')
            ->get()
            ->map(function ($d) {
                // تحديد تاريخ الصرف (أولوية: created_at > dispense_month)
                $dispenseDate = null;
                if ($d->created_at) {
                    $dispenseDate = Carbon::parse($d->created_at)->format('Y/m/d');
                } elseif ($d->dispense_month) {
                    $dispenseDate = Carbon::parse($d->dispense_month)->format('Y/m/d');
                }
                
                return [
                    'id'          => $d->id,
                    'drugName'    => $d->drug?->name ?? 'دواء غير معروف',
                    'quantity'    => $d->quantity_dispensed ?? 0,
                    'quantity_dispensed' => $d->quantity_dispensed ?? 0,
                    'dispensedAt' => $dispenseDate,
                    'pharmacy'    => $d->pharmacy?->name ?? null,
                    'pharmacist'  => $d->pharmacist?->full_name ?? 'غير معروف',
                    'reverted'    => (bool) $d->reverted,
                    'revertedAt'  => $d->reverted_at ? Carbon::parse($d->reverted_at)->format('Y/m/d') : null,
                ];
            });

        return response()->json($history);
    }
}
