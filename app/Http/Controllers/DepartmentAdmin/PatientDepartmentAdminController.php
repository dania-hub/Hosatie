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
use Carbon\Carbon;
use App\Services\ResalaService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Observers\PrescriptionDrugObserver;
use App\Services\PatientNotificationService;

class PatientDepartmentAdminController extends BaseApiController
{
    /**
     * GET /api/department-admin/patients
     * List all patients (for the main table)
     */
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
            
            // Find ALL Active Prescriptions for this Patient (from all hospitals)
            $activePrescriptions = Prescription::with(['drugs', 'doctor'])
                ->where('patient_id', $patient->id)
                ->where('status', 'active')
                ->get();

            // Aggregate medications from all active prescriptions
            $allMedications = collect();
            foreach ($activePrescriptions as $prescription) {
                foreach ($prescription->drugs as $drug) {
                    $assignmentDate = $drug->pivot->created_at 
                        ? $drug->pivot->created_at->format('Y-m-d')
                        : ($prescription->start_date ? (is_object($prescription->start_date) ? $prescription->start_date->format('Y-m-d') : $prescription->start_date) : null);

                    $latestLog = \App\Models\AuditLog::whereIn('table_name', ['prescription_drug', 'prescription_drugs'])
                        ->where('record_id', $drug->pivot->id)
                        ->whereIn('action', ['إضافة دواء', 'تعديل دواء'])
                        ->with('user')
                        ->latest()
                        ->first();

                    $assignedBy = $latestLog && $latestLog->user 
                        ? $latestLog->user->full_name 
                        : ($prescription->doctor ? $prescription->doctor->full_name : 'غير محدد');

                    $monthlyQty = (int)($drug->pivot->monthly_quantity ?? 0);
                    $dailyQty = (int)($drug->pivot->daily_quantity ?? 0);
                    $unit = $this->getDrugUnit($drug);
                    // استخدام daily_quantity مباشرة للجرعة اليومية
                    $dosageText = $dailyQty > 0 
                        ? $dailyQty . ' ' . $unit . ' يومياً'
                        : 'غير محدد';
                    $monthlyQuantityText = $monthlyQty > 0 ? $monthlyQty . ' ' . $unit : 'غير محدد';

                    $allMedications->push([
                        'id' => $drug->id,
                        'pivot_id' => $drug->pivot->id ?? null,
                        'drugName' => $drug->name,
                        'strength' => $drug->strength ?? null,
                        'dosage' => $dosageText,
                        'dailyQuantity' => $dailyQty,
                        'monthlyQuantity' => $monthlyQuantityText,
                        'monthlyQuantityNum' => $monthlyQty,
                        'unit' => $unit,
                        'assignmentDate' => $assignmentDate,
                        'assignedBy' => $assignedBy,
                        'note' => $drug->pivot->note ?? null,
                        'maxMonthlyDose' => $drug->max_monthly_dose ?? null, // الحد الأقصى الشهري
                    ]);
                }
            }

            return [
                'fileNumber' => $patient->id,
                'name'       => $patient->full_name,
                'nationalId' => $patient->national_id,
                // تاريخ الميلاد بصيغة موحدة لواجهة الطبيب
                'birth'      => $birthFormatted ?? 'غير محدد',
                'phone'      => $patient->phone,
                'lastUpdated'=> $patient->updated_at->toIso8601String(),

                'medications' => $allMedications->toArray(),

                'hasPrescription' => $activePrescriptions->contains('hospital_id', $hospitalId)
            ];
        });

        return $this->sendSuccess($patients, 'تم جلب بيانات المرضى بنجاح.');
    }

    /**
     * GET /api/department-admin/patients/{id}
     * Get single patient details + medications
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

        // Get ALL active prescriptions for this patient (from any hospital) and include doctor
        $activePrescriptions = Prescription::with(['drugs', 'doctor'])
            ->where('patient_id', $patient->id)
            ->where('status', 'active')
            ->get();

        // تنسيق تاريخ الميلاد بشكل موحد Y/m/d
        $birthFormatted = $patient->birth_date
            ? Carbon::parse($patient->birth_date)->format('Y/m/d')
            : null;

        // Collect all medications from all active prescriptions
        $allMedications = collect();
        foreach ($activePrescriptions as $prescription) {
            foreach ($prescription->drugs as $drug) {
                $assignmentDate = $drug->pivot->created_at 
                    ? $drug->pivot->created_at->format('Y-m-d')
                    : ($prescription->start_date ? (is_object($prescription->start_date) ? $prescription->start_date->format('Y-m-d') : $prescription->start_date) : null);

                $latestLog = \App\Models\AuditLog::whereIn('table_name', ['prescription_drug', 'prescription_drugs'])
                    ->where('record_id', $drug->pivot->id)
                    ->whereIn('action', ['إضافة دواء', 'تعديل دواء'])
                    ->with('user')
                    ->latest()
                    ->first();

                $assignedBy = $latestLog && $latestLog->user 
                    ? $latestLog->user->full_name 
                    : ($prescription->doctor ? $prescription->doctor->full_name : 'غير محدد');

                $monthlyQty = (int)($drug->pivot->monthly_quantity ?? 0);
                $dailyQty = (int)($drug->pivot->daily_quantity ?? 0);
                $unit = $this->getDrugUnit($drug);
                // استخدام daily_quantity مباشرة للجرعة اليومية
                $dosageText = $dailyQty > 0 
                    ? $dailyQty . ' ' . $unit . ' يومياً'
                    : 'غير محدد';
                $monthlyQuantityText = $monthlyQty > 0 ? $monthlyQty . ' ' . $unit : 'غير محدد';

                $allMedications->push([
                    'id' => $drug->id,
                    'pivot_id' => $drug->pivot->id ?? null,
                    'drugName' => $drug->name,
                    'strength' => $drug->strength ?? null,
                    'dosage' => $dosageText,
                    'dailyQuantity' => $dailyQty,
                    'monthlyQuantity' => $monthlyQuantityText,
                    'monthlyQuantityNum' => $monthlyQty,
                    'unit' => $unit,
                    'assignmentDate' => $assignmentDate,
                    'assignedBy' => $assignedBy,
                    'note' => $drug->pivot->note ?? null,
                    'maxMonthlyDose' => $drug->max_monthly_dose ?? null, // الحد الأقصى الشهري
                ]);
            }
        }

        $data = [
            'fileNumber' => $patient->id,
            'name'       => $patient->full_name,
            'nationalId' => $patient->national_id,
            'birth'      => $birthFormatted ?? 'غير محدد',
            'phone'      => $patient->phone,
            'lastUpdated'=> $patient->updated_at->toIso8601String(),
            'medications' => $allMedications->toArray(),
        ];

        return $this->sendSuccess($data, 'تم جلب بيانات المريض.');
    }

    /**
     * POST /api/department-admin/patients/{id}/medications
     * Add new medications (like doctor/patients)
     */
    public function store(Request $request, $id, ResalaService $resalaService, PatientNotificationService $notificationService)
    {
        try {
            $request->validate([
                'medications' => 'required|array',
                'medications.*.drug_id' => 'required|exists:drugs,id',
                'medications.*.quantity' => 'required|integer|min:1',
            ]);
            
            DB::beginTransaction();

            $hospitalId = $request->user()->hospital_id;
            $currentUserId = $request->user()->id;

            // التأكد من أن المستخدم لديه hospital_id
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            $patient = User::where('type', 'patient')
                ->where('hospital_id', $hospitalId)
                ->where('id', $id)
                ->first();
            
            if (!$patient) {
                return $this->sendError('المريض غير موجود أو غير مرتبط بنفس المستشفى.', [], 404);
            }

            // إرسال رسالة التفعيل إذا كان بانتظار التفعيل (دون تغيير الحالة)
            if ($patient->status === 'pending_activation') {
                $plainPassword = \Illuminate\Support\Str::random(8); // إنشاء كلمة مرور عشوائية
                
                $patient->update([
                    'password' => \Illuminate\Support\Facades\Hash::make($plainPassword),
                    // 'status'   => 'active' // تم التعطيل بناءً على طلب المستخدم
                ]);

                $resalaService->sendActivationSms($patient->phone, $plainPassword);
                
                Log::info('✅ Activation SMS sent by Department Head (status remains pending_activation)', ['id' => $patient->id]);
            }

            // 1. Find existing active prescription (from any hospital)
            $prescription = Prescription::where('patient_id', $patient->id)
                ->where('status', 'active')
                ->first();

            // 2. If NO prescription, Create one
            if (!$prescription) {
                $prescription = Prescription::create([
                    'patient_id' => $patient->id,
                    'hospital_id' => $hospitalId,
                    'doctor_id' => $currentUserId,
                    'start_date' => now(),
                    'status' => 'active',
                ]);
            }

            // 3. Add Drugs (only if they don't exist, or update quantity if exists)
            $createdDrugs = [];
            $updatedDrugs = [];

            // منع Observer من إرسال إشعار مكرر (إذا كان يفعل ذلك)
            PrescriptionDrugObserver::$skipNotification = true;

            foreach ($request->medications as $med) {
                $existingPd = PrescriptionDrug::where('prescription_id', $prescription->id)
                            ->where('drug_id', $med['drug_id'])
                            ->first();
                
                if (!$existingPd) {
                    // إضافة دواء جديد فقط
                    $monthlyQuantity = $med['quantity'];
                    $dailyQuantity = isset($med['daily_quantity']) && $med['daily_quantity'] !== null 
                        ? (int)$med['daily_quantity'] 
                        : null;
                    
                    $newPd = PrescriptionDrug::create([
                        'prescription_id' => $prescription->id,
                        'drug_id'         => $med['drug_id'],
                        'monthly_quantity'=> $monthlyQuantity,
                        'daily_quantity'  => $dailyQuantity,
                    ]);
                    $newPd->load('drug');
                    $createdDrugs[] = $newPd;
                } else {
                    // إذا كان الدواء موجوداً، قم بتحديث الكمية فقط
                    $oldQty = $existingPd->monthly_quantity;
                    $existingPd->monthly_quantity = $med['quantity'];
                    if (isset($med['daily_quantity']) && $med['daily_quantity'] !== null) {
                        $existingPd->daily_quantity = (int)$med['daily_quantity'];
                    }
                    if ($existingPd->isDirty()) {
                        $existingPd->save();
                        $existingPd->load('drug');
                        $updatedDrugs[] = $existingPd;
                    }
                }
            }

            DB::commit();

            PrescriptionDrugObserver::$skipNotification = false;

            // Trigger Push Notifications
            foreach ($createdDrugs as $pd) {
                $notificationService->notifyDrugAssigned($patient, $prescription, $pd->drug);
            }
            foreach ($updatedDrugs as $pd) {
                $notificationService->notifyDrugUpdated($patient, $prescription, $pd->drug);
            }

            return $this->sendSuccess([], 'تم إضافة الأدوية بنجاح.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return $this->sendError('خطأ في التحقق من البيانات.', $e->errors(), 422);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            \Log::error('Error adding medications: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return $this->sendError('حدث خطأ أثناء حفظ البيانات.', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
        }
    }

    /**
     * PUT /api/department-admin/patients/{id}/medications
     * Sync medications list (Add/Remove/Update all at once)
     */
    public function updateMedications(Request $request, $id, ResalaService $resalaService, PatientNotificationService $notificationService)
    {
        $request->validate([
            'medications' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $hospitalId = $request->user()->hospital_id;
            $currentUserId = $request->user()->id; // المستخدم الحالي (Department Admin أو Doctor)

            // التأكد من أن المستخدم لديه hospital_id
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            $patient = User::where('type', 'patient')
                ->where('hospital_id', $hospitalId)
                ->where('id', $id)
                ->first();
            
            if (!$patient) {
                return $this->sendError('المريض غير موجود أو غير مرتبط بنفس المستشفى.', [], 404);
            }

            // إرسال رسالة التفعيل إذا كان بانتظار التفعيل (دون تغيير الحالة)
            if ($patient->status === 'pending_activation') {
                $plainPassword = \Illuminate\Support\Str::random(8); // إنشاء كلمة مرور عشوائية
                
                $patient->update([
                    'password' => \Illuminate\Support\Facades\Hash::make($plainPassword),
                    // 'status'   => 'active' // تم التعطيل بناءً على طلب المستخدم
                ]);

                $resalaService->sendActivationSms($patient->phone, $plainPassword);
                
                Log::info('✅ Activation SMS sent by Department Head sync (status remains pending_activation)', ['id' => $patient->id]);
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

            // الحصول على الأدوية الموجودة حالياً
            $existingDrugs = PrescriptionDrug::where('prescription_id', $prescription->id)
                ->pluck('drug_id')
                ->toArray();

            $createdRecords = [];
            $updatedRecords = [];
            $processedDrugIds = [];
            
            // إضافة أو تحديث الأدوية
            foreach ($request->medications as $med) {
                // البحث عن الدواء بواسطة الاسم أو ID
                $drug = null;
                
                // أولاً: البحث بواسطة ID
                if (!empty($med['drugId']) || !empty($med['id'])) {
                    $drugId = $med['drugId'] ?? $med['id'];
                    $drug = Drug::find($drugId);
                }
                
                // ثانياً: البحث بواسطة الاسم
                if (!$drug && !empty($med['drugName'])) {
                    $drug = Drug::where('name', trim($med['drugName']))->first();
                }
                
                // ثالثاً: إذا لم يوجد الدواء وكان هناك اسم، إنشاء واحد جديد
                if (!$drug) {
                    $drugName = trim($med['drugName'] ?? $med['name'] ?? '');
                    if (empty($drugName)) {
                        // تخطي هذا الدواء إذا لم يكن هناك اسم أو ID
                        \Log::warning('Skipping medication without drugId or drugName', ['med' => $med]);
                        continue;
                    }
                    
                    try {
                        $drug = Drug::create([
                            'name' => $drugName,
                            'generic_name' => $drugName,
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
                    } catch (\Exception $e) {
                        \Log::error('Error creating drug: ' . $e->getMessage(), ['drugName' => $drugName]);
                        throw new \Exception('فشل في إنشاء الدواء: ' . $drugName . ' - ' . $e->getMessage());
                    }
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

                    // التحقق من وجود الدواء في الوصفة
                    $existingPd = PrescriptionDrug::where('prescription_id', $prescription->id)
                        ->where('drug_id', $drug->id)
                        ->first();

                    if ($existingPd) {
                        // تحديث كمية الدواء الموجود فقط إذا تغيرت القيم فعلياً
                        $needsUpdate = false;
                        
                        if ($existingPd->monthly_quantity != $monthlyQuantity) {
                            $existingPd->monthly_quantity = $monthlyQuantity;
                            $needsUpdate = true;
                        }
                        
                        $newDailyQuantity = isset($med['daily_quantity']) && $med['daily_quantity'] !== null 
                            ? (int)$med['daily_quantity'] 
                            : null;
                        if ($existingPd->daily_quantity != $newDailyQuantity) {
                            $existingPd->daily_quantity = $newDailyQuantity;
                            $needsUpdate = true;
                        }
                        
                        // حفظ فقط إذا كان هناك تغيير فعلي
                        if ($needsUpdate) {
                            $existingPd->save();
                            $existingPd->load('drug');
                            $updatedRecords[] = $existingPd;
                        }
                    } else {
                        // إضافة دواء جديد فقط
                        $pd = PrescriptionDrug::create([
                            'prescription_id' => $prescription->id,
                            'drug_id' => $drug->id,
                            'monthly_quantity' => $monthlyQuantity,
                            'daily_quantity' => isset($med['daily_quantity']) && $med['daily_quantity'] !== null 
                                ? (int)$med['daily_quantity'] 
                                : null,
                        ]);
                        $pd->load('drug');
                        $createdRecords[] = $pd;
                    }

                    $processedDrugIds[] = $drug->id;
                }
            }

            // حذف الأدوية التي لم تعد موجودة في القائمة الجديدة
            $drugsToDelete = array_diff($existingDrugs, $processedDrugIds);
            if (!empty($drugsToDelete)) {
                $deletedDrugRecords = PrescriptionDrug::with('drug')
                    ->where('prescription_id', $prescription->id)
                    ->whereIn('drug_id', $drugsToDelete)
                    ->get();
                
                foreach ($deletedDrugRecords as $dr) {
                    $dr->delete();
                }
            }

            DB::commit();

            // Trigger Push Notifications
            foreach ($createdRecords as $pd) {
                $notificationService->notifyDrugAssigned($patient, $prescription, $pd->drug);
            }
            foreach ($updatedRecords as $pd) {
                $notificationService->notifyDrugUpdated($patient, $prescription, $pd->drug);
            }
            if (!empty($drugsToDelete)) {
                foreach ($deletedDrugRecords as $dr) {
                    $notificationService->notifyDrugDeleted($patient, $prescription, $dr->drug);
                }
            }

            // إعادة جلب البيانات المحدثة
            return $this->show($request, $id);

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
            \Log::error('Error updating medications: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'patient_id' => $id
            ]);
            return $this->sendError('حدث خطأ أثناء حفظ البيانات.', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * PUT /api/department-admin/patients/{id}/medications/{pivotId}
     * Update single medication quantity
     */
    public function update(Request $request, $patientId, $pivotId, PatientNotificationService $notificationService)
    {
        $request->validate(['dosage' => 'required|integer|min:1']);
        $hospitalId = $request->user()->hospital_id;

        $item = PrescriptionDrug::with(['prescription.patient', 'drug'])->where('id', $pivotId)->first();
        if (!$item) return $this->sendError('الدواء غير موجود في السجل.', [], 404);

        // تأكد أن المريض موجود وفي نفس المستشفى
        $patient = User::where('type', 'patient')
            ->where('hospital_id', $hospitalId)
            ->where('id', $patientId)
            ->first();
        if (!$patient) return $this->sendError('المريض غير موجود أو غير مرتبط بنفس المستشفى.', [], 404);

        $prescription = $item->prescription;

        $item->monthly_quantity = $request->dosage;
        
        // تحديث الجرعة اليومية أيضاً إذا تم إرسالها
        if ($request->has('daily_quantity')) {
            $item->daily_quantity = $request->daily_quantity;
        } else {
            // حساب الجرعة اليومية من الشهرية (الشهرية / 30)
            $item->daily_quantity = round($request->dosage / 30);
        }
        
        $item->save();

        // Trigger Push Notification
        $notificationService->notifyDrugUpdated($patient, $prescription, $item->drug);

        return $this->sendSuccess($item, 'تم تحديث جرعة الدواء بنجاح.');
    }

    /**
     * DELETE /api/department-admin/patients/{id}/medications/{pivotId}
     * Delete single medication
     */
    public function destroy(Request $request, $patientId, $pivotId, PatientNotificationService $notificationService)
    {
        $hospitalId = $request->user()->hospital_id;

        $item = PrescriptionDrug::with(['prescription.patient', 'drug'])->where('id', $pivotId)->first();
        if (!$item) return $this->sendError('الدواء غير موجود في السجل.', [], 404);

        // تأكد أن المريض موجود وفي نفس المستشفى
        $patient = User::where('type', 'patient')
            ->where('hospital_id', $hospitalId)
            ->where('id', $patientId)
            ->first();
        if (!$patient) return $this->sendError('المريض غير موجود أو غير مرتبط بنفس المستشفى.', [], 404);

        $prescription = $item->prescription;
        $drug = $item->drug;

        DB::beginTransaction();
        try {
            // حفظ معلومات الدواء قبل الحذف
            $prescriptionId = $item->prescription_id;
            $drugId = $item->drug_id;

            // إزالة الـ foreign key constraint مؤقتاً لمنع حذف سجلات الصرف
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                
                // حذف الدواء
                $item->delete();
                
                // إعادة تفعيل الـ foreign key constraint
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } catch (\Exception $e) {
                // في حالة الخطأ، إعادة تفعيل الـ foreign key constraint
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                throw $e;
            }

            // تحديث updated_at للمريض ليظهر في بداية القائمة
            $patient->touch();

            // Trigger Push Notification
            $notificationService->notifyDrugDeleted($patient, $prescription, $drug);

            // التحقق من أن الروشتة ليست فارغة، وإذا كانت فارغة يمكن حذفها
            if ($prescription->drugs()->count() == 0) {
                $prescription->delete();
            }

            DB::commit();
            
            return $this->sendSuccess([], 'تم حذف الدواء بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('حدث خطأ أثناء حذف الدواء.', [
                'error' => config('app.debug') ? $e->getMessage() : 'خطأ في الحذف'
            ], 500);
        }
    }

    /**
     * GET /api/department-admin/patients/{id}/dispensation-history
     */
    public function dispensationHistory($id)
    {
        $history = Dispensing::with(['drug', 'pharmacist'])
            ->where('patient_id', $id)
            ->latest('created_at')
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'drugName' => $record->drug ? $record->drug->name : 'غير معروف',
                    'quantity' => $record->quantity_dispensed ?? 0,
                    'date' => $record->created_at ? \Carbon\Carbon::parse($record->created_at)->format('Y/m/d') : '-',
                    'assignedBy' => $record->pharmacist ? ($record->pharmacist->full_name ?? 'غير معروف') : 'غير معروف',
                ];
            });

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
