<?php

namespace App\Http\Controllers\DataEntry;

use App\Http\Controllers\BaseApiController; // Or BaseApiController
use App\Http\Requests\DataEntry\StorePatientRequest;
use App\Http\Requests\DataEntry\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\User;
use App\Models\A;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class PatientDataEntryController extends BaseApiController
{
    // 1. Register New Patient
    public function store(StorePatientRequest $request)
    {
        // Create User with type 'patient'
        $user = User::create([
            'full_name'   => $request->full_name,
            'national_id' => $request->national_id,
            'email'       => $request->email,
            'phone'       => $request->phone,
            'password'    => Hash::make('password123'), // Default password, they will change it (FR-3)
            'type'        => 'patient',
            'status'      => 'pending_activation', // Matches FR-3 flow
            // 'birth_date' => $request->birth_date // Add this if column exists
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل المريض بنجاح',
            'data'    => new PatientResource($user)
        ]);
    }

    // 2. View Patient Details
    public function show($id)
    {
        $user = User::where('id', $id)->where('type', 'patient')->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'المريض غير موجود'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new PatientResource($user)
        ]);
    }

    // 3. Update Patient
    public function update(UpdatePatientRequest $request, $id)
    {
        $user = User::where('id', $id)->where('type', 'patient')->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'المريض غير موجود'], 404);
        }

        $user->update([
            'email' => $request->email,
            'phone' => $request->phone,
            // 'birth_date' => $request->birth_date
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل بيانات المريض بنجاح',
            'data'    => new PatientResource($user)
        ]);
    }
        // 4. View Operations Log (Sijil al-Amaliyat)
    public function activityLog()
    {
        // Fetch logs related to the 'users' table where patient actions happened
        // You can refine this filter based on your needs
        $logs = \App\Models\AuditLog::with('user')
            ->where('table_name', 'users') // Only user-related changes
            ->latest()
            ->paginate(20); // Pagination is good for logs

        return \App\Http\Resources\AuditLogResource::collection($logs);
    }
    // 5. Dashboard Statistics
    public function stats()
    {
        $total = User::where('type', 'patient')->count();
        
        $today = User::where('type', 'patient')
                     ->whereDate('created_at', \Carbon\Carbon::today())
                     ->count();

        $week  = User::where('type', 'patient')
                     ->whereBetween('created_at', [
                         \Carbon\Carbon::now()->startOfWeek(), 
                         \Carbon\Carbon::now()->endOfWeek()
                     ])
                     ->count();

        return response()->json([
            'totalRegistered' => $total,
            'todayRegistered' => $today,
            'weekRegistered'  => $week,
        ]);
    }
     /**
     * List All Patients (Index)
     */
    public function index(Request $request)
    {
        // Basic pagination and search
        $query = User::where('type', 'patient');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%$search%")
                  ->orWhere('national_id', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%");
            });
        }

        $patients = $query->latest()->get(); // Or ->paginate(10)

        return $this->sendSuccess($patients, 'تم جلب قائمة المرضى بنجاح.');
    }

    /**
     * Delete Patient (Destroy)
     */
    public function destroy(Request $request, $id)
    {
        $patient = User::where('type', 'patient')->where('id', $id)->first();

        if (!$patient) {
            return $this->sendError('المريض غير موجود.', [], 404);
        }

        // Optional: Check if patient has dependencies (Prescriptions, etc.)
        // if ($patient->prescriptions()->exists()) {
        //     return $this->sendError('لا يمكن حذف المريض لوجود سجلات طبية مرتبطة به.', [], 400);
        // }

        $patient->delete();

        return $this->sendSuccess([], 'تم حذف المريض بنجاح.');
    }

}
