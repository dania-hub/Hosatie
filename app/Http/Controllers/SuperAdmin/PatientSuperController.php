<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Models\User;
use App\Models\Prescription;
use App\Models\PrescriptionDrug;
use App\Models\Dispensing;
use App\Models\Drug;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseApiController;
class PatientSuperController extends BaseApiController
{
    /**
     * عرض جميع المرضى مع بيانات المستشفى
     */
    public function index(Request $request)
    {
        // جلب المرضى فقط
        $patients = User::with('hospital')        // يستخدم علاقة hospital الموجودة عندك
            ->where('type', 'patient')           // فقط المستخدمين من نوع مريض
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($user) {
                return [
                    'fileNumber'   => $user->id,                          // رقم الملف
                    'fullName'     => $user->full_name,                  // الاسم الرباعي
                    'nationalId'   => $user->national_id,                // الرقم الوطني
                    'birthDate'    => optional($user->birth_date)->format('Y-m-d'),
                    'phone'        => $user->phone,
            
                    'hospitalName' => optional($user->hospital)->name,   // اسم المستشفى من العلاقة
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $patients,
            'message' => 'تم جلب جميع المرضى بنجاح',
        ], 200);
    }
    /**
     * عرض تفاصيل مريض محدد
     */
    public function show($id)
    {
        $patient = User::where('type', 'patient')->findOrFail($id);

        // جلب جميع الوصفات للمريض (من أي مستشفى)
        $prescriptions = Prescription::with('doctor')
            ->where('patient_id', $patient->id)
            ->latest('start_date')
            ->get();

        $medications = [];

        if ($prescriptions->isNotEmpty()) {
            $prescriptionIds = $prescriptions->pluck('id');
            $medications = PrescriptionDrug::with('drug')
                ->whereIn('prescription_id', $prescriptionIds)
                ->get()
                ->map(function ($pd) use ($patient, $prescriptions) {
                    $prescription = $prescriptions->firstWhere('id', $pd->prescription_id);
                    $drug = $pd->drug;
                    $currentMonthlyQty = (int)($pd->monthly_quantity ?? 0);
                    
                    if (!$drug) {
                        $drug = \App\Models\Drug::find($pd->drug_id);
                    }
                    
                    $unit = $drug?->unit ?? 'حبة';
                    
                    $assignmentDate = $pd->created_at
                        ? Carbon::parse($pd->created_at)->format('Y/m/d')
                        : ($prescription && $prescription->start_date ? Carbon::parse($prescription->start_date)->format('Y/m/d') : null);

                    // تحسين منطق "بواسطة" (Assigned By) لاستخدام سجل التدقيق (AuditLog) للحصول على دقة أعلى
                    $assignedBy = 'غير محدد';
                    
                    // محاولة العثور على من قام بإضافة هذا الدواء تحديداً
                    $latestLog = \App\Models\AuditLog::whereIn('table_name', ['prescription_drug', 'prescription_drugs']) // البحث في كلا الاحتمالين
                        ->where('record_id', $pd->id)
                        ->whereIn('action', ['إضافة دواء', 'تعديل دواء', 'create', 'update'])
                        ->with('user')
                        ->latest()
                        ->first();
                        
                    if ($latestLog && $latestLog->user) {
                        $assignedBy = $latestLog->user->full_name ?? $latestLog->user->name ?? 'غير محدد';
                    } elseif ($prescription && $prescription->doctor) {
                        // البديل: الطبيب صاحب الوصفة
                        $assignedBy = $prescription->doctor->full_name ?? 'غير محدد';
                    }
                    
                    // حساب الكمية المصروفة في الشهر الحالي
                    $startOfMonth = Carbon::now()->startOfMonth();
                    $endOfMonth = Carbon::now()->endOfMonth();
                    
                    $totalDispensedThisMonth = (int)Dispensing::where('patient_id', $patient->id)
                        ->where('drug_id', $pd->drug_id)
                        ->where('reverted', false)
                        ->whereBetween('dispense_month', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
                        ->sum('quantity_dispensed');
                    
                    // حساب الكمية المتبقية
                    // الكمية الشهرية "currentMonthlyQty" هي القيمة الحالية في الجدول
                    // إذا أردنا عرض المتبقي من "الرصيد الشهري الكلي"، يجب أن نعرف هل monthly_quantity هو الرصيد المتبقي أم الكلي؟
                    // عادة في هذا النظام monthly_quantity هو الحد المسموح به شهرياً
                    // والمتبقي = (الكمية الشهرية المقررة) - (ما تم صرفه بالفعل)
                    // لكن في بعض التنفيذات، monthly_quantity يتناقص مع كل صرف. سنفترض هنا أنه "الكمية المخصصة شهرياً" ولا يتغير.
                    // إذا كان يتغير، فالعملية الحسابية تختلف.
                    // بناءً على PatientHospitalAdminController (Step 39)، يبدو أنهم يحسبون "monthlyQty" (الكلي) = "current" + "dispensed".
                    // ثم "remaining" = "monthlyQty" - "dispensed".
                    // سنستخدم نفس المنطق لتوحيد العرض.

                    $monthlyQtyTotal = $currentMonthlyQty + $totalDispensedThisMonth;
                    $remainingQuantity = max(0, $monthlyQtyTotal - $totalDispensedThisMonth);

                    // إذا كانت monthly_quantity في قاعدة البيانات هي ثابتة ولا تتناقص، فالمنطق يكون:
                    // $remainingQuantity = max(0, $currentMonthlyQty - $totalDispensedThisMonth);
                    // ولكن الكود السابق يوحي بأن الحقل يتم تحديثه. لنلتزم بمنطق PatientHospitalAdminController للأمان.
                    
                    // تصحيح: إذا كان النظام لا يخصم من الـ DB، فإن $currentMonthlyQty هي الكمية الكاملة.
                    // في هذه الحالة $remainingQuantity = $currentMonthlyQty - $totalDispensedThisMonth.
                    // لكن بما أن المستخدم طلب "المصروف والمتبقي"، سنفترض أن القيمة القادمة من DB هي المعيار.
                    // سنعيد القيمتين (المصروف والمتبقي) للفرونت إند ليتم عرضهما.
                    // ولتجنب الارتباك، سنرسل dispensed و remaining محسوبين.
                    
                    // ملاحظة: بناءً على مراجعة PatientHospitalAdminController، يبدو أنهم يعتمدون على أن monthly_quantity هو الرصيد *المتبقي* في الـ row.
                    // لذلك:
                    $originalMonthlyQty = $currentMonthlyQty + $totalDispensedThisMonth; 
                    $remainingQuantity  = $currentMonthlyQty; // القيمة الحالية في الـ DB هي المتبقي

                    return [
                        'id'             => $pd->id,
                        'drugId'         => $pd->drug_id,
                        'drugName'       => $drug?->name ?? 'دواء غير معروف',
                        'dosage'         => $pd->daily_quantity ? $pd->daily_quantity . ' ' . $unit . ' يومياً' : ($drug?->strength ?? 'غير محدد'),
                        'monthlyQuantity'=> ($originalMonthlyQty > 0 ? $originalMonthlyQty . ' ' . $unit : 'غير محدد'), // نعرض الكمية الأصلية هنا
                        'monthlyQuantityNum' => $originalMonthlyQty,
                        'unit'           => $unit,
                        'dispensedQty'   => $totalDispensedThisMonth, // المصروف هذا الشهر
                        'remainingQty'   => $remainingQuantity,       // المتبقي (هو القيمة الحالية في الـ DB)
                        'assignmentDate' => $assignmentDate,
                        'assignedBy'     => $assignedBy,
                        'note'           => $pd->note,
                    ];
                })->values();
        }

        return response()->json([
            'id'            => $patient->id,
            'fileNumber'    => $patient->id,
            'name'          => $patient->full_name,
            'nationalId'    => $patient->national_id,
            'birth'         => optional($patient->birth_date)->toDateString(),
            'phone'         => $patient->phone,
            'hospitalName'  => optional($patient->hospital)->name,
            'hospitalId'    => $patient->hospital_id,
            'medications'   => $medications,
        ]);
    }

    /**
     * سجل الصرف للمريض
     */
    public function dispensationHistory(Request $request, $id)
    {
        $patient = User::where('type', 'patient')->findOrFail($id);

        $dispensations = Dispensing::with(['drug', 'pharmacist', 'pharmacy'])
            ->where('patient_id', $patient->id)
            ->where('reverted', false)
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedHistory = $dispensations->map(function ($d) {
            $dispenseDate = null;
            if ($d->created_at) {
                $dispenseDate = Carbon::parse($d->created_at)->format('Y/m/d');
            } elseif ($d->dispense_month) {
                $dispenseDate = Carbon::parse($d->dispense_month)->format('Y/m/d');
            }
            
            $unit = 'حبة';
            if ($d->drug && !empty($d->drug->unit)) {
                $unit = $d->drug->unit;
            }
            
            return [
                'id' => $d->id,
                'date' => $dispenseDate ?? 'غير محدد',
                'assignedBy' => $d->pharmacist ? ($d->pharmacist->full_name ?? $d->pharmacist->name) : 'غير معروف',
                'pharmacyName' => $d->pharmacy ? ($d->pharmacy->name ?? 'غير محدد') : null,
                'drugName' => $d->drug ? $d->drug->name : 'غير معروف',
                'quantity' => ($d->quantity_dispensed ?? 0) . ' ' . $unit,
                'notes' => $d->note ?? ''
            ];
        });

        // Returning Array as expected by Vue
        return response()->json($formattedHistory);
    }
}
