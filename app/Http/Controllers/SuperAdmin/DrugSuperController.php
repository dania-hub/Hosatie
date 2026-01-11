<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use App\Models\Drug;
use App\Models\ExternalSupplyRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DrugSuperController extends BaseApiController
{
    /**
     * عرض قائمة الأدوية
     * GET /api/super-admin/drugs
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $query = Drug::query();

            // البحث
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('generic_name', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%");
                });
            }

            // التصفية حسب الفئة
            if ($request->has('category')) {
                $query->where('category', $request->input('category'));
            }

            // التصفية حسب الحالة
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            $drugs = $query->orderBy('name')->get()->map(function ($drug) {
                return [
                    'id' => $drug->id,
                    'name' => $drug->name,
                    'genericName' => $drug->generic_name,
                    'strength' => $drug->strength,
                    'form' => $drug->form,
                    'category' => $drug->category,
                    'unit' => $drug->unit,
                    'maxMonthlyDose' => $drug->max_monthly_dose,
                    'status' => $drug->status,
                    'manufacturer' => $drug->manufacturer,
                    'country' => $drug->country,
                    'utilization_type' => $drug->utilization_type,
                    'warnings' => $drug->warnings,
                    'indications' => $drug->indications,
                    'expiryDate' => $drug->expiry_date,
                    'createdAt' => optional($drug->created_at)->format('Y-m-d'),
                ];
            });

            return $this->sendSuccess($drugs, 'تم جلب قائمة الأدوية بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Drugs Index Error');
        }
    }

    /**
     * GET /api/categories
     */
    public function categories()
    {
        $categories = Drug::select('category')
            ->distinct()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->orderBy('category')
            ->pluck('category');

        return $this->sendSuccess($categories, 'تم جلب الفئات بنجاح');
    }

    /**
     * GET /api/pharmaceutical-forms
     */
    public function forms()
    {
        $forms = Drug::select('form')
            ->distinct()
            ->whereNotNull('form')
            ->where('form', '!=', '')
            ->orderBy('form')
            ->pluck('form');

        return $this->sendSuccess($forms, 'تم جلب الأشكال الصيدلانية بنجاح');
    }

    /**
     * GET /api/countries
     */
    public function countries()
    {
        $countries = Drug::select('country')
            ->distinct()
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->orderBy('country')
            ->pluck('country');

        return $this->sendSuccess($countries, 'تم جلب الدول بنجاح');
    }

    /**
     * FR-90: إضافة دواء جديد إلى القائمة
     * POST /api/super-admin/drugs
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'generic_name' => 'required|string|max:255',
                'strength' => 'required|string|max:50',
                'form' => 'required|string|max:50',
                'category' => 'required|string|max:100',
                'unit' => 'required|string|max:50',
                'max_monthly_dose' => 'required|integer|min:1',
                'status' => 'required|in:متوفر,غير متوفر,تم الصرف',
                'manufacturer' => 'required|string|max:255',
                'country' => 'required|string|max:100',
                'utilization_type' => 'required|string|max:100',
                'warnings' => 'required|string',
                'indications' => 'required|string',
                'expiry_date' => 'required|date',
            ], [
                'name.required' => 'اسم الدواء مطلوب',
                'generic_name.required' => 'الاسم العلمي مطلوب',
                'strength.required' => 'التركيز مطلوب',
                'form.required' => 'الشكل الصيدلاني مطلوب',
                'category.required' => 'الفئة العلاجية مطلوبة',
                'unit.required' => 'الوحدة مطلوبة',
                'max_monthly_dose.required' => 'الجرعة الشهرية القصوى مطلوبة',
                'status.required' => 'الحالة مطلوبة',
                'manufacturer.required' => 'الشركة المصنعة مطلوبة',
                'country.required' => 'الدولة مطلوبة',
                'utilization_type.required' => 'نوع الاستخدام مطلوب',
                'warnings.required' => 'التحذيرات مطلوبة',
                'indications.required' => 'دواعي الاستعمال مطلوبة',
                'expiry_date.required' => 'تاريخ الانتهاء مطلوب',
            ]);

            if ($validator->fails()) {
                return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
            }

            // التحقق من عدم التكرار
            $exists = Drug::where('name', $request->name)
                ->where('strength', $request->strength)
                ->exists();

            if ($exists) {
                return $this->sendError('هذا الدواء بنفس التركيز موجود بالفعل', null, 422);
            }

            $drug = Drug::create([
                'name' => $request->name,
                'generic_name' => $request->generic_name,
                'strength' => $request->strength,
                'form' => $request->form,
                'category' => $request->category,
                'unit' => $request->unit ?? 'قرص',
                'max_monthly_dose' => $request->max_monthly_dose,
                'status' => $request->status ?? 'متوفر',
                'manufacturer' => $request->manufacturer,
                'country' => $request->country,
                'utilization_type' => $request->utilization_type,
                'warnings' => $request->warnings,
                'indications' => $request->indications,
                'contraindications' => $request->contraindications ?? '',
                'expiry_date' => $request->expiry_date,
            ]);

            return $this->sendSuccess([
                'id' => $drug->id,
                'name' => $drug->name,
                'genericName' => $drug->generic_name,
                'strength' => $drug->strength,
                'form' => $drug->form,
                'category' => $drug->category,
                'unit' => $drug->unit,
                'maxMonthlyDose' => $drug->max_monthly_dose,
                'status' => $drug->status,
                'manufacturer' => $drug->manufacturer,
                'country' => $drug->country,
                'utilization_type' => $drug->utilization_type,
                'warnings' => $drug->warnings,
                'indications' => $drug->indications,
                'expiryDate' => $drug->expiry_date,
                'createdAt' => optional($drug->created_at)->format('Y-m-d'),
            ], 'تم إضافة الدواء بنجاح', 201);

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Drug Store Error');
        }
    }

    /**
     * FR-91: تعديل بيانات دواء موجود
     * PUT /api/super-admin/drugs/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $drug = Drug::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'generic_name' => 'sometimes|required|string|max:255',
                'strength' => 'sometimes|required|string|max:50',
                'form' => 'sometimes|required|string|max:50',
                'category' => 'sometimes|required|string|max:100',
                'unit' => 'sometimes|required|string|max:50',
                'max_monthly_dose' => 'sometimes|required|integer|min:1',
                'status' => 'sometimes|required|in:متوفر,غير متوفر,تم الصرف',
                'manufacturer' => 'sometimes|required|string|max:255',
                'country' => 'sometimes|required|string|max:100',
                'utilization_type' => 'sometimes|required|string|max:100',
                'warnings' => 'sometimes|required|string',
                'indications' => 'sometimes|required|string',
                'expiry_date' => 'sometimes|required|date',
            ]);

            if ($validator->fails()) {
                return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
            }

            $drug->update($request->only([
                'name', 'generic_name', 'strength', 'form', 'category', 
                'unit', 'max_monthly_dose', 'status', 'manufacturer', 'country',
                'utilization_type', 'warnings', 'indications', 'expiry_date'
            ]));

            return $this->sendSuccess([
                'id' => $drug->id,
                'name' => $drug->name,
                'genericName' => $drug->generic_name,
                'strength' => $drug->strength,
                'form' => $drug->form,
                'category' => $drug->category,
                'unit' => $drug->unit,
                'maxMonthlyDose' => $drug->max_monthly_dose,
                'status' => $drug->status,
                'manufacturer' => $drug->manufacturer,
                'country' => $drug->country,
                'utilization_type' => $drug->utilization_type,
                'warnings' => $drug->warnings,
                'indications' => $drug->indications,
                'expiryDate' => $drug->expiry_date,
                'createdAt' => optional($drug->created_at)->format('Y-m-d'),
            ], 'تم تعديل بيانات الدواء بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Drug Update Error');
        }
    }

    /**
     * FR-92: إيقاف دعم دواء
     * PATCH /api/super-admin/drugs/{id}/discontinue
     */
    public function discontinue(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $drug = Drug::findOrFail($id);

            if ($drug->status === Drug::STATUS_UNAVAILABLE || $drug->status === Drug::STATUS_ARCHIVED) {
                return $this->sendError('الدواء متوقف أو مؤرشف بالفعل', null, 400);
            }

            $policy = $request->input('policy', 'immediate'); // default to immediate (old behavior)

            if ($policy === 'dispense_until_zero') {
                $drug->update(['status' => Drug::STATUS_PHASING_OUT]);
                
                // 1. Trigger role-specific notifications
                $notificationService = app(\App\Services\StaffNotificationService::class);
                $notificationService->notifyDrugPhasingOut($drug);
                
                // 2. Automatically cancel all pending or future ExternalSupplyRequests
                $pendingRequests = ExternalSupplyRequest::whereIn('status', ['pending', 'approved'])
                    ->whereHas('items', function($query) use ($drug) {
                        $query->where('drug_id', $drug->id);
                    })->get();

                foreach ($pendingRequests as $request) {
                    $request->update(['status' => 'cancelled']);
                    // Optionally log this action
                }

                // 3. Notify patients who have active prescriptions for this drug
                try {
                    $patients = \App\Models\User::where('type', 'patient')
                        ->whereHas('prescriptionsAsPatient', function ($query) use ($drug) {
                            $query->where('status', 'active')
                                ->whereHas('drugs', function ($q) use ($drug) {
                                    $q->where('drug_id', $drug->id);
                                });
                        })
                        ->get();

                    if ($patients->isNotEmpty()) {
                        $patientNotificationService = app(\App\Services\PatientNotificationService::class);
                        $patientNotificationService->notifyDrugPhasingOut($drug, $patients);
                    }
                } catch (\Exception $e) {
                    \Log::error('Patient notification failed during phasing out', ['error' => $e->getMessage()]);
                }
                
                return $this->sendSuccess([
                    'id' => $drug->id,
                    'name' => $drug->name,
                    'status' => Drug::STATUS_PHASING_OUT,
                ], 'تم البدء في الإيقاف التدريجي للدواء. سيظل متاحاً للصرف حتى نفاذ الكمية.');
            }

            $drug->update(['status' => Drug::STATUS_UNAVAILABLE]);

            return $this->sendSuccess([
                'id' => $drug->id,
                'name' => $drug->name,
                'status' => Drug::STATUS_UNAVAILABLE,
            ], 'تم إيقاف دعم الدواء بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Drug Discontinue Error');
        }
    }

    /**
     * إعادة تفعيل دواء
     * PATCH /api/super-admin/drugs/{id}/reactivate
     */
    public function reactivate(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $drug = Drug::findOrFail($id);

            if ($drug->status === Drug::STATUS_AVAILABLE) {
                return $this->sendError('الدواء مفعّل بالفعل', null, 400);
            }

            $drug->update(['status' => Drug::STATUS_AVAILABLE]);

            // إرسال إشعارات لجميع الجهات المعنية
            try {
                $notificationService = app(\App\Services\StaffNotificationService::class);
                $notificationService->notifyDrugReactivated($drug);
            } catch (\Exception $e) {
                \Log::error('Drug reactivation notification failed', ['error' => $e->getMessage()]);
            }

            return $this->sendSuccess([
                'id' => $drug->id,
                'name' => $drug->name,
                'status' => Drug::STATUS_AVAILABLE,
            ], 'تم إعادة تفعيل الدواء بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Drug Reactivate Error');
        }
    }
}
