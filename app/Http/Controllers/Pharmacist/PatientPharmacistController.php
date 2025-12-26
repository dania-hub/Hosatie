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
use App\Models\AuditLog;
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
        $user = $request->user();
        $hospitalId = $user->hospital_id;

        // التأكد من أن المستخدم لديه hospital_id
        if (!$hospitalId) {
            return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
        }

        // جلب المرضى الذين لديهم نفس hospital_id فقط
        $patients = User::where('type', 'patient')
            ->where('hospital_id', $hospitalId)
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
        $user = $request->user();
        $hospitalId = $user->hospital_id;

        // التأكد من أن المستخدم لديه hospital_id
        if (!$hospitalId) {
            return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
        }

        $patient = User::where('type', 'patient')
            ->where('id', $fileNumber)
            ->where('hospital_id', $hospitalId)
            ->first();

        if (!$patient) {
            return $this->sendError('المريض غير موجود.', [], 404);
        }

        // البحث عن الوصفة النشطة: أولاً في المستشفى الحالي، ثم في جميع المستشفيات
        // هذا يسمح بعرض الأدوية للمرضى المنقولين حديثاً
        $activePrescription = Prescription::with(['drugs', 'doctor'])
            ->where('patient_id', $patient->id)
            ->where('status', 'active')
            ->orderByRaw("CASE WHEN hospital_id = ? THEN 0 ELSE 1 END", [$hospitalId])
            ->latest('start_date')
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
                // البحث في جميع سجلات الصرف بغض النظر عن المستشفى (للمرضى المنقولين)
                $lastDispense = Dispensing::where('patient_id', $patient->id)
                    ->where('drug_id', $drug->id)
                    ->where('reverted', false) // استبعاد السجلات الملغاة
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
                
                // حساب الكمية المصروفة في الشهر الحالي من جميع المستشفيات
                // هذا يضمن حساب دقيق للمرضى المنقولين
                $totalDispensedThisMonth = (int)Dispensing::where('patient_id', $patient->id)
                    ->where('drug_id', $drug->id)
                    ->where('reverted', false) // استبعاد السجلات الملغاة
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
                // البحث في كلا الحالتين prescription_drug و prescription_drugs لضمان التوافق
                $latestLog = \App\Models\AuditLog::whereIn('table_name', ['prescription_drug', 'prescription_drugs'])
                    ->where('record_id', $pivot->id)
                    ->whereIn('action', ['إضافة دواء', 'تعديل دواء'])
                    ->with('user')
                    ->latest()
                    ->first();
                
                $assignedBy = 'غير محدد';
                if ($latestLog && $latestLog->user) {
                    // استخدام full_name أولاً، ثم name كبديل
                    $assignedBy = $latestLog->user->full_name ?? $latestLog->user->name ?? 'غير محدد';
                } elseif ($activePrescription->doctor) {
                    // إذا لم يكن هناك سجل audit log، استخدم اسم الطبيب
                    $assignedBy = $activePrescription->doctor->full_name ?? $activePrescription->doctor->name ?? 'غير محدد';
                }

                $medications[] = [
                    'id' => $drug->id,
                    'pivot_id' => $pivot->id,
                    'drugName' => $drug->name,
                    'strength' => $drug->strength ?? null,
                    'dosage' => $dosageText,
                    'dailyQuantity' => $dailyQty,
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
            $pharmacist = $request->user();
            $hospitalId = $pharmacist->hospital_id;

            // التأكد من أن المستخدم لديه hospital_id
            if (!$hospitalId) {
                throw new \Exception("المستخدم غير مرتبط بمستشفى.");
            }

            $patient = User::where('id', $request->patientFileNumber)
                ->where('type', 'patient')
                ->where('hospital_id', $hospitalId)
                ->first();

            if (!$patient) {
                throw new \Exception("المريض غير موجود.");
            }

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
            $dispensationsCreated = [];
            $inventoryChanges = [];
            
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
                
                // حفظ الكمية الأصلية للمخزون للتراجع
                $originalQuantity = $inventory->current_quantity;
                
                // د. خصم الكمية والحفظ
                $inventory->current_quantity -= $item['quantity'];
                $inventory->save();
                
                // حفظ معلومات تغيير المخزون للتراجع
                $inventoryChanges[] = [
                    'inventory_id' => $inventory->id,
                    'drug_id' => $drug->id,
                    'quantity_added_back' => $item['quantity'],
                ];

                // هـ. إنشاء أو تحديث سجل الصرف
                // إذا كان هناك سجل موجود لنفس الدواء في نفس الشهر، نضيف الكمية الجديدة
                $dispenseMonth = Carbon::now()->startOfMonth()->format('Y-m-d');
                
                $existingDispensing = Dispensing::where('prescription_id', $prescription->id)
                    ->where('drug_id', $drug->id)
                    ->where('dispense_month', $dispenseMonth)
                    ->first();
                
                if ($existingDispensing) {
                    // حفظ الكمية الأصلية للتراجع
                    $originalDispensedQuantity = $existingDispensing->quantity_dispensed;
                    
                    // تحديث الكمية المصروفة بإضافة الكمية الجديدة
                    $existingDispensing->quantity_dispensed += $item['quantity'];
                    $existingDispensing->pharmacist_id = $pharmacist->id;
                    $existingDispensing->pharmacy_id = $pharmacyId;
                    $existingDispensing->save();
                    
                    // حفظ معلومات السجل المحدث للتراجع
                    $dispensationsCreated[] = [
                        'dispensing_id' => $existingDispensing->id,
                        'drug_id' => $drug->id,
                        'drug_name' => $item['drugName'],
                        'quantity' => $item['quantity'],
                        'original_quantity' => $originalDispensedQuantity,
                        'is_new' => false,
                    ];
                } else {
                    // إنشاء سجل جديد
                    $dispensing = Dispensing::create([
                        'prescription_id' => $prescription->id,
                        'patient_id' => $patient->id,
                        'drug_id' => $drug->id,
                        'pharmacist_id' => $pharmacist->id,
                        'pharmacy_id' => $pharmacyId,
                        'dispense_month' => $dispenseMonth,
                        'quantity_dispensed' => $item['quantity'],
                    ]);
                    
                    // حفظ معلومات السجل الجديد للتراجع
                    $dispensationsCreated[] = [
                        'dispensing_id' => $dispensing->id,
                        'drug_id' => $drug->id,
                        'drug_name' => $item['drugName'],
                        'quantity' => $item['quantity'],
                        'original_quantity' => 0,
                        'is_new' => true,
                    ];
                }
            }

            DB::commit();
            return $this->sendSuccess([
                'dispensations' => $dispensationsCreated,
                'inventory_changes' => $inventoryChanges,
            ], 'تم صرف الأدوية الموصوفة بنجاح.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('فشل في العملية: ' . $e->getMessage());
        }
    }


    /**
     * POST /api/pharmacist/dispense/undo
     * التراجع عن صرف الأدوية (يجب أن يكون خلال 7 ثوانٍ من الصرف).
     */
    public function undoDispense(Request $request)
    {
        $request->validate([
            'dispensations' => 'required|array',
            'dispensations.*.dispensing_id' => 'required|exists:dispensings,id',
            'dispensations.*.drug_id' => 'required|exists:drugs,id',
            'dispensations.*.quantity' => 'required|integer|min:1',
            'dispensations.*.is_new' => 'required|boolean',
            'dispensations.*.original_quantity' => 'nullable|integer|min:0',
            'inventory_changes' => 'required|array',
            'inventory_changes.*.inventory_id' => 'required|exists:inventories,id',
            'inventory_changes.*.quantity_added_back' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $pharmacist = $request->user();
            $hospitalId = $pharmacist->hospital_id;

            // التأكد من أن المستخدم لديه hospital_id
            if (!$hospitalId) {
                throw new \Exception("المستخدم غير مرتبط بمستشفى.");
            }

            // 1. التراجع عن تغييرات المخزون
            foreach ($request->inventory_changes as $change) {
                $inventory = Inventory::find($change['inventory_id']);
                
                if ($inventory) {
                    // إرجاع الكمية المخصومة
                    $inventory->current_quantity += $change['quantity_added_back'];
                    $inventory->save();
                }
            }

            // 2. التراجع عن سجلات الصرف وتسجيل العملية
            $patientId = null;
            $drugsInfo = [];
            
            foreach ($request->dispensations as $disp) {
                $dispensing = Dispensing::find($disp['dispensing_id']);
                
                if (!$dispensing) {
                    continue;
                }

                // حفظ معرف المريض
                if (!$patientId) {
                    $patientId = $dispensing->patient_id;
                }

                // جلب معلومات الدواء للمستخدم
                $drug = Drug::find($disp['drug_id']);
                $drugName = $drug ? $drug->name : 'غير محدد';
                
                $drugsInfo[] = [
                    'drug_name' => $drugName,
                    'quantity' => $disp['quantity'],
                ];

                // التحقق من أن الصيدلاني الحالي هو نفس من قام بالصرف (أو السماح للصيدلانيين من نفس المستشفى)
                // يمكن إضافة هذا التحقق إذا أردت تقييد التراجع للصيدلاني نفسه فقط:
                // if ($dispensing->pharmacist_id !== $pharmacist->id) {
                //     throw new \Exception("لا يمكن التراجع عن صرف قام به صيدلاني آخر.");
                // }

                if ($disp['is_new']) {
                    // إذا كان سجل جديد، احذفه
                    $dispensing->delete();
                } else {
                    // إذا كان سجل موجود، أرجع الكمية إلى القيمة الأصلية
                    $dispensing->quantity_dispensed = $disp['original_quantity'] ?? 0;
                    
                    // إذا أصبحت الكمية 0 أو أقل، احذف السجل
                    if ($dispensing->quantity_dispensed <= 0) {
                        $dispensing->delete();
                    } else {
                        $dispensing->save();
                    }
                }
            }

            // 3. تسجيل عملية التراجع في AuditLog
            if ($patientId && count($drugsInfo) > 0) {
                $patient = User::find($patientId);
                $patientName = $patient ? ($patient->full_name ?? $patient->name ?? 'غير محدد') : 'غير محدد';
                
                // إنشاء نص وصف للعملية
                $drugsText = collect($drugsInfo)->map(function($drug) {
                    return "{$drug['drug_name']} ({$drug['quantity']})";
                })->implode('، ');
                
                AuditLog::create([
                    'user_id' => $pharmacist->id,
                    'hospital_id' => $hospitalId,
                    'action' => 'تراجع عن صرف وصفة طبية',
                    'table_name' => 'dispensings',
                    'record_id' => $patientId,
                    'old_values' => null,
                    'new_values' => json_encode([
                        'patient_id' => $patientId,
                        'patient_name' => $patientName,
                        'drugs' => $drugsInfo,
                        'drugs_text' => $drugsText,
                    ]),
                    'ip_address' => $request->ip(),
                ]);
            }

            DB::commit();
            return $this->sendSuccess([], 'تم التراجع عن صرف الأدوية بنجاح.');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('فشل في التراجع: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/pharmacist/patients/{fileNumber}/dispensations
     * سجل صرف المريض.
     */
    public function history(Request $request, $fileNumber)
    {
        $user = $request->user();
        $hospitalId = $user->hospital_id;

        // التأكد من أن المستخدم لديه hospital_id
        if (!$hospitalId) {
            return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
        }

        $patient = User::where('id', $fileNumber)
            ->where('type', 'patient')
            ->where('hospital_id', $hospitalId)
            ->first();
        
        if (!$patient) {
            return $this->sendError('المريض غير موجود.', [], 404);
        }

        // جلب جميع سجلات الصرف للمريض بغض النظر عن المستشفى
        // هذا يضمن عرض سجل الصرف الكامل للمرضى المنقولين
        $dispensations = Dispensing::with(['drug', 'pharmacist', 'pharmacy'])
            ->where('patient_id', $patient->id)
            ->where('reverted', false) // استبعاد السجلات الملغاة
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedHistory = $dispensations->map(function ($d) {
            // تحديد تاريخ الصرف (أولوية: created_at > dispense_month)
            $dispenseDate = null;
            if ($d->created_at) {
                $dispenseDate = Carbon::parse($d->created_at)->format('Y/m/d');
            } elseif ($d->dispense_month) {
                $dispenseDate = Carbon::parse($d->dispense_month)->format('Y/m/d');
            }
            
            return [
                'id' => $d->id,
                'date' => $dispenseDate ?? ($d->created_at ? Carbon::parse($d->created_at)->format('Y/m/d') : 'غير محدد'),
                'pharmacistName' => $d->pharmacist ? ($d->pharmacist->full_name ?? $d->pharmacist->name) : 'غير معروف',
                'pharmacyName' => $d->pharmacy ? ($d->pharmacy->name ?? 'غير محدد') : null,
                'totalItems' => 1,
                'items' => [
                    [
                        'drugName' => $d->drug ? $d->drug->name : 'غير معروف', 
                        'quantity' => $d->quantity_dispensed ?? 0,
                        'unit' => $d->drug ? ($d->drug->unit ?? 'حبة') : 'حبة'
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
