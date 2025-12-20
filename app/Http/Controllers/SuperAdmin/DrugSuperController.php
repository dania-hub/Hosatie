<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use App\Models\Drug;
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
                'generic_name' => 'nullable|string|max:255',
                'strength' => 'nullable|string|max:50',
                'form' => 'nullable|string|max:50',
                'category' => 'nullable|string|max:100',
                'unit' => 'nullable|string|max:50',
                'max_monthly_dose' => 'nullable|integer|min:1',
                'manufacturer' => 'nullable|string|max:255',
                'country' => 'nullable|string|max:100',
                'utilization_type' => 'nullable|string|max:100',
                'warnings' => 'nullable|string',
                'expiry_date' => 'nullable|date',
            ], [
                'name.required' => 'اسم الدواء مطلوب',
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
                'status' => 'متوفر',
                'manufacturer' => $request->manufacturer,
                'country' => $request->country,
                'utilization_type' => $request->utilization_type,
                'warnings' => $request->warnings,
                'expiry_date' => $request->expiry_date,
            ]);

            return $this->sendSuccess([
                'id' => $drug->id,
                'name' => $drug->name,
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
                'generic_name' => 'nullable|string|max:255',
                'strength' => 'nullable|string|max:50',
                'form' => 'nullable|string|max:50',
                'category' => 'nullable|string|max:100',
                'unit' => 'nullable|string|max:50',
                'max_monthly_dose' => 'nullable|integer|min:1',
                'manufacturer' => 'nullable|string|max:255',
                'country' => 'nullable|string|max:100',
                'utilization_type' => 'nullable|string|max:100',
                'warnings' => 'nullable|string',
                'expiry_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
            }

            $drug->update($request->only([
                'name', 'generic_name', 'strength', 'form', 'category', 
                'unit', 'max_monthly_dose', 'manufacturer', 'country',
                'utilization_type', 'warnings', 'expiry_date'
            ]));

            return $this->sendSuccess([
                'id' => $drug->id,
                'name' => $drug->name,
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

            if ($drug->status === 'غير متوفر') {
                return $this->sendError('الدواء متوقف بالفعل', null, 400);
            }

            $drug->update(['status' => 'غير متوفر']);

            return $this->sendSuccess([
                'id' => $drug->id,
                'name' => $drug->name,
                'status' => 'غير متوفر',
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

            if ($drug->status === 'متوفر') {
                return $this->sendError('الدواء مفعّل بالفعل', null, 400);
            }

            $drug->update(['status' => 'متوفر']);

            return $this->sendSuccess([
                'id' => $drug->id,
                'name' => $drug->name,
                'status' => 'متوفر',
            ], 'تم إعادة تفعيل الدواء بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Drug Reactivate Error');
        }
    }
}
