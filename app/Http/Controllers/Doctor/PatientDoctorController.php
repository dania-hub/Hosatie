<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Prescription;
use Carbon\Carbon;

class PatientDoctorController extends BaseApiController
{
    public function index(Request $request)
    {
        $hospitalId = $request->user()->hospital_id;

        // التأكد من أن المستخدم لديه hospital_id
        if (!$hospitalId) {
            return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
        }

        // 1. Query Patients (with Search support)
        // عرض المرضى الذين لديهم نفس hospital_id فقط
        $query = User::where('type', 'patient')
            ->where('hospital_id', $hospitalId);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%$search%")
                  ->orWhere('national_id', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%")
                  ->orWhere('id', 'like', "%$search%");
            });
        }

        // 2. Get Data
        $patients = $query->get()->map(function ($patient) use ($hospitalId) {
            // تنسيق تاريخ الميلاد بشكل موحد Y/m/d
            $birthFormatted = $patient->birth_date
                ? Carbon::parse($patient->birth_date)->format('Y/m/d')
                : null;
            
            // Find Active Prescription for this Patient in THIS Hospital
            $activePrescription = Prescription::with(['drugs'])
                ->where('patient_id', $patient->id)
                ->where('hospital_id', $hospitalId)
                ->where('status', 'active')
                ->first();

            return [
                'fileNumber' => $patient->id,
                'name'       => $patient->full_name,
                'nationalId' => $patient->national_id,
                // تاريخ الميلاد بصيغة موحدة لواجهة الطبيب
                'birth'      => $birthFormatted ?? 'غير محدد',
                'phone'      => $patient->phone,
                'lastUpdated'=> $patient->updated_at->toIso8601String(),
                
                // Return Medications List (Formatted for Frontend)
                'medications' => $activePrescription ? $activePrescription->drugs->map(function($drug) {
                    return [
                        'id'       => $drug->id,
                        'pivot_id' => $drug->pivot->id, // Important for Update/Delete
                        'drugName' => $drug->name,
                        'strength' => $drug->strength ?? null,
                        'dosage'   => $drug->pivot->monthly_quantity,
                        'note'     => $drug->pivot->note
                    ];
                }) : [],

                'hasPrescription' => !!$activePrescription
            ];
        });

        return $this->sendSuccess($patients, 'تم جلب بيانات المرضى بنجاح.');
    }

    /**
     * Get Single Patient Details (Added to complete the controller)
     */
    public function show(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;
        
        // التأكد من أن المستخدم لديه hospital_id
        if (!$hospitalId) {
            return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
        }
        
        $patient = User::where('type', 'patient')
            ->where('hospital_id', $hospitalId)
            ->where('id', $id)
            ->first();

        if (!$patient) return $this->sendError('المريض غير موجود أو غير مرتبط بنفس المستشفى.', [], 404);

        $activePrescription = Prescription::with(['drugs', 'doctor'])
            ->where('patient_id', $patient->id)
            ->where('hospital_id', $hospitalId)
            ->where('status', 'active')
            ->first();

        // تنسيق تاريخ الميلاد بشكل موحد Y/m/d
        $birthFormatted = $patient->birth_date
            ? Carbon::parse($patient->birth_date)->format('Y/m/d')
            : null;

        $data = [
            'fileNumber' => $patient->id,
            'name'       => $patient->full_name,
            'nationalId' => $patient->national_id,
            'birth'      => $birthFormatted ?? 'غير محدد',
            'phone'      => $patient->phone,
            'lastUpdated'=> $patient->updated_at->toIso8601String(),
            'medications' => $activePrescription ? $activePrescription->drugs->map(function($drug) use ($activePrescription) {
                // استخدام created_at من pivot كتاريخ الإسناد، أو start_date من prescription
                $assignmentDate = $drug->pivot->created_at 
                    ? $drug->pivot->created_at->format('Y-m-d')
                    : ($activePrescription->start_date ? $activePrescription->start_date->format('Y-m-d') : null);
                
                // اسم المستخدم الذي قام بآخر عملية على هذا الدواء (من audit_log)
                $latestLog = \App\Models\AuditLog::whereIn('table_name', ['prescription_drug', 'prescription_drugs'])
                    ->where('record_id', $drug->pivot->id)
                    ->whereIn('action', ['إضافة دواء', 'تعديل دواء'])
                    ->with('user')
                    ->latest()
                    ->first();
                
                $assignedBy = $latestLog && $latestLog->user 
                    ? $latestLog->user->full_name 
                    : ($activePrescription->doctor ? $activePrescription->doctor->full_name : 'غير محدد');
                
                // الحصول على الكمية الشهرية
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
                    'id'       => $drug->id,
                    'pivot_id' => $drug->pivot->id,
                    'drugName' => $drug->name,
                    'strength' => $drug->strength ?? null,
                    'dosage'   => $dosageText, // الجرعة اليومية: "2 مل يومياً" أو "2 حبة يومياً"
                    'monthlyQuantity' => $monthlyQuantityText, // الكمية الشهرية: "60 مل" أو "60 حبة"
                    'monthlyQuantityNum' => $monthlyQty, // الكمية الشهرية كرقم للعمليات الحسابية
                    'unit' => $unit, // وحدة القياس
                    'assignmentDate' => $assignmentDate,
                    'assignedBy' => $assignedBy,
                    'note'     => $drug->pivot->note
                ];
            }) : [],
        ];

        return $this->sendSuccess($data, 'تم جلب بيانات المريض بنجاح.');
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
