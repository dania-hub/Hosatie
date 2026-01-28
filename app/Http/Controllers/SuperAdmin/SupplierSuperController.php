<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Hospital;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SupplierSuperController extends BaseApiController
{
    /**
     * جلب الكود التالي المقترح للمورد
     * GET /api/super-admin/suppliers/next-code
     */
    public function getNextSupplierCode(Request $request)
    {
        try {
            $user = $request->user();
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $lastId = Supplier::max('id') ?? 0;
            $nextId = $lastId + 1;
            $code = "MED-SUP-" . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            return $this->sendSuccess(['code' => $code], 'تم توليد الكود المقترح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Next Supplier Code Error');
        }
    }

    /**
     * التحقق من وجود اسم المورد أو المستشفى
     * GET /api/super-admin/suppliers/check-name/{name}
     */
    public function checkName(Request $request, $name)
    {
        try {
            $user = $request->user();
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $existsInSuppliers = Supplier::where('name', $name)->exists();
            $existsInHospitals = Hospital::where('name', $name)->exists();
            $exists = $existsInSuppliers || $existsInHospitals;

            return $this->sendSuccess([
                'exists' => $exists,
                'name' => $name
            ], $exists ? 'الاسم موجود بالفعل في النظام' : 'الاسم متاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Supplier Check Name Error');
        }
    }
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

            // التحقق من تكرار الاسم في المستشفيات والموردين
            $nameExistsInSuppliers = Supplier::where('name', $request->name)->exists();
            $nameExistsInHospitals = Hospital::where('name', $request->name)->exists();
            if ($nameExistsInSuppliers || $nameExistsInHospitals) {
                return $this->sendError('بيانات غير صحيحة', ['name' => ['هذا الاسم مستخدم بالفعل لمستشفى أو شركة توريد']], 422);
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

            // توليد كود تلقائي إذا لم يتم إرساله
            $code = $request->code;
            if (!$code) {
                $lastId = Supplier::max('id') ?? 0;
                $nextId = $lastId + 1;
                $code = "MED-SUP-" . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            }

            $supplier = Supplier::create([
                'name' => $request->name,
                'code' => $code,
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

            DB::beginTransaction();

            try {
                $supplier->update($request->only([
                    'name', 'code', 'phone', 'address', 'city'
                ]));

                // ربط/فك ربط المسؤول بالمورد
                if ($request->has('admin_id')) {
                    $adminId = $request->input('admin_id');
                    
                    // إذا تم إرسال null، إزالة المدير الحالي وتعطيله
                    if ($adminId === null || $adminId === '') {
                        // إزالة supplier_id من المدير الحالي إن وجد وتعطيله
                            $supplier->admin->update([
                                'status' => 'inactive' // تعطيل المدير الحالي مع الاحتفاظ بـ supplier_id
                            ]);
                            // حذف جميع tokens للمستخدم
                            $supplier->admin->tokens()->delete();
                    } else {
                        // التحقق من أن المستخدم المحدد هو supplier_admin
                        $newAdmin = User::where('type', 'supplier_admin')
                            ->where('id', $adminId)
                            ->first();
                        
                        if (!$newAdmin) {
                            DB::rollBack();
                            return $this->sendError('المستخدم المحدد ليس مدير مورد', ['admin_id' => ['المستخدم المحدد ليس مدير مورد']], 422);
                        }

                        // إزالة supplier_id من المدير الحالي إن وجد وتعطيله
                            $oldAdmin->update([
                                'status' => 'inactive' // تعطيل الحساب مع الاحتفاظ بـ supplier_id
                            ]);
                            // حذف جميع tokens للمستخدم
                            $oldAdmin->tokens()->delete();

                        // إزالة supplier_id من أي مستخدم آخر مرتبط بنفس المورد وتعطيلهم
                        $otherAdmins = User::where('supplier_id', $supplier->id)
                            ->where('type', 'supplier_admin')
                            ->where('id', '!=', $adminId)
                            ->get();
                        
                        foreach ($otherAdmins as $otherAdmin) {
                            $otherAdmin->update([
                                'status' => 'inactive' // تعطيل الحساب مع الاحتفاظ بـ supplier_id
                            ]);
                            // حذف جميع tokens للمستخدم
                            $otherAdmin->tokens()->delete();
                        }

                        // تعيين المدير الجديد
                        $newAdmin->update(['supplier_id' => $supplier->id]);
                        
                        // تفعيل المدير إذا كان معطلاً
                        if ($newAdmin->status === 'inactive') {
                            $newAdmin->update(['status' => 'active']);
                        }
                    }
                }

                DB::commit();

                return $this->sendSuccess([
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                ], 'تم تعديل بيانات المورد بنجاح');

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
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
    /**
     * التحقق المسبق قبل إيقاف تفعيل مورد
     * GET /api/super-admin/suppliers/{id}/deactivation-data
     */
    public function deactivationData(Request $request, $id)
    {
        try {
            $user = $request->user();
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $supplier = Supplier::findOrFail($id);

            // 1. التحقق من وجود مورد بديل (أهم شرط)
            $otherActiveSuppliersCount = Supplier::where('id', '!=', $id)
                ->where('status', 'active')
                ->count();
            
            if ($otherActiveSuppliersCount === 0) {
                return $this->sendError('لا يمكن إيقاف هذا المورد لأنه المورد النشط الوحيد في النظام. يجب إضافة وتفعيل مورد بديل أولاً.', null, 422);
            }

            // 2. التحقق من العمليات النشطة (Blockers)
            
            // طلبات التوريد الخارجية النشطة للمورد
            $activeExternalRequests = \App\Models\ExternalSupplyRequest::where('supplier_id', $id)
                ->whereIn('status', ['pending', 'approved', 'partially_approved', 'fulfilled'])
                ->get();
            
            // طلبات التوريد الداخلية النشطة للمورد
            $activeInternalRequests = \App\Models\InternalSupplyRequest::where('supplier_id', $id)
                ->whereIn('status', ['pending', 'approved'])
                ->get();

            $blockers = [];
            foreach ($activeExternalRequests as $req) {
                $blockers[] = "طلب توريد خارجي رقم EXT-{$req->id}";
            }
            foreach ($activeInternalRequests as $req) {
                $blockers[] = "طلب توريد داخلي رقم INT-{$req->id}";
            }

            // 3. التحقق من الكيانات المرتبطة (Counts)
            
            // المستشفيات التابعة
            $hospitalsCount = Hospital::where('supplier_id', $id)->count();
            
            // رصيد المخزن الحالي (عدد الأدوية ذات الرصيد > 0)
            $inventoryItemsCount = \App\Models\Inventory::where('supplier_id', $id)
                ->where('current_quantity', '>', 0)
                ->count();
            
            // 4. قائمة الموردين البدلاء
            $otherSuppliers = Supplier::where('id', '!=', $id)
                ->where('status', 'active')
                ->get(['id', 'name', 'city']);

            // 5. مدير المورد الحالي
            $supplierAdmin = User::where('supplier_id', $id)->where('type', 'supplier_admin')->first();

            return $this->sendSuccess([
                'supplier' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'city' => $supplier->city,
                ],
                'blockers' => $blockers,
                'hasBlockers' => count($blockers) > 0,
                'counts' => [
                    'hospitals' => $hospitalsCount,
                    'inventoryItems' => $inventoryItemsCount,
                ],
                'alternativeSuppliers' => $otherSuppliers,
                'manager' => $supplierAdmin ? [
                    'id' => $supplierAdmin->id,
                    'name' => $supplierAdmin->full_name,
                ] : null
            ], 'تم جلب بيانات فحص الإيقاف بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Supplier Deactivation Data Error');
        }
    }

    /**
     * FR-88: تعطيل مورد (مع المعالج التفاعلي)
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

            // التحقق من وجود مورد بديل أولاً
            $otherActiveSuppliersCount = Supplier::where('id', '!=', $id)
                ->where('status', 'active')
                ->count();
            
            if ($otherActiveSuppliersCount === 0) {
                return $this->sendError('لا يمكن إيقاف هذا المورد لأنه المورد النشط الوحيد في النظام.', null, 422);
            }

            // التحقق من العمليات النشطة
            $hasExternal = \App\Models\ExternalSupplyRequest::where('supplier_id', $id)
                ->whereIn('status', ['pending', 'approved', 'partially_approved', 'fulfilled'])
                ->exists();
            $hasInternal = \App\Models\InternalSupplyRequest::where('supplier_id', $id)
                ->whereIn('status', ['pending', 'approved'])
                ->exists();

            if ($hasExternal || $hasInternal) {
                return $this->sendError('لا يمكن إيقاف المورد. يوجد طلبات توريد نشطة. يجب إكمالها أو إلغاؤها أولاً.', null, 422);
            }

            // التحقق من صحة البيانات المرسلة من المعالج
            $validator = Validator::make($request->all(), [
                'target_supplier_id' => 'required|exists:suppliers,id',
                'transfer_inventory' => 'required|boolean',
                'manager_action' => 'required|in:deactivate',
            ]);

            if ($validator->fails()) {
                return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
            }

            DB::beginTransaction();

            try {
                $targetSupplierId = $request->target_supplier_id;
                $targetSupplier = Supplier::find($targetSupplierId);

                // 1. إعادة ربط المستشفيات التابعة
                Hospital::where('supplier_id', $id)->update(['supplier_id' => $targetSupplierId]);

                // 2. نقل المخزون (إذا طلب ذلك)
                if ($request->transfer_inventory) {
                    $sourceInventories = \App\Models\Inventory::where('supplier_id', $id)
                        ->where('current_quantity', '>', 0)
                        ->get();

                    foreach ($sourceInventories as $sourceInv) {
                        $qty = $sourceInv->current_quantity;
                        
                        // البحث عن نفس الصنف في المورد الهدف مع قفل السجل لضمان دقة العمليات الحسابية
                        $targetInv = \App\Models\Inventory::where('supplier_id', $targetSupplierId)
                            ->where('drug_id', $sourceInv->drug_id)
                            ->where('batch_number', $sourceInv->batch_number)
                            ->where('expiry_date', $sourceInv->expiry_date)
                            ->lockForUpdate()
                            ->first();

                        if ($targetInv) {
                            $targetInv->increment('current_quantity', $qty);
                        } else {
                            \App\Models\Inventory::create([
                                'supplier_id' => $targetSupplierId,
                                'drug_id' => $sourceInv->drug_id,
                                'batch_number' => $sourceInv->batch_number,
                                'expiry_date' => $sourceInv->expiry_date,
                                'current_quantity' => $qty,
                                'status' => 'active',
                            ]);
                        }
                        
                        // تصفير المخزون في المورد المنقول منه بشكل صريح
                        $sourceInv->current_quantity = 0;
                        $sourceInv->save();
                    }
                }

                // 3. التعامل مع المدير
                $manager = User::where('supplier_id', $id)->where('type', 'supplier_admin')->first();
                if ($manager) {
                    // إيقاف تفعيل الحساب مع الاحتفاظ بـ supplier_id
                    $manager->update([
                        'status' => 'inactive',
                    ]);
                    $manager->tokens()->delete();
                }

                // 4. الإيقاف النهائي للمورد
                $supplier->update(['status' => 'inactive']);

                DB::commit();

                return $this->sendSuccess([
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'status' => 'inactive',
                ], 'تم إيقاف المورد وإعادة توزيع التبعيات بنجاح');

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Supplier Deactivation Error');
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
