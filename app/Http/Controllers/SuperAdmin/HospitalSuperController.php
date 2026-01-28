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
use App\Services\StaffNotificationService;
use App\Services\PatientNotificationService;

class HospitalSuperController extends BaseApiController
{
    /**
     * جلب الكود التالي المقترح للمستشفى
     * GET /api/super-admin/hospitals/next-code
     */
    public function getNextHospitalCode(Request $request)
    {
        try {
            $user = $request->user();
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $lastId = Hospital::max('id') ?? 0;
            $nextId = $lastId + 1;
            $code = "HOSP-TR-" . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            return $this->sendSuccess(['code' => $code], 'تم توليد الكود المقترح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Next Hospital Code Error');
        }
    }

    /**
     * التحقق من وجود اسم المستشفى أو المورد
     * GET /api/super-admin/hospitals/check-name/{name}
     */
    public function checkName(Request $request, $name)
    {
        try {
            $user = $request->user();
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $existsInHospitals = Hospital::where('name', $name)->exists();
            $existsInSuppliers = \App\Models\Supplier::where('name', $name)->exists();
            $exists = $existsInHospitals || $existsInSuppliers;

            return $this->sendSuccess([
                'exists' => $exists,
                'name' => $name
            ], $exists ? 'الاسم موجود بالفعل في النظام' : 'الاسم متاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Hospital Check Name Error');
        }
    }

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
                'code' => 'sometimes|string|max:50|unique:hospitals,code',
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

            // التحقق من تكرار الاسم في المستشفيات والموردين
            $nameExistsInSuppliers = \App\Models\Supplier::where('name', $request->name)->exists();
            $nameExistsInHospitals = Hospital::where('name', $request->name)->exists();
            if ($nameExistsInSuppliers || $nameExistsInHospitals) {
                return $this->sendError('بيانات غير صحيحة', ['name' => ['هذا الاسم مستخدم بالفعل لمستشفى أو شركة توريد']], 422);
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

            // توليد كود تلقائي إذا لم يتم إرساله
            $code = $request->code;
            if (!$code) {
                $lastId = Hospital::max('id') ?? 0;
                $nextId = $lastId + 1;
                $code = "HOSP-TR-" . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            }

            // إنشاء المؤسسة
            $hospital = Hospital::create([
                'name' => $request->name,
                'code' => $code,
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
                'manager_id' => 'nullable|exists:users,id',
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

            DB::beginTransaction();

            try {
                // تحديث بيانات المستشفى
                $hospital->update($request->only([
                    'name', 'code', 'type', 'city', 'address', 'phone', 'supplier_id'
                ]));

                // معالجة تعيين المدير
                if ($request->has('manager_id')) {
                    $managerId = $request->input('manager_id');
                    
                    // إذا تم إرسال null، تعطيل المدير الحالي فقط دون حذف hospital_id
                    if ($managerId === null || $managerId === '') {
                        // تعطيل المدير الحالي إن وجد مع الاحتفاظ بـ hospital_id
                        if ($hospital->admin) {
                            $hospital->admin->update([
                                'status' => 'inactive'
                            ]);
                            // حذف جميع tokens للمستخدم
                            $hospital->admin->tokens()->delete();
                        }
                    } else {
                        // التحقق من أن المستخدم المحدد هو hospital_admin
                        $newManager = User::where('type', 'hospital_admin')
                            ->where('id', $managerId)
                            ->first();
                        
                        if (!$newManager) {
                            DB::rollBack();
                            return $this->sendError('المستخدم المحدد ليس مدير مستشفى', ['manager_id' => ['المستخدم المحدد ليس مدير مستشفى']], 422);
                        }

                        // تعطيل المدير الحالي إن وجد مع الاحتفاظ بـ hospital_id
                        if ($hospital->admin && $hospital->admin->id != $managerId) {
                            $oldAdmin = $hospital->admin;
                            $oldAdmin->update([
                                'status' => 'inactive'
                            ]);
                            // حذف جميع tokens للمستخدم
                            $oldAdmin->tokens()->delete();
                        }

                        // تعطيل أي مديرين آخرين مرتبطين بنفس المستشفى مع الاحتفاظ بـ hospital_id
                        $otherAdmins = User::where('hospital_id', $hospital->id)
                            ->where('type', 'hospital_admin')
                            ->where('id', '!=', $managerId)
                            ->get();
                        
                        foreach ($otherAdmins as $otherAdmin) {
                            $otherAdmin->update([
                                'status' => 'inactive'
                            ]);
                            // حذف جميع tokens للمستخدم
                            $otherAdmin->tokens()->delete();
                        }

                        // تعيين المدير الجديد
                        $newManager->update(['hospital_id' => $hospital->id]);
                        
                        // تفعيل المدير إذا كان معطلاً
                        if ($newManager->status === 'inactive') {
                            $newManager->update(['status' => 'active']);
                        }
                    }
                }

                DB::commit();

                return $this->sendSuccess([
                    'id' => $hospital->id,
                    'name' => $hospital->name,
                ], 'تم تعديل بيانات المؤسسة بنجاح');

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Hospital Update Error');
        }
    }

    /**
     * التحقق المسبق قبل إيقاف تفعيل مستشفى
     * GET /api/super-admin/hospitals/{id}/deactivation-data
     */
    public function deactivationData(Request $request, $id)
    {
        try {
            $user = $request->user();
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $hospital = Hospital::findOrFail($id);

            // 1. التحقق من العمليات النشطة (Blockers)
            
            // طلبات التوريد الخارجية النشطة
            $activeExternalRequests = \App\Models\ExternalSupplyRequest::where('hospital_id', $id)
                ->whereIn('status', ['pending', 'approved', 'fulfilled', 'partially_approved'])
                ->get();
            
            // طلبات التوريد الداخلية النشطة (من الصيدليات التابعة للمستشفى)
            $activeInternalRequests = \App\Models\InternalSupplyRequest::whereHas('pharmacy', function($query) use ($id) {
                    $query->where('hospital_id', $id);
                })
                ->whereIn('status', ['pending', 'approved'])
                ->get();

            $blockers = [];
            foreach ($activeExternalRequests as $req) {
                $blockers[] = "طلب توريد خارجي رقم EXT-{$req->id}";
            }
            foreach ($activeInternalRequests as $req) {
                $blockers[] = "طلب توريد داخلي رقم INT-{$req->id}";
            }

            // 2. التحقق من الكيانات المرتبطة (Counts)
            
            // المرضى المسجلون
            $patientsCount = User::where('hospital_id', $id)->where('type', 'patient')->count();
            
            // الموظفون النشطون
            $employeesCount = User::where('hospital_id', $id)
                ->where('type', '!=', 'patient')
                ->where('status', 'active')
                ->count();
            
            // الشكاوى المفتوحة (المرتبطة بمرضى هذا المستشفى)
            $openComplaintsCount = \App\Models\Complaint::whereHas('patient', function($query) use ($id) {
                    $query->where('hospital_id', $id);
                })
                ->where('status', 'pending')
                ->count();
            
            // طلبات النقل المعلقة
            $pendingTransfersCount = \App\Models\PatientTransferRequest::where(function($query) use ($id) {
                    $query->where('from_hospital_id', $id)->orWhere('to_hospital_id', $id);
                })
                ->where('status', 'pending')
                ->count();

            // 3. قائمة المستشفيات البديلة (في نفس المدينة ونشطة)
            $otherHospitals = Hospital::where('city', $hospital->city)
                ->where('id', '!=', $id)
                ->where('status', 'active')
                ->get(['id', 'name']);

            // 4. مدير المستشفى ومسؤول المخزن
            $hospitalAdmin = User::where('hospital_id', $id)->where('type', 'hospital_admin')->first();
            $warehouseManager = User::where('hospital_id', $id)->where('type', 'warehouse_manager')->first();

            return $this->sendSuccess([
                'hospital' => [
                    'id' => $hospital->id,
                    'name' => $hospital->name,
                    'city' => $hospital->city,
                ],
                'blockers' => $blockers,
                'hasBlockers' => count($blockers) > 0,
                'counts' => [
                    'patients' => $patientsCount,
                    'employees' => $employeesCount,
                    'complaints' => $openComplaintsCount,
                    'transfers' => $pendingTransfersCount,
                ],
                'alternativeHospitals' => $otherHospitals,
                'managers' => [
                    'hospitalAdmin' => $hospitalAdmin ? [
                        'id' => $hospitalAdmin->id,
                        'name' => $hospitalAdmin->full_name,
                    ] : null,
                    'warehouseManager' => $warehouseManager ? [
                        'id' => $warehouseManager->id,
                        'name' => $warehouseManager->full_name,
                    ] : null,
                ]
            ], 'تم جلب بيانات فحص الإيقاف بنجاح');

        } catch (\Exception $e) {
            return $this->handleException($e, 'Super Admin Hospital Deactivation Data Error');
        }
    }

    /**
     * FR-88: تعطيل مؤسسة صحية (مع المعالج التفاعلي)
     * PATCH /api/super-admin/hospitals/{id}/deactivate
     */
    public function deactivate(Request $request, $id)
    {
        $staffNotify = app(StaffNotificationService::class);
        $patientNotify = app(PatientNotificationService::class);
        
        try {
            $user = $request->user();
            
            if ($user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $hospital = Hospital::findOrFail($id);

            if ($hospital->status === 'inactive') {
                return $this->sendError('المؤسسة معطلة بالفعل', null, 400);
            }

            // التحقق من وجود طلبات توريد نشطة قبل التنفيذ
            $hasExternal = \App\Models\ExternalSupplyRequest::where('hospital_id', $id)
                ->whereIn('status', ['pending', 'approved', 'fulfilled', 'partially_approved'])
                ->exists();
            $hasInternal = \App\Models\InternalSupplyRequest::whereHas('pharmacy', function($query) use ($id) {
                    $query->where('hospital_id', $id);
                })
                ->whereIn('status', ['pending', 'approved'])
                ->exists();

            if ($hasExternal || $hasInternal) {
                return $this->sendError('لا يمكن إيقاف المستشفى. يوجد طلبات توريد نشطة. يجب إكمالها أو إلغاؤها أولاً.', null, 422);
            }

            // إذا لم يتم تمرير بيانات المعالج (طلب إيقاف مباشر قديم)، نطلب البيانات
            if (!$request->has('target_hospital_id')) {
                return $this->sendError('يرجى إكمال خطوات معالج الإيقاف أولاً.', null, 422);
            }

            $validator = Validator::make($request->all(), [
                'target_hospital_id' => 'required|exists:hospitals,id',
                'target_employee_entity_id' => 'nullable|exists:hospitals,id', 
                'deactivate_employees' => 'boolean',
                'manager_action' => 'required|in:deactivate,change_role',
                'manager_new_role' => 'required_if:manager_action,change_role|in:hospital_admin,warehouse_manager,doctor,department_head,pharmacist,data_entry',
                'store_keeper_action' => 'required|in:deactivate,change_role',
                'store_keeper_new_role' => 'required_if:store_keeper_action,change_role|in:hospital_admin,warehouse_manager,doctor,department_head,pharmacist,data_entry',
            ]);

            if ($validator->fails()) {
                return $this->sendError('بيانات غير صحيحة', $validator->errors(), 422);
            }

            DB::beginTransaction();

            try {
                // 1. التعامل مع الشكاوى المفتوحة
                \App\Models\Complaint::whereHas('patient', function($query) use ($id) {
                        $query->where('hospital_id', $id);
                    })
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'closed',
                        'reply_message' => 'تم إغلاق الشكوى بسبب إيقاف نشاط المستشفى. يرجى التواصل مع إدارة النظام للمزيد من التفاصيل.',
                        'replied_at' => now(),
                        'replied_by' => $user->id,
                    ]);

                // 2. التعامل مع طلبات النقل المعلقة
                \App\Models\PatientTransferRequest::where(function($query) use ($id) {
                        $query->where('from_hospital_id', $id)->orWhere('to_hospital_id', $id);
                    })
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'rejected',
                        'rejection_reason' => 'تم إلغاء الطلب بسبب إيقاف نشاط المستشفى المصدر.',
                        'handeled_by' => $user->id,
                        'handeled_at' => now(),
                    ]);

                // تحديد الصيدلية الهدف للمرضى والموظفين
                $targetHospitalPharmacyId = \App\Models\Pharmacy::where('hospital_id', $request->target_hospital_id)
                    ->where('status', 'active')
                    ->value('id');
                
                $targetEmployeePharmacyId = $request->target_employee_entity_id 
                    ? \App\Models\Pharmacy::where('hospital_id', $request->target_employee_entity_id)->where('status', 'active')->value('id')
                    : $targetHospitalPharmacyId;

                // 3. إعادة توزيع المرضى
                $targetHospital = Hospital::find($request->target_hospital_id);
                $patients = User::where('hospital_id', $id)
                    ->where('type', 'patient')
                    ->get();

                foreach ($patients as $patient) {
                    $patient->update([
                        'hospital_id' => $request->target_hospital_id,
                        'pharmacy_id' => $targetHospitalPharmacyId, // التبعية للصيدلية الجديدة
                    ]);
                    $patientNotify->createNotification(
                        $patient,
                        'عادي',
                        'تغيير التبعية الطبية',
                        "بسبب إيقاف نشاط مستشفى [{$hospital->name}]، تم نقل ملفك الطبي إلى مستشفى [{$targetHospital->name}] وصيدلية [".(\App\Models\Pharmacy::find($targetHospitalPharmacyId)->name ?? 'الصيدلية المركزية')."]."
                    );
                }

                // 4. إعادة توزيع الموظفين العاديين
                $employeeTypes = ['pharmacist', 'doctor', 'data_entry', 'department_head'];
                $employees = User::where('hospital_id', $id)
                    ->whereIn('type', $employeeTypes)
                    ->get();

                foreach ($employees as $employee) {
                    if ($request->deactivate_employees) {
                        // إذا كان رئيس قسم، نصفر مسؤوليته في جدول الأقسام
                        if ($employee->type === 'department_head') {
                            \App\Models\Department::where('head_user_id', $employee->id)->update(['head_user_id' => null]);
                        }

                        $employee->update([
                            'status' => 'inactive',
                            'pharmacy_id' => null,
                            'warehouse_id' => null,
                        ]);
                        $staffNotify->createNotification(
                            $employee,
                            'تنبيه: إيقاف حساب',
                            "تم إيقاف تفعيل حسابك مؤقتاً بسبب إيقاف نشاط مستشفى [{$hospital->name}]. يرجى مراجعة إدارة النظام.",
                            'عادي'
                        );
                    } else if ($request->target_employee_entity_id) {
                        $isHOD = $employee->type === 'department_head';
                        $newType = $isHOD ? 'doctor' : $employee->type;
                        
                        // إذا كان رئيس قسم، نصفر مسؤوليته القديمة قبل النقل
                        if ($isHOD) {
                            \App\Models\Department::where('head_user_id', $employee->id)->update(['head_user_id' => null]);
                        }

                        $employee->update([
                            'hospital_id' => $request->target_employee_entity_id,
                            'type' => $newType, 
                            'pharmacy_id' => ($newType === 'pharmacist') ? $targetEmployeePharmacyId : null, 
                            'warehouse_id' => null, 
                        ]);
                        $staffNotify->createNotification(
                            $employee,
                            'تنبيه: نقل إداري',
                            "تم نقل تبعيتك الإدارية إلى مستشفى [{$targetHospital->name}]. " . ($isHOD ? "تم تعديل دورك الوظيفي من رئيس قسم إلى طبيب." : "مع الحفاظ على دورك الوظيفي الحالي."),
                            'عادي'
                        );
                    }
                }

                // 5. التعامل مع المدير ومسؤول المخزن
                $managers = User::where('hospital_id', $id)
                    ->whereIn('type', ['hospital_admin', 'warehouse_manager'])
                    ->get();

                foreach ($managers as $manager) {
                    $isHospitalAdmin = $manager->type === 'hospital_admin';
                    $action = $isHospitalAdmin ? $request->manager_action : $request->store_keeper_action;
                    $newRole = $isHospitalAdmin ? $request->manager_new_role : $request->store_keeper_new_role;
                    
                    if ($action === 'deactivate') {
                        $manager->update([
                            'status' => 'inactive',
                            'pharmacy_id' => null,
                            'warehouse_id' => null,
                        ]);
                        $manager->tokens()->delete();
                        $staffNotify->createNotification(
                            $manager,
                            'إيقاف الصلاحيات الإدارية',
                            "تم إيقاف حسابك الإداري بسبب إيقاف نشاط مستشفى [{$hospital->name}].",
                            'عادي'
                        );
                    } else {
                        $role = $newRole ?: 'data_entry';
                        // تغيير الدور حسب المختار من القائمة ونقله
                        $manager->update([
                            'type' => $role,
                            'hospital_id' => $request->target_employee_entity_id ?: $request->target_hospital_id,
                            'pharmacy_id' => ($role === 'pharmacist') ? $targetEmployeePharmacyId : null, 
                            'warehouse_id' => null, 
                        ]);
                        $staffNotify->createNotification(
                            $manager,
                            'تحديث الدور الوظيفي',
                            "تم نقل تبعيتك إلى مستشفى [{$targetHospital->name}] وتعديل دورك الوظيفي. يرجى مراجعة الصلاحيات الجديدة.",
                            'عادي'
                        );
                    }
                }

                // 6. الإيقاف النهائي للمستشفى
                $hospital->update(['status' => 'inactive']);

                DB::commit();

                return $this->sendSuccess([
                    'id' => $hospital->id,
                    'name' => $hospital->name,
                    'status' => 'inactive',
                ], 'تم تنفيذ عملية إيقاف المستشفى وإعادة التوزيع بنجاح');

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

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
