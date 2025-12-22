<?php

namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Mail\StaffActivationMail;
use Illuminate\Support\Facades\Log;

class StaffController extends BaseApiController
{
    /**
     * Get all staff members for the hospital
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
            }

            $hospitalId = $user->hospital_id;
            
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            // خريطة تحويل الأدوار من الإنجليزية إلى العربية
            $roleMap = [
                'doctor' => 'طبيب',
                'pharmacist' => 'صيدلي',
                'warehouse_manager' => 'مدير المخزن',
                'department_head' => 'مدير القسم',
                'data_entry' => 'مدخل بيانات',
            ];

            $staff = User::with(['department'])
                ->where('hospital_id', $hospitalId)
                ->whereIn('type', ['doctor', 'pharmacist', 'warehouse_manager', 'department_head', 'data_entry'])
                ->latest()
                ->get()
                ->map(function ($user) use ($roleMap) {
                    return [
                        'fileNumber' => $user->id,
                        'name' => $user->full_name,
                        'nationalId' => $user->national_id,
                        'birth' => $user->birth_date ? $user->birth_date->format('Y/m/d') : null,
                        'phone' => $user->phone,
                        'email' => $user->email,
                        'role' => $roleMap[$user->type] ?? $user->type, // تحويل إلى العربية
                        'department' => $user->department?->name ?? null,
                        'isActive' => $user->status === 'active',
                        'lastUpdated' => $user->updated_at?->toIso8601String(),
                    ];
                });

            return $this->sendSuccess($staff, 'تم جلب قائمة الموظفين بنجاح.');
        } catch (\Exception $e) {
            Log::error('Get Staff Error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->sendError('فشل في جلب قائمة الموظفين.', [], 500);
        }
    }

    /**
     * Admin creates a new Staff member
     */
    public function store(Request $request)
    {
        try {
            $currentUser = $request->user();
            
            if (!$currentUser) {
                return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
            }

            $hospitalId = $currentUser->hospital_id;
            
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            // 1. Validate
            $validated = $request->validate([
                'full_name' => 'required|string|max:255',
                'email'     => 'required|email|unique:users,email',
                'role'      => 'required|string|in:doctor,pharmacist,warehouse_manager,department_head,data_entry',
                'phone'     => 'nullable|string|unique:users,phone',
                'national_id' => 'nullable|string|unique:users,national_id',
                'birth_date' => 'nullable|date',
            ]);

            DB::beginTransaction();

            // 2. Create User (Inactive, No Password yet)
            $user = User::create([
                'full_name'   => $validated['full_name'],
                'email'       => $validated['email'],
                'type'        => $validated['role'],
                'hospital_id' => $hospitalId, // إضافة hospital_id
                'phone'       => $validated['phone'] ?? null,
                'national_id' => $validated['national_id'] ?? null,
                'birth_date'  => $validated['birth_date'] ?? null,
                'status'      => 'pending_activation',
                'password'    => '', // Empty for now
                'created_by'  => $currentUser->id,
            ]);

            // 3. Generate Secure Token
            $token = Str::random(60);

            // 4. Store Token in Cache
            $key = 'activation_token_' . $user->email;
            \Illuminate\Support\Facades\Cache::put($key, $token, 86400); // 24 hours
            
            // 5. Send Email (مع معالجة الأخطاء)
            try {
                Mail::to($user->email)->send(new StaffActivationMail($token, $user->email, $user->full_name));
            } catch (\Exception $mailException) {
                // تسجيل خطأ البريد ولكن لا نوقف العملية
                Log::warning('Failed to send activation email: ' . $mailException->getMessage(), [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
            }

            DB::commit();

            return $this->sendSuccess([
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->type,
            ], 'تم إنشاء حساب الموظف بنجاح. سيتم إرسال رابط التفعيل إلى بريده الإلكتروني.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->sendError('التحقق من البيانات فشل.', $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create Staff Error: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->all()
            ]);
            return $this->sendError('فشل في إنشاء حساب الموظف: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Toggle staff status (activate/deactivate)
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $currentUser = $request->user();
            
            if (!$currentUser) {
                return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
            }

            $hospitalId = $currentUser->hospital_id;
            
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            // التحقق من صحة البيانات
            $validated = $request->validate([
                'isActive' => 'required|boolean',
            ]);

            // البحث عن الموظف في نفس المستشفى
            $staff = User::where('hospital_id', $hospitalId)
                ->whereIn('type', ['doctor', 'pharmacist', 'warehouse_manager', 'department_head', 'data_entry'])
                ->findOrFail($id);

            // تحديث الحالة
            $staff->status = $validated['isActive'] ? 'active' : 'inactive';
            $staff->save();

            return $this->sendSuccess([
                'id' => $staff->id,
                'name' => $staff->full_name,
                'isActive' => $staff->status === 'active',
                'lastUpdated' => $staff->updated_at->toIso8601String(),
            ], $validated['isActive'] ? 'تم تفعيل حساب الموظف بنجاح.' : 'تم تعطيل حساب الموظف بنجاح.');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('الموظف غير موجود أو لا ينتمي إلى هذا المستشفى.', [], 404);
        } catch (\Exception $e) {
            Log::error('Toggle Staff Status Error: ' . $e->getMessage(), [
                'exception' => $e,
                'staff_id' => $id,
                'request_data' => $request->all()
            ]);
            return $this->sendError('فشل في تحديث حالة الموظف: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Handle exceptions for this controller: log and return JSON error.
     *
     * @param \Throwable $e
     * @param string $contextMessage
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleException(\Throwable $e, $contextMessage = 'Error')
    {
        // Log the exception for debugging
        Log::error($contextMessage . ': ' . $e->getMessage(), ['exception' => $e]);

        // Return a generic JSON error response
        return response()->json([
            'success' => false,
            'message' => $contextMessage,
            'error'   => $e->getMessage()
        ], 500);
    }
}
