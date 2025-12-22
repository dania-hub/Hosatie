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
use Carbon\Carbon;

class PatientPharmacistController extends BaseApiController
{
    /**
     * GET /api/pharmacist/patients
     * قائمة المرضى مرتبة حسب آخر صرف (الأحدث أولاً).
     */
    public function index(Request $request)
    {
        // جلب جميع المرضى مع آخر تاريخ صرف لكل مريض
        $patients = User::where('type', 'patient')
            ->get()
            ->map(function ($p) {
                // جلب آخر عملية صرف لهذا المريض
                $lastDispensing = Dispensing::where('patient_id', $p->id)
                    ->latest('created_at')
                    ->first();
                
                // حساب تاريخ آخر تحديث (أولوية: آخر صرف > updated_at من users)
                $lastUpdateDate = null;
                if ($lastDispensing && $lastDispensing->created_at) {
                    $lastUpdateDate = Carbon::parse($lastDispensing->created_at);
                } elseif ($p->updated_at) {
                    $lastUpdateDate = Carbon::parse($p->updated_at);
                } else {
                    $lastUpdateDate = Carbon::parse($p->created_at);
                }
                
                return [
                    'fileNumber' => $p->id,
                    'name' => $p->full_name ?? $p->name,
                    'nationalId' => $p->national_id,
                    'birthDate' => $p->birth_date ? Carbon::parse($p->birth_date)->format('Y/m/d') : null,
                    'phone' => $p->phone,
                    'lastUpdated' => $lastUpdateDate ? $lastUpdateDate->format('Y/m/d H:i') : null,
                    'medications' => [],
                    'dispensationHistory' => [],
                    // حقل مؤقت للترتيب
                    '_lastUpdateTimestamp' => $lastUpdateDate->timestamp,
                ];
            })
            ->sortByDesc('_lastUpdateTimestamp')
            ->map(function ($p) {
                // إزالة الحقل المؤقت بعد الترتيب
                unset($p['_lastUpdateTimestamp']);
                return $p;
            })
            ->values()
            ->toArray();

        return $this->sendSuccess($patients, 'تم تحديث قائمة المرضى بنجاح.');
    }

    /**
     * GET /api/pharmacist/patients/{fileNumber}
     * تفاصيل مريض واحد + الأدوية الموصوفة له (لواجهة الصيدلي).
     */
    public function show(Request $request, $fileNumber)
    {
        $hospitalId = $request->user()->hospital_id;

        $patient = User::where('type', 'patient')
            ->where('id', $fileNumber)
            ->first();

        if (!$patient) {
            return $this->sendError('المريض غير موجود.', [], 404);
        }

        // الوصفة النشطة للمريض في هذا المستشفى
        $activePrescription = Prescription::with(['drugs', 'doctor'])
            ->where('patient_id', $patient->id)
            ->where('hospital_id', $hospitalId)
            ->where('status', 'active')
            ->first();

        // تنسيق تاريخ الميلاد بشكل موحد Y/m/d
        $birthFormatted = $patient->birth_date
            ? Carbon::parse($patient->birth_date)->format('Y/m/d')
            : null;

        $medications = [];

        // تحديد الصيدلية للتحقق من المخزون
        $pharmacist = $request->user();
        $pharmacyId = null;
        
        if ($pharmacist->pharmacy_id) {
            $pharmacyId = $pharmacist->pharmacy_id;
        } elseif ($patient->hospital_id) {
            $pharmacy = Pharmacy::where('hospital_id', $patient->hospital_id)->first();
            $pharmacyId = $pharmacy ? $pharmacy->id : null;
        }
        
        // حل مؤقت للتجربة
        if (!$pharmacyId) $pharmacyId = 1;

        if ($activePrescription) {
            foreach ($activePrescription->drugs as $drug) {
                $pivot = $drug->pivot;

                $monthlyQty = (int)($pivot->monthly_quantity ?? 0);
                $unit = $this->getDrugUnit($drug);

                // آخر عملية صرف لهذا الدواء (لتحديد أخر إستلام)
                $lastDispense = Dispensing::where('patient_id', $patient->id)
                    ->where('drug_id', $drug->id)
                    ->latest('created_at')
                    ->first();

                $assignmentDate = $pivot->created_at
                    ? Carbon::parse($pivot->created_at)->format('Y/m/d')
                    : ($activePrescription->start_date ? Carbon::parse($activePrescription->start_date)->format('Y/m/d') : null);

                $lastDispenseDate = $lastDispense && $lastDispense->created_at
                    ? Carbon::parse($lastDispense->created_at)->format('Y/m/d')
                    : null;

                // نعرض للمستخدم تاريخ "آخر إستلام" إن وجد، وإلا تاريخ الإسناد
                $displayDate = $lastDispenseDate ?? $assignmentDate;
                
                // تاريخ آخر تعديل للترتيب (أولوية: آخر صرف > آخر تحديث في pivot > تاريخ الإسناد)
                $lastUpdateDate = null;
                // استخدام created_at من Dispensing إذا كان موجوداً
                if ($lastDispense) {
                    // محاولة استخدام created_at أولاً
                    if ($lastDispense->created_at) {
                        $lastUpdateDate = Carbon::parse($lastDispense->created_at);
                    } 
                    // إذا لم يكن موجوداً، استخدم dispense_month
                    elseif ($lastDispense->dispense_month) {
                        $lastUpdateDate = Carbon::parse($lastDispense->dispense_month);
                    }
                }
                
                // إذا لم يكن هناك صرف، استخدم updated_at من pivot
                if (!$lastUpdateDate && $pivot->updated_at) {
                    $lastUpdateDate = Carbon::parse($pivot->updated_at);
                }
                
                // إذا لم يكن هناك updated_at، استخدم created_at من pivot
                if (!$lastUpdateDate && $pivot->created_at) {
                    $lastUpdateDate = Carbon::parse($pivot->created_at);
                }
                
                // إذا لم يكن هناك created_at، استخدم start_date من الوصفة
                if (!$lastUpdateDate && $activePrescription->start_date) {
                    $lastUpdateDate = Carbon::parse($activePrescription->start_date);
                }
                
                // إذا لم يكن هناك أي تاريخ، استخدم التاريخ الحالي
                if (!$lastUpdateDate) {
                    $lastUpdateDate = Carbon::now();
                }

                // ============================================================
                // تحديد حالة الاستحقاق بناءً على:
                // 1. توفر الدواء في المخزون (أولوية أولى)
                // 2. الكمية المصروفة الشهرية (يجب أن تكون أقل من الكمية الشهرية)
                // ============================================================
                
                // 1. التحقق من توفر الدواء في المخزون
                $inventory = null;
                if ($pharmacyId) {
                    $inventory = Inventory::where('drug_id', $drug->id)
                        ->where('pharmacy_id', $pharmacyId)
                        ->first();
                }
                
                $eligibilityStatus = 'مستحق';
                
                // حساب الكمية المصروفة في الشهر الحالي (يتم حسابها دائماً لعرضها)
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                
                $totalDispensedThisMonth = (int)Dispensing::where('patient_id', $patient->id)
                    ->where('drug_id', $drug->id)
                    ->whereBetween('dispense_month', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
                    ->sum('quantity_dispensed');
                
                // حساب الكمية المتبقية
                $remainingQuantity = max(0, $monthlyQty - $totalDispensedThisMonth);
                
                // إذا لم يكن الدواء متوفراً في المخزون (غير موجود أو الكمية = 0)
                if (!$inventory || $inventory->current_quantity <= 0) {
                    $eligibilityStatus = 'غير متوفر';
                } else {
                    // إذا تم صرف الكمية الشهرية كاملة أو أكثر، يصبح غير مستحق
                    if ($monthlyQty > 0 && $totalDispensedThisMonth >= $monthlyQty) {
                        $eligibilityStatus = 'غير مستحق';
                    }
                }

                // تحويل الجرعة الشهرية إلى جرعة يومية نصية
                $dailyQty = $monthlyQty > 0 ? round($monthlyQty / 30, 1) : 0;
                $dosageText = $dailyQty > 0
                    ? (($dailyQty % 1 === 0) ? (int)$dailyQty : $dailyQty) . ' ' . $unit . ' يومياً'
                    : 'غير محدد';

                // تنسيق الكمية الشهرية كنص مع الوحدة الصحيحة
                $monthlyQuantityText = $monthlyQty > 0 ? $monthlyQty . ' ' . $unit : 'غير محدد';

                // تنسيق الكمية المتبقية كنص
                $remainingQuantityText = '';
                if ($monthlyQty > 0 && $totalDispensedThisMonth > 0) {
                    $remainingQuantityText = $remainingQuantity > 0 
                        ? $remainingQuantity . ' ' . $unit . ' متبقية'
                        : 'تم صرف الكمية الشهرية كاملة';
                }

                // اسم من قام بالإسناد (من audit_log أو اسم الطبيب كبديل)
                $latestLog = \App\Models\AuditLog::where('table_name', 'prescription_drug')
                    ->where('record_id', $pivot->id)
                    ->whereIn('action', ['إضافة دواء', 'تعديل دواء'])
                    ->with('user')
                    ->latest()
                    ->first();
                
                $assignedBy = $latestLog && $latestLog->user 
                    ? $latestLog->user->full_name 
                    : ($activePrescription->doctor 
                        ? ($activePrescription->doctor->full_name ?? $activePrescription->doctor->name)
                        : 'غير محدد');

                $medications[] = [
                    'id' => $drug->id,
                    'pivot_id' => $pivot->id,
                    'drugName' => $drug->name,
                    'dosage' => $dosageText,
                    'monthlyQuantity' => $monthlyQuantityText,
                    'monthlyQuantityNum' => $monthlyQty,
                    'unit' => $unit,
                    'assignmentDate' => $displayDate,
                    'assignedBy' => $assignedBy,
                    'eligibilityStatus' => $eligibilityStatus,
                    // معلومات الصرف الشهري
                    'totalDispensedThisMonth' => $totalDispensedThisMonth,
                    'remainingQuantity' => $remainingQuantity,
                    'remainingQuantityText' => $remainingQuantityText,
                    // ستتغير من الواجهة عند إدخال كمية الصرف
                    'dispensedQuantity' => 0,
                    'note' => $pivot->note,
                    // تاريخ آخر تعديل للترتيب
                    '_lastUpdateTimestamp' => $lastUpdateDate->timestamp,
                ];
            }
            
            // ترتيب الأدوية حسب آخر تعديل (الأحدث أولاً)
            if (count($medications) > 1) {
                // استخدام usort للترتيب بشكل مباشر
                usort($medications, function($a, $b) {
                    $timestampA = isset($a['_lastUpdateTimestamp']) ? (int)$a['_lastUpdateTimestamp'] : 0;
                    $timestampB = isset($b['_lastUpdateTimestamp']) ? (int)$b['_lastUpdateTimestamp'] : 0;
                    
                    // ترتيب تنازلي: الأحدث أولاً (الأكبر أولاً)
                    // إذا كانت القيم متساوية، نرتب حسب اسم الدواء
                    if ($timestampB === $timestampA) {
                        return strcmp($a['drugName'] ?? '', $b['drugName'] ?? '');
                    }
                    
                    return $timestampB - $timestampA;
                });
                
                // إزالة الحقل المؤقت بعد الترتيب
                foreach ($medications as &$med) {
                    if (isset($med['_lastUpdateTimestamp'])) {
                        unset($med['_lastUpdateTimestamp']);
                    }
                }
                unset($med); // إزالة المرجع الأخير
            } elseif (count($medications) === 1) {
                // إذا كان هناك دواء واحد فقط، أزل الحقل المؤقت
                if (isset($medications[0]['_lastUpdateTimestamp'])) {
                    unset($medications[0]['_lastUpdateTimestamp']);
                }
            }
        }

        $data = [
            'fileNumber' => $patient->id,
            'name' => $patient->full_name ?? $patient->name,
            'nationalId' => $patient->national_id,
            'birth' => $birthFormatted ?? 'غير محدد',
            'phone' => $patient->phone,
            'lastUpdated' => $patient->updated_at ? Carbon::parse($patient->updated_at)->format('Y/m/d H:i') : null,
            'medications' => $medications,
        ];

        return $this->sendSuccess($data, 'تم جلب بيانات المريض بنجاح.');
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

                // هـ. إنشاء أو تحديث سجل الصرف
                // إذا كان هناك سجل موجود لنفس الدواء في نفس الشهر، نضيف الكمية الجديدة
                $dispenseMonth = Carbon::now()->startOfMonth()->format('Y-m-d');
                
                $existingDispensing = Dispensing::where('prescription_id', $prescription->id)
                    ->where('drug_id', $drug->id)
                    ->where('dispense_month', $dispenseMonth)
                    ->first();
                
                if ($existingDispensing) {
                    // تحديث الكمية المصروفة بإضافة الكمية الجديدة
                    $existingDispensing->quantity_dispensed += $item['quantity'];
                    $existingDispensing->pharmacist_id = $pharmacist->id;
                    $existingDispensing->pharmacy_id = $pharmacyId;
                    $existingDispensing->save();
                } else {
                    // إنشاء سجل جديد
                    Dispensing::create([
                        'prescription_id' => $prescription->id,
                        'patient_id' => $patient->id,
                        'drug_id' => $drug->id,
                        'pharmacist_id' => $pharmacist->id,
                        'pharmacy_id' => $pharmacyId,
                        'dispense_month' => $dispenseMonth,
                        'quantity_dispensed' => $item['quantity'],
                    ]);
                }
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

    /**
     * تحديد وحدة القياس بناءً على نوع الدواء (منسوخ من منطق الطبيب ليتوافق مع الواجهة).
     */
    private function getDrugUnit($drug)
    {
        // أولاً: استخدام وحدة القياس المباشرة من جدول الدواء
        if (!empty($drug->unit)) {
            $unit = strtolower(trim($drug->unit));
            
            // تحويل الوحدات الشائعة إلى التنسيق المطلوب
            // للأدوية الصلبة (حبوب/أقراص)
            if (in_array($unit, ['قرص', 'حبة', 'tablet', 'capsule', 'pill', 'tab', 'cap'])) {
                return 'حبة';
            }
            // للأدوية السائلة
            if (in_array($unit, ['مل', 'ml', 'milliliter', 'millilitre', 'ملل', 'ملليلتر'])) {
                return 'مل';
            }
            // للحقن
            if (in_array($unit, ['أمبول', 'ampoule', 'ampule', 'vial', 'حقنة', 'amp'])) {
                return 'أمبول';
            }
            // للمراهم والكريمات
            if (in_array($unit, ['جرام', 'gram', 'gm', 'g', 'غرام'])) {
                return 'جرام';
            }
            // إذا كانت الوحدة موجودة ولكن غير معروفة، نستخدمها كما هي
            return $drug->unit;
        }
        
        // ثانياً: استخدام form لتحديد الوحدة
        if (!empty($drug->form)) {
            $form = strtolower(trim($drug->form));
            
            // للأدوية الصلبة
            if (in_array($form, ['tablet', 'capsule', 'pill', 'tab', 'cap', 'قرص', 'حبة', 'كبسولة'])) {
                return 'حبة';
            }
            // للأدوية السائلة
            if (in_array($form, ['liquid', 'syrup', 'suspension', 'solution', 'elixir', 'drops', 'قطرة', 'سائل', 'شراب', 'معلق', 'محلول'])) {
                return 'مل';
            }
            // للحقن
            if (in_array($form, ['injection', 'ampoule', 'ampule', 'vial', 'amp', 'حقن', 'أمبول', 'حقنة'])) {
                return 'أمبول';
            }
            // للمراهم والكريمات
            if (in_array($form, ['ointment', 'cream', 'gel', 'lotion', 'مرهم', 'كريم', 'جل', 'لوشن'])) {
                return 'جرام';
            }
        }
        
        // ثالثاً: البحث في اسم الدواء عن إشارات للنوع
        if (!empty($drug->name)) {
            $name = strtolower($drug->name);
            
            // البحث عن كلمات تشير إلى سائل
            if (preg_match('/\b(syrup|suspension|solution|liquid|drops|شراب|معلق|محلول|قطرة|سائل|شرب)\b/i', $name)) {
                return 'مل';
            }
            // البحث عن كلمات تشير إلى حقن
            if (preg_match('/\b(injection|ampoule|vial|amp|حقن|أمبول|حقنة)\b/i', $name)) {
                return 'أمبول';
            }
            // البحث عن كلمات تشير إلى مرهم
            if (preg_match('/\b(ointment|cream|gel|lotion|مرهم|كريم|جل|لوشن)\b/i', $name)) {
                return 'جرام';
            }
        }
        
        // افتراضياً: حبة (للأدوية الصلبة)
        return 'حبة';
    }
}
