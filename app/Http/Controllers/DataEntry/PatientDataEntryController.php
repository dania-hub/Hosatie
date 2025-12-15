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
        $user = User::create([
            'full_name'   => $request->full_name,
            'national_id' => $request->national_id,
            'birth_date'  => $request->birth_date,
            'email'       => $request->email,
            'phone'       => $request->phone,
            'password'    => Hash::make('password123'),
            'type'        => 'patient',
            'status'      => 'pending_activation',
        ]);

        AuditLog::create([
            'user_id'    => $request->user()->id ?? null,
            'table_name' => 'users',
            'record_id'  => $user->id,
            'action'     => 'create_patient',
            'old_values' => null,
            'new_values' => json_encode([
                'full_name'   => $user->full_name,
                'national_id' => $user->national_id,
                'birth_date'  => $user->birth_date,
                'phone'       => $user->phone,
                'email'       => $user->email,
            ]),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل المريض بنجاح',
            'data'    => new PatientResource($user),
        ]);
    }

    // 2. View Patient Details
    public function show($id)
    {
        $user = User::where('id', $id)
            ->where('type', 'patient')
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

    // 3. Update Patient
    public function update(UpdatePatientRequest $request, $id)
    {
        $user = User::where('id', $id)
            ->where('type', 'patient')
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'المريض غير موجود',
            ], 404);
        }

        $old = $user->only(['full_name', 'national_id', 'birth_date', 'phone', 'email']);

        $user->update([
            'email'      => $request->email,
            'phone'      => $request->phone,
            'birth_date' => $request->birth_date,
        ]);

        AuditLog::create([
            'user_id'    => $request->user()->id ?? null,
            'table_name' => 'users',
            'record_id'  => $user->id,
            'action'     => 'update_patient',
            'old_values' => json_encode($old),
            'new_values' => json_encode($user->only(['full_name','national_id','birth_date','phone','email'])),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل بيانات المريض بنجاح',
            'data'    => new PatientResource($user),
        ]);
    }

    // 4. View Operations Log (سجل العمليات على المرضى)
    public function activityLog(Request $request)
    {
        $logs = AuditLog::where('table_name', 'users')
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
                'fileNumber'    => 'FILE-' . $log->record_id,
                // نستخدم المفتاح name ليتطابق مع ما تتوقعه واجهة Vue (op.name)
                'name'          => $fullName,
                'operationType' => $operationType,
                // تنسيق التاريخ بصيغة YYYY/MM/DD ليتوافق مع parseDate في الواجهة
                'operationDate' => $log->created_at?->format('Y/m/d'),
            ];
        });

        return response()->json($data);
    }




    // 5. Dashboard Statistics (إحصائيات المرضى)
    public function stats()
    {
        $total = User::where('type', 'patient')->count();

        $today = User::where('type', 'patient')
            ->whereDate('created_at', Carbon::today())
            ->count();

        $week = User::where('type', 'patient')
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
        $query = User::where('type', 'patient')
            // نرجع البريد الإلكتروني أيضاً حتى يظهر في نماذج العرض/التعديل في الواجهة
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
        $patient = User::where('type', 'patient')
            ->where('id', $id)
            ->first();

        if (!$patient) {
            return $this->sendError('المريض غير موجود.', [], 404);
        }

        $old = $patient->only(['full_name','national_id','birth_date','phone','email']);

        $patient->delete();

        AuditLog::create([
            'user_id'    => $request->user()->id ?? null,
            'table_name' => 'users',
            'record_id'  => $id,
            'action'     => 'delete_patient',
            'old_values' => json_encode($old),
            'new_values' => null,
            'ip_address' => $request->ip(),
        ]);

        return $this->sendSuccess([], 'تم حذف المريض بنجاح.');
    }
}
