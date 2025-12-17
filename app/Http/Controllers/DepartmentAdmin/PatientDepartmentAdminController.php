<?php

namespace App\Http\Controllers\DepartmentAdmin;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Prescription;
use App\Models\PrescriptionDrug;
use App\Models\Drug;
use App\Models\Dispensing;
use Illuminate\Support\Facades\DB;

class PatientDepartmentAdminController extends BaseApiController
{
    /**
     * GET /api/department-admin/patients
     * List all patients (for the main table)
     */
    public function index(Request $request)
    {
        $query = User::where('type', 'patient');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%$search%")
                  ->orWhere('national_id', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%")
                  ->orWhere('id', 'like', "%$search%");
            });
        }

        $patients = $query->get()->map(function ($patient) {
            return [
                'fileNumber' => $patient->id,
                'name'       => $patient->full_name,
                'nationalId' => $patient->national_id,
                'birth'      => $patient->birth_date,
                'phone'      => $patient->phone,
                'lastUpdated'=> $patient->updated_at->toIso8601String(),
            ];
        });

        return $this->sendSuccess($patients, 'تم جلب قائمة المرضى.');
    }

    /**
     * GET /api/department-admin/patients/{id}
     * Get single patient details + medications
     */
    public function show($id)
    {
        $patient = User::where('type', 'patient')->where('id', $id)->first();
        if (!$patient) return $this->sendError('المريض غير موجود.', [], 404);

        // Get active prescription for this department/hospital
        // تحميل العلاقة doctor للحصول على اسم الدكتور الذي قام بالإسناد
        $activePrescription = Prescription::with(['drugs', 'doctor'])
            ->where('patient_id', $patient->id)
            ->where('status', 'active')
            ->first();

        $data = [
            'fileNumber' => $patient->id,
            'name'       => $patient->full_name,
            'nationalId' => $patient->national_id,
            'birth'      => $patient->birth_date,
            'phone'      => $patient->phone,
            'medications' => $activePrescription ? $activePrescription->drugs->map(function($drug) use ($activePrescription) {
                // استخدام created_at من pivot كتاريخ الإسناد، أو start_date من prescription
                $assignmentDate = $drug->pivot->created_at 
                    ? $drug->pivot->created_at->format('Y-m-d')
                    : ($activePrescription->start_date ? $activePrescription->start_date->format('Y-m-d') : null);
                
                // اسم الطبيب الذي قام بالإسناد من العلاقة doctor
                $assignedBy = $activePrescription->doctor ? $activePrescription->doctor->full_name : 'غير محدد';
                
                // الحصول على الكمية الشهرية من قاعدة البيانات
                $monthlyQty = (int)($drug->pivot->monthly_quantity ?? 0);
                
                // تحديد وحدة القياس من بيانات الدواء
                $unit = $this->getDrugUnit($drug);
                
                // تحويل الجرعة الشهرية إلى جرعة يومية نصية
                $dailyQty = $monthlyQty > 0 ? round($monthlyQty / 30, 1) : 0;
                $dosageText = $dailyQty > 0 
                    ? (($dailyQty % 1 === 0) ? (int)$dailyQty : $dailyQty) . ' ' . $unit . ' يومياً'
                    : 'غير محدد';
                
                // تنسيق الكمية الشهرية كنص مع الوحدة الصحيحة
                $monthlyQuantityText = $monthlyQty > 0 ? $monthlyQty . ' ' . $unit : 'غير محدد';
                
                return [
                    'id' => $drug->id,
                    'pivot_id' => $drug->pivot->id ?? null, // مهم للتعديل/الحذف
                    'drugName' => $drug->name,
                    'dosage' => $dosageText, // الجرعة اليومية: "2 مل يومياً" أو "2 حبة يومياً"
                    'monthlyQuantity' => $monthlyQuantityText, // الكمية الشهرية: "60 مل" أو "60 حبة"
                    'monthlyQuantityNum' => $monthlyQty, // الكمية الشهرية كرقم للعمليات الحسابية
                    'unit' => $unit, // وحدة القياس للاستخدام في الواجهة
                    'assignmentDate' => $assignmentDate,
                    'assignedBy' => $assignedBy, // اسم الدكتور الفعلي من قاعدة البيانات
                    'note' => $drug->pivot->note ?? null,
                ];
            }) : [],

        ];

        return $this->sendSuccess($data, 'تم جلب بيانات المريض.');
    }

    /**
     * PUT /api/department-admin/patients/{id}/medications
     * Sync medications list (Add/Remove/Update all at once)
     */
    public function updateMedications(Request $request, $id)
    {
        $request->validate([
            'medications' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $hospitalId = $request->user()->hospital_id;
            $currentUserId = $request->user()->id; // المستخدم الحالي (Department Admin أو Doctor)

            $patient = User::where('type', 'patient')->where('id', $id)->first();
            if (!$patient) {
                return $this->sendError('المريض غير موجود.', [], 404);
            }

            // البحث عن وصفة نشطة أو إنشاء واحدة
            $prescription = Prescription::where('patient_id', $patient->id)
                ->where('hospital_id', $hospitalId)
                ->where('status', 'active')
                ->first();

            if (!$prescription) {
                // إنشاء وصفة جديدة مع تعيين المستخدم الحالي كطبيب
                $prescription = Prescription::create([
                    'patient_id' => $patient->id,
                    'hospital_id' => $hospitalId,
                    'doctor_id' => $currentUserId, // تعيين المستخدم الحالي
                    'start_date' => now(),
                    'status' => 'active',
                ]);
            } else {
                // تحديث doctor_id إذا لم يكن محدداً
                if (!$prescription->doctor_id) {
                    $prescription->doctor_id = $currentUserId;
                    $prescription->save();
                }
            }

            // حذف جميع الأدوية القديمة
            PrescriptionDrug::where('prescription_id', $prescription->id)->delete();

            $created = [];
            
            // إضافة الأدوية الجديدة
            foreach ($request->medications as $med) {
                // البحث عن الدواء بواسطة الاسم أو ID
                $drug = null;
                
                if (!empty($med['drugId']) || !empty($med['id'])) {
                    $drug = Drug::find($med['drugId'] ?? $med['id']);
                }
                
                if (!$drug && !empty($med['drugName'])) {
                    $drug = Drug::where('name', $med['drugName'])->first();
                }
                
                // إذا لم يوجد الدواء، إنشاء واحد جديد
                if (!$drug && !empty($med['drugName'])) {
                    $drug = Drug::create([
                        'name' => $med['drugName'],
                        'generic_name' => $med['drugName'],
                        'strength' => $med['dosage'] ?? '',
                        'form' => '',
                        'category' => '',
                        'unit' => '',
                        'max_monthly_dose' => null,
                        'status' => 'active',
                        'manufacturer' => '',
                        'country' => '',
                        'utilization_type' => '',
                        'warnings' => '',
                        'expiry_date' => null,
                    ]);
                }

                if ($drug) {
                    // حساب الكمية الشهرية
                    $monthlyQuantity = 0;
                    
                    // أولاً: محاولة الحصول من monthlyQuantity
                    if (!empty($med['monthlyQuantity'])) {
                        if (is_string($med['monthlyQuantity'])) {
                            // إذا كانت نصية، استخراج الرقم فقط (تجاهل النص)
                            preg_match('/(\d+)/', $med['monthlyQuantity'], $matches);
                            $monthlyQuantity = isset($matches[1]) ? (int)$matches[1] : 0;
                        } else {
                            $monthlyQuantity = (int)$med['monthlyQuantity'];
                        }
                    }
                    
                    // ثانياً: إذا لم يكن هناك monthlyQuantity، احسب من dosage
                    if ($monthlyQuantity <= 0 && !empty($med['dosage'])) {
                        if (is_string($med['dosage'])) {
                            // استخراج الرقم من dosage مثل "2 حبة يومياً"
                            preg_match('/(\d+(?:\.\d+)?)/', $med['dosage'], $matches);
                            if (isset($matches[1])) {
                                $dailyQty = (float)$matches[1];
                                $monthlyQuantity = (int)round($dailyQty * 30);
                            }
                        } elseif (is_numeric($med['dosage'])) {
                            // إذا كان dosage رقم (جرعة يومية)
                            $monthlyQuantity = (int)round($med['dosage'] * 30);
                        }
                    }
                    
                    // ثالثاً: إذا لم ينجح شيء، استخدم monthlyQuantityNum إذا كان موجوداً
                    if ($monthlyQuantity <= 0 && !empty($med['monthlyQuantityNum'])) {
                        $monthlyQuantity = (int)$med['monthlyQuantityNum'];
                    }
                    
                    // التأكد من وجود قيمة صالحة (أكبر من 0)
                    if ($monthlyQuantity <= 0) {
                        $monthlyQuantity = 30; // قيمة افتراضية
                    }

                    $pd = PrescriptionDrug::create([
                        'prescription_id' => $prescription->id,
                        'drug_id' => $drug->id,
                        'monthly_quantity' => $monthlyQuantity,
                        'note' => $med['note'] ?? null,
                    ]);

                    // تحديد وحدة القياس من بيانات الدواء
                    $unit = $this->getDrugUnit($drug);
                    $dailyQty = $monthlyQuantity > 0 ? round($monthlyQuantity / 30, 1) : 0;
                    $dosageText = $dailyQty > 0 
                        ? (($dailyQty % 1 === 0) ? (int)$dailyQty : $dailyQty) . ' ' . $unit . ' يومياً'
                        : 'غير محدد';
                    $monthlyQuantityText = $monthlyQuantity > 0 ? $monthlyQuantity . ' ' . $unit : 'غير محدد';

                    $created[] = [
                        'id' => $drug->id,
                        'pivot_id' => $pd->id,
                        'drugName' => $drug->name,
                        'dosage' => $dosageText,
                        'monthlyQuantity' => $monthlyQuantityText,
                        'monthlyQuantityNum' => $monthlyQuantity,
                        'unit' => $unit,
                        'assignmentDate' => $prescription->start_date ? $prescription->start_date->format('Y-m-d') : null,
                        'assignedBy' => $request->user()->full_name, // اسم المستخدم الحالي
                        'note' => $pd->note,
                    ];
                }
            }

            DB::commit();

            // إعادة جلب البيانات المحدثة
            return $this->show($id);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('حدث خطأ أثناء حفظ البيانات.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/department-admin/patients/{id}/dispensation-history
     */
    public function dispensationHistory($id)
    {
        $history = Dispensing::where('patient_id', $id)->get();
        // Map to match frontend expectation if needed
        return $this->sendSuccess($history, 'تم جلب سجل الصرف.');
    }

    /**
     * تحديد وحدة القياس بناءً على نوع الدواء
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
