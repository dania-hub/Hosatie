<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use App\Models\Hospital;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HospitalSuperController extends BaseApiController
{
    /**
     * FR-86: عرض قائمة المؤسسات الصحية
     * GET /api/super-admin/hospitals
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $query = Hospital::with(['supplier', 'admin']);

            // البحث
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('city', 'like', "%{$search}%");
                });
            }

            // التصفية حسب الحالة
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // التصفية حسب النوع
            if ($request->has('type')) {
                $query->where('type', $request->input('type'));
            }

            // التصفية حسب المدينة
            if ($request->has('city')) {
                $query->where('city', $request->input('city'));
            }

            $hospitals = $query->orderBy('name')->get()->map(function ($hospital) {
                return [
                    'id' => $hospital->id,
                    'name' => $hospital->name,
                    'code' => $hospital->code,
                    'type' => $hospital->type,
                    'typeArabic' => $this->translateType($hospital->type),
                    'city' => $hospital->city,
                    'address' => $hospital->address,
                    'phone' => $hospital->phone,
                    'status' => $hospital->status,
                    'statusArabic' => $hospital->status === 'active' ? 'نشط' : 'غير نشط',
                    'supplier' => $hospital->supplier ? [
                        'id' => $hospital->supplier->id,
                        'name' => $hospital->supplier->name,
                        'code' => $hospital->supplier->code,
                        'phone' => $hospital->supplier->phone,
                        'address' => $hospital->supplier->address,
                        'city' => $hospital->supplier->city,
                    ] : null,
                    'admin' => $hospital->admin ? [
                        'id' => $hospital->admin->id,
                        'name' => $hospital->admin->full_name,
                        'email' => $hospital->admin->email,
                        'phone' => $hospital->admin->phone,
                    ] : null,
                    'createdAt' => optional($hospital->created_at)->format('Y-m-d'),
                ];
            });

            return $this->sendSuccess($hospitals, 'تم جلب قائمة المؤسسات الصحية بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Hospitals Index Error');
        }
    }

    /**
     * FR-85: إضافة مؤسسة صحية جديدة
     * POST /api/super-admin/hospitals
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
                'code' => 'required|string|max:50|unique:hospitals,code',
                'type' => 'required|in:hospital,health_center,clinic',
                'city' => 'required|in:طرابلس,بنغازي',
                'address' => 'nullable|string|max:500',
                'phone' => [
                    'nullable',
                    'regex:/^(021|092|091|093|094)\d{7}$/',
                ],
                'supplier_id' => 'nullable|exists:suppliers,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
            }

            // التحقق من وجود رقم الهاتف في hospitals و users
            if ($request->has('phone') && $request->phone) {
                $existsInHospitals = Hospital::where('phone', $request->phone)->exists();
                $existsInUsers = User::where('phone', $request->phone)->exists();
                if ($existsInHospitals || $existsInUsers) {
                    return $this->sendError('بيانات غير صحيحة', ['phone' => ['رقم الهاتف موجود بالفعل في النظام']], 422);
                }
            }

            DB::beginTransaction();

            // إنشاء المؤسسة
            $hospital = Hospital::create([
                'name' => $request->name,
                'code' => $request->code,
                'type' => $request->type,
                'city' => $request->city,
                'address' => $request->address,
                'phone' => $request->phone,
                'supplier_id' => $request->supplier_id,
                'status' => 'active',
            ]);

            // إنشاء مستودع الأدوية الرئيسي تلقائياً
            Warehouse::create([
                'hospital_id' => $hospital->id,
                'name' => 'مستودع الأدوية الرئيسي ل ' . $hospital->name,
                'status' => 'active',
            ]);

            // إنشاء الصيدلية الرئيسية تلقائياً
            Pharmacy::create([
                'hospital_id' => $hospital->id,
                'name' => 'صيدلية ' . $hospital->name . ' الرئيسية',
                'status' => 'active',
            ]);

            DB::commit();

            return $this->sendSuccess([
                'id' => $hospital->id,
                'name' => $hospital->name,
                'code' => $hospital->code,
            ], 'تم إضافة المؤسسة الصحية بنجاح', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->handleException($e, 'Super Admin Hospital Store Error');
        }
    }

    /**
     * FR-87: تعديل بيانات مؤسسة صحية
     * PUT /api/super-admin/hospitals/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $hospital = Hospital::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'code' => 'sometimes|required|string|max:50|unique:hospitals,code,' . $id,
                'type' => 'sometimes|required|in:hospital,health_center,clinic',
                'city' => 'sometimes|required|in:طرابلس,بنغازي',
                'address' => 'nullable|string|max:500',
                'phone' => [
                    'nullable',
                    'regex:/^(021|092|091|093|094)\d{7}$/',
                ],
                'supplier_id' => 'nullable|exists:suppliers,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
            }

            // التحقق من وجود رقم الهاتف في hospitals و users (تجاهل المستشفى الحالي)
            if ($request->has('phone') && $request->phone && $hospital->phone !== $request->phone) {
                $existsInHospitals = Hospital::where('phone', $request->phone)
                    ->where('id', '!=', $id)
                    ->exists();
                $existsInUsers = User::where('phone', $request->phone)->exists();
                if ($existsInHospitals || $existsInUsers) {
                    return $this->sendError('بيانات غير صحيحة', ['phone' => ['رقم الهاتف موجود بالفعل في النظام']], 422);
                }
            }

            $hospital->update($request->only([
                'name', 'code', 'type', 'city', 'address', 'phone', 'supplier_id'
            ]));

            return $this->sendSuccess([
                'id' => $hospital->id,
                'name' => $hospital->name,
            ], 'تم تعديل بيانات المؤسسة بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Hospital Update Error');
        }
    }

    /**
     * FR-88: تعطيل مؤسسة صحية
     * PATCH /api/super-admin/hospitals/{id}/deactivate
     */
    public function deactivate(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $hospital = Hospital::findOrFail($id);

            if ($hospital->status === 'inactive') {
                return $this->sendError('المؤسسة معطلة بالفعل', null, 400);
            }

            $hospital->update(['status' => 'inactive']);

            return $this->sendSuccess([
                'id' => $hospital->id,
                'name' => $hospital->name,
                'status' => 'inactive',
            ], 'تم تعطيل المؤسسة الصحية بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Hospital Deactivate Error');
        }
    }

    /**
     * FR-89: تفعيل مؤسسة صحية
     * PATCH /api/super-admin/hospitals/{id}/activate
     */
    public function activate(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $hospital = Hospital::findOrFail($id);

            if ($hospital->status === 'active') {
                return $this->sendError('المؤسسة مفعلة بالفعل', null, 400);
            }

            $hospital->update(['status' => 'active']);

            return $this->sendSuccess([
                'id' => $hospital->id,
                'name' => $hospital->name,
                'status' => 'active',
            ], 'تم تفعيل المؤسسة الصحية بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Hospital Activate Error');
        }
    }

    /**
     * التحقق من وجود رقم الهاتف
     * GET /api/super-admin/hospitals/check-phone/{phone}
     */
    public function checkPhone(Request $request, $phone)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            // التحقق من التنسيق
            if (!preg_match('/^(021|092|091|093|094)\d{7}$/', $phone)) {
                return $this->sendError('تنسيق رقم الهاتف غير صحيح', null, 422);
            }

            // التحقق من وجود الرقم في hospitals و users
            $existsInHospitals = Hospital::where('phone', $phone)->exists();
            $existsInUsers = User::where('phone', $phone)->exists();
            $exists = $existsInHospitals || $existsInUsers;

            return $this->sendSuccess([
                'exists' => $exists,
                'phone' => $phone
            ], $exists ? 'رقم الهاتف موجود بالفعل' : 'رقم الهاتف متاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Hospital Check Phone Error');
        }
    }

    /**
     * Helper: ترجمة نوع المؤسسة
     */
    private function translateType($type)
    {
        return match($type) {
            'hospital' => 'مستشفى',
            'health_center' => 'مركز صحي',
            'clinic' => 'عيادة',
            default => $type,
        };
    }
}
