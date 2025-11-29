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

class PatientController extends BaseApiController
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

}
