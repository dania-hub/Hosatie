<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Hospital;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierSuperController extends BaseApiController
{
    /**
     * عرض قائمة الموردين
     * GET /api/super-admin/suppliers
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $query = Supplier::query();

            // البحث
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            // التصفية حسب الحالة
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // التصفية حسب المدينة
            if ($request->has('city')) {
                $query->where('city', $request->input('city'));
            }

            $suppliers = $query->with('admin')->orderBy('name')->get()->map(function ($supplier) {
                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'code' => $supplier->code,
                    'phone' => $supplier->phone,
                    'address' => $supplier->address,
                    'city' => $supplier->city,
                    'status' => $supplier->status,
                    'statusArabic' => $supplier->status === 'active' ? 'نشط' : 'غير نشط',
                    'admin' => $supplier->admin ? [
                        'id' => $supplier->admin->id,
                        'name' => $supplier->admin->full_name,
                        'email' => $supplier->admin->email,
                        'phone' => $supplier->admin->phone,
                    ] : null,
                    'createdAt' => optional($supplier->created_at)->format('Y-m-d'),
                ];
            });

            return $this->sendSuccess($suppliers, 'تم جلب قائمة الموردين بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Suppliers Index Error');
        }
    }

    /**
     * إضافة مورد جديد
     * POST /api/super-admin/suppliers
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
                'code' => 'required|string|max:50|unique:suppliers,code',
                'phone' => [
                    'nullable',
                    'regex:/^(021|092|091|093|094)\d{7}$/',
                ],
                'address' => 'nullable|string|max:500',
                'city' => 'required|in:طرابلس,بنغازي',
                'admin_id' => 'nullable|exists:users,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
            }

            // التحقق من وجود رقم الهاتف في hospitals, suppliers و users
            if ($request->has('phone') && $request->phone) {
                $existsInHospitals = Hospital::where('phone', $request->phone)->exists();
                $existsInSuppliers = Supplier::where('phone', $request->phone)->exists();
                $existsInUsers = User::where('phone', $request->phone)->exists();
                if ($existsInHospitals || $existsInSuppliers || $existsInUsers) {
                    return $this->sendError('بيانات غير صحيحة', ['phone' => ['رقم الهاتف موجود بالفعل في النظام']], 422);
                }
            }

            // التحقق من أن المستخدم المحدد هو supplier_admin
            if ($request->filled('admin_id')) {
                $adminUser = User::find($request->admin_id);
                if (!$adminUser || $adminUser->type !== 'supplier_admin') {
                    return $this->sendError('المستخدم المحدد ليس مسؤول مورد', ['admin_id' => ['المستخدم المحدد ليس مسؤول مورد']], 422);
                }
            }

            $supplier = Supplier::create([
                'name' => $request->name,
                'code' => $request->code,
                'phone' => $request->phone,
                'address' => $request->address,
                'city' => $request->city,
                'status' => 'active',
            ]);

            // ربط المسؤول بالمورد
            if ($request->filled('admin_id')) {
                User::where('id', $request->admin_id)->update(['supplier_id' => $supplier->id]);
            }

            return $this->sendSuccess([
                'id' => $supplier->id,
                'name' => $supplier->name,
                'code' => $supplier->code,
            ], 'تم إضافة المورد بنجاح', 201);

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Supplier Store Error');
        }
    }

    /**
     * تعديل بيانات مورد
     * PUT /api/super-admin/suppliers/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $supplier = Supplier::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'code' => 'sometimes|required|string|max:50|unique:suppliers,code,' . $id,
                'phone' => [
                    'nullable',
                    'regex:/^(021|092|091|093|094)\d{7}$/',
                ],
                'address' => 'nullable|string|max:500',
                'city' => 'sometimes|required|in:طرابلس,بنغازي',
                'admin_id' => 'nullable|exists:users,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
            }

            // التحقق من وجود رقم الهاتف في hospitals, suppliers و users (تجاهل المورد الحالي)
            if ($request->has('phone') && $request->phone && $supplier->phone !== $request->phone) {
                $existsInHospitals = Hospital::where('phone', $request->phone)->exists();
                $existsInSuppliers = Supplier::where('phone', $request->phone)->where('id', '!=', $id)->exists();
                $existsInUsers = User::where('phone', $request->phone)->exists();
                if ($existsInHospitals || $existsInSuppliers || $existsInUsers) {
                    return $this->sendError('بيانات غير صحيحة', ['phone' => ['رقم الهاتف موجود بالفعل في النظام']], 422);
                }
            }

            // التحقق من أن المستخدم المحدد هو supplier_admin
            if ($request->filled('admin_id')) {
                $adminUser = User::find($request->admin_id);
                if (!$adminUser || $adminUser->type !== 'supplier_admin') {
                    return $this->sendError('المستخدم المحدد ليس مسؤول مورد', ['admin_id' => ['المستخدم المحدد ليس مسؤول مورد']], 422);
                }
            }

            $supplier->update($request->only([
                'name', 'code', 'phone', 'address', 'city'
            ]));

            // ربط/فك ربط المسؤول بالمورد
            if ($request->has('admin_id')) {
                // فك ربط المسؤول القديم
                if ($supplier->admin) {
                    User::where('id', $supplier->admin->id)->update(['supplier_id' => null]);
                }
                // ربط المسؤول الجديد
                if ($request->filled('admin_id')) {
                    User::where('id', $request->admin_id)->update(['supplier_id' => $supplier->id]);
                }
            }

            return $this->sendSuccess([
                'id' => $supplier->id,
                'name' => $supplier->name,
            ], 'تم تعديل بيانات المورد بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Supplier Update Error');
        }
    }

    /**
     * تعطيل مورد
     * PATCH /api/super-admin/suppliers/{id}/deactivate
     */
    public function deactivate(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $supplier = Supplier::findOrFail($id);

            if ($supplier->status === 'inactive') {
                return $this->sendError('المورد معطل بالفعل', null, 400);
            }

            $supplier->update(['status' => 'inactive']);

            return $this->sendSuccess([
                'id' => $supplier->id,
                'name' => $supplier->name,
                'status' => 'inactive',
            ], 'تم تعطيل المورد بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Supplier Deactivate Error');
        }
    }

    /**
     * تفعيل مورد
     * PATCH /api/super-admin/suppliers/{id}/activate
     */
    public function activate(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $supplier = Supplier::findOrFail($id);

            if ($supplier->status === 'active') {
                return $this->sendError('المورد مفعل بالفعل', null, 400);
            }

            $supplier->update(['status' => 'active']);

            return $this->sendSuccess([
                'id' => $supplier->id,
                'name' => $supplier->name,
                'status' => 'active',
            ], 'تم تفعيل المورد بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Supplier Activate Error');
        }
    }

    /**
     * التحقق من وجود رقم الهاتف في النظام
     * GET /api/super-admin/suppliers/check-phone/{phone}
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

            $existsInHospitals = Hospital::where('phone', $phone)->exists();
            $existsInSuppliers = Supplier::where('phone', $phone)->exists();
            $existsInUsers = User::where('phone', $phone)->exists();
            $exists = $existsInHospitals || $existsInSuppliers || $existsInUsers;

            return $this->sendSuccess([
                'exists' => $exists,
                'phone' => $phone
            ], $exists ? 'رقم الهاتف موجود بالفعل في النظام' : 'رقم الهاتف متاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Supplier Check Phone Error');
        }
    }
}
