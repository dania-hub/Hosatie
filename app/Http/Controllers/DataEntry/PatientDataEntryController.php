<?php

namespace App\Http\Controllers\DataEntry;

use App\Http\Controllers\BaseApiController;
use App\Http\Requests\DataEntry\StorePatientRequest;
use App\Http\Requests\DataEntry\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PatientDataEntryController extends BaseApiController
{
    // 1. Register New Patient
    public function store(StorePatientRequest $request)
    {
        $authUser = $request->user();
        $hospitalId = $authUser->hospital_id;

        // التأكد من أن المستخدم لديه hospital_id
        if (!$hospitalId) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير مرتبط بمستشفى',
            ], 400);
        }

        $user = User::create([
            'full_name'   => $request->full_name,
            'national_id' => $request->national_id,
            'birth_date'  => $request->birth_date,
            'email'       => $request->email,
            'phone'       => $request->phone,
            'password'    => Hash::make('password123'),
            'type'        => 'patient',
            'status'      => 'pending_activation',
            'hospital_id' => $hospitalId,
        ]);

        // يتم تسجيل العملية تلقائياً من خلال UserObserverب

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل المريض بنجاح',
            'data'    => new PatientResource($user),
        ]);
    }

    // 2. View Patient Details
    public function show(Request $request, $id)
    {
        $authUser = $request->user();
        $hospitalId = $authUser->hospital_id;

        // التأكد من أن المستخدم لديه hospital_id
        if (!$hospitalId) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير مرتبط بمستشفى',
            ], 400);
        }

        $user = User::where('id', $id)
            ->where('type', 'patient')
            ->where('hospital_id', $hospitalId)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'المريض غير موجود',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new PatientResource($user),
        ]);
    }

    // 3. Update Patient (تم التحديث)
    public function update(UpdatePatientRequest $request, $id)
    {
        $authUser = $request->user();
        $hospitalId = $authUser->hospital_id;

        // التأكد من أن المستخدم لديه hospital_id
        if (!$hospitalId) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير مرتبط بمستشفى',
            ], 400);
        }

        $user = User::where('id', $id)
            ->where('type', 'patient')
            ->where('hospital_id', $hospitalId)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'المريض غير موجود',
            ], 404);
        }

        $user->update([
            'full_name'  => $request->full_name, // تمت الإضافة
            'email'      => $request->email,
            'phone'      => $request->phone,
            'birth_date' => $request->birth_date,
        ]);

        // يتم تسجيل العملية تلقائياً من خلال UserObserver

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل بيانات المريض بنجاح',
            'data'    => new PatientResource($user),
        ]);
    }

    // 4. View Operations Log (سجل العمليات على المرضى)
    public function activityLog(Request $request)
    {
        $user = $request->user();
        $hospitalId = $user->hospital_id;

        // التأكد من أن المستخدم لديه hospital_id
        if (!$hospitalId) {
            return response()->json([
                'error' => 'المستخدم غير مرتبط بمستشفى'
            ], 400);
        }

        // جلب سجلات المرضى فقط للمستشفى المحدد
        $patientIds = User::where('type', 'patient')
            ->where('hospital_id', $hospitalId)
            ->pluck('id');

        $logs = AuditLog::where('table_name', 'users')
            ->whereIn('record_id', $patientIds)
            ->latest()
            ->get();

        $data = $logs->map(function ($log) {
            $patient = User::find($log->record_id);
            $old     = $log->old_values ? json_decode($log->old_values, true) : [];
            $new     = $log->new_values ? json_decode($log->new_values, true) : [];

            // 1) نحاول من الموديل مباشرة
            if ($patient) {
                $fullName = $patient->full_name;
            } else {
                // 2) من new_values
                $fullName = $new['full_name']
                    ?? ($old['full_name'] ?? 'غير معروف');
            }

            $operationType = match ($log->action) {
                'create_patient' => 'إضافة',
                'update_patient' => 'تعديل',
                'delete_patient' => 'حذف',
                default          => $log->action,
            };

            return [
                'fileNumber'    => $log->record_id,
                // نستخدم المفتاح name ليتطابق مع ما تتوقعه واجهة Vue (op.name)
                'name'          => $fullName,
                'operationType' => $operationType,
                // تنسيق التاريخ بصيغة YYYY/MM/DD ليتوافق مع parseDate في الواجهة
                'operationDate' => $log->created_at?->format('Y/m/d'),
                'changes'       => [
                    'old' => $old,
                    'new' => $new,
                ],
            ];
        });

        return response()->json($data);
    }

    // 5. Dashboard Statistics (إحصائيات المرضى)
    public function stats(Request $request)
    {
        $user = $request->user();
        $hospitalId = $user->hospital_id;

        // التأكد من أن المستخدم لديه hospital_id
        if (!$hospitalId) {
            return response()->json([
                'error' => 'المستخدم غير مرتبط بمستشفى'
            ], 400);
        }

        $total = User::where('type', 'patient')
            ->where('hospital_id', $hospitalId)
            ->count();

        $today = User::where('type', 'patient')
            ->where('hospital_id', $hospitalId)
            ->whereDate('created_at', Carbon::today())
            ->count();

        $week = User::where('type', 'patient')
            ->where('hospital_id', $hospitalId)
            ->whereBetween('created_at', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
            ])->count();

        return response()->json([
            'totalRegistered' => $total,
            'todayRegistered' => $today,
            'weekRegistered'  => $week,
        ]);
    }

    // 6. List All Patients (Index)
    public function index(Request $request)
    {
        $user = $request->user();
        $hospitalId = $user->hospital_id;

        // التأكد من أن المستخدم لديه hospital_id
        if (!$hospitalId) {
            return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
        }

        $query = User::where('type', 'patient')
            ->where('hospital_id', $hospitalId)
            ->select('id', 'full_name', 'national_id', 'birth_date', 'phone', 'email', 'created_at');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%$search%")
                  ->orWhere('national_id', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%");
            });
        }

        $patients = $query->latest()->get();

        return $this->sendSuccess($patients, 'تم جلب قائمة المرضى بنجاح.');
    }

    // 7. Delete Patient
    public function destroy(Request $request, $id)
    {
        $authUser = $request->user();
        $hospitalId = $authUser->hospital_id;

        // التأكد من أن المستخدم لديه hospital_id
        if (!$hospitalId) {
            return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
        }

        $patient = User::where('type', 'patient')
            ->where('id', $id)
            ->where('hospital_id', $hospitalId)
            ->first();

        if (!$patient) {
            return $this->sendError('المريض غير موجود.', [], 404);
        }

        $patient->delete();

        // يتم تسجيل العملية تلقائياً من خلال UserObserver

        return $this->sendSuccess([], 'تم حذف المريض بنجاح.');
    }
    // 8. Check Uniqueness (للتحقق الفوري)
    public function checkUnique(Request $request)
    {
        $user = $request->user();
        $hospitalId = $user->hospital_id;

        $request->validate([
            'national_id' => 'nullable|string',
            'phone'       => 'nullable|string',
            'exclude_id'  => 'nullable|integer',
        ]);

        $exists = false;
        $msg = '';

        if ($request->filled('national_id')) {
            $query = User::where('national_id', $request->national_id)
                         ->where('type', 'patient');
            
            if ($request->filled('exclude_id')) {
                $query->where('id', '!=', $request->exclude_id);
            }

            if ($query->exists()) {
                $exists = true;
                $msg = 'الرقم الوطني مسجل بالفعل';
            }
        }

        if (!$exists && $request->filled('phone')) {
            $query = User::where('phone', $request->phone)
                         ->where('type', 'patient');

            if ($request->filled('exclude_id')) {
                $query->where('id', '!=', $request->exclude_id);
            }

            if ($query->exists()) {
                $exists = true;
                $msg = 'رقم الهاتف مسجل بالفعل';
            }
        }

        return response()->json([
            'exists' => $exists,
            'message' => $msg
        ]);
    }
}