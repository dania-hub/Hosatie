<?php

namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;

class DepartmentHospitalAdminController extends BaseApiController
{
    // 1) قائمة الأقسام
   public function index(Request $request)
{
    $hospitalId = $request->user()->hospital_id;

    $departments = Department::with('head')
        ->where('hospital_id', $hospitalId)
        ->latest()
        ->get()
        ->map(function ($dep) {
            return [
                'id'          => $dep->id,
                'name'        => $dep->name,
                'managerId'   => $dep->head_user_id,
                'managerName' => $dep->head?->full_name ?? null,
                'isActive'    => $dep->status === 'active',
                'lastUpdated' => $dep->updated_at?->toIso8601String(),
            ];
        });

    return response()->json($departments);
}

    // 2) قائمة الموظفين (الأطباء) لاختيارهم كمديرين
 public function employees(Request $request)
{
    $hospitalId = $request->user()->hospital_id;

    $employees = User::where('hospital_id', $hospitalId)
        ->where('type', 'doctor')
        ->where('status', 'active')
        ->get()
        ->map(function ($u) {
            return [
                'id'       => $u->id,
                'fullName' => $u->full_name,
                'isActive' => $u->status === 'active',
            ];
        });

    return response()->json($employees);
}



    // 3) إنشاء قسم جديد
  public function store(Request $request)
{
    $hospitalId = $request->user()->hospital_id;

    $data = $request->validate([
        'name'      => 'required|string|max:255',
        'managerId' => 'nullable|exists:users,id',
    ]);

    $dep = Department::create([
        'hospital_id'  => $hospitalId,
        'name'         => $data['name'],
        'head_user_id' => $data['managerId'] ?? null,
        'status'       => 'active',
    ]);

    return response()->json([
        'id'          => $dep->id,
        'name'        => $dep->name,
        'managerId'   => $dep->head_user_id,
        'managerName' => $dep->head?->full_name,
        'isActive'    => true,
        'lastUpdated' => $dep->updated_at?->toIso8601String(),
    ], 201);
}

public function update(Request $request, $id)
{
    $hospitalId = $request->user()->hospital_id;

    $dep = Department::where('hospital_id', $hospitalId)->findOrFail($id);

    $data = $request->validate([
        'name'      => 'required|string|max:255',
        'managerId' => 'nullable|exists:users,id',
    ]);

    $dep->update([
        'name'         => $data['name'],
        'head_user_id' => $data['managerId'] ?? null,
    ]);

    return response()->json([
        'id'          => $dep->id,
        'name'        => $dep->name,
        'managerId'   => $dep->head_user_id,
        'managerName' => $dep->head?->full_name,
        'isActive'    => $dep->status === 'active',
        'lastUpdated' => $dep->updated_at?->toIso8601String(),
    ]);
}


    // 5) تفعيل/تعطيل قسم
    public function toggleStatus(Request $request, $id)
    {
        $hospitalId = $request->user()->hospital_id;

        $department = Department::where('hospital_id', $hospitalId)->findOrFail($id);

        $data = $request->validate([
            'isActive' => 'required|boolean',
        ]);

        $department->status = $data['isActive'] ? 'active' : 'inactive';
        $department->save();

        return response()->json([
            'success' => true,
            'isActive' => $department->status === 'active',
            'lastUpdated' => $department->updated_at?->toIso8601String(),
        ]);
    }
}
