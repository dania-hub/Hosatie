<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseApiController;
class PatientSuperController extends BaseApiController
{
    /**
     * عرض جميع المرضى مع بيانات المستشفى
     */
    public function index(Request $request)
    {
        // جلب المرضى فقط
        $patients = User::with('hospital')        // يستخدم علاقة hospital الموجودة عندك
            ->where('type', 'patient')           // فقط المستخدمين من نوع مريض
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($user) {
                return [
                    'fileNumber'   => $user->id,                          // رقم الملف
                    'fullName'     => $user->full_name,                  // الاسم الرباعي
                    'nationalId'   => $user->national_id,                // الرقم الوطني
                    'birthDate'    => optional($user->birth_date)->format('Y-m-d'),
                    'phone'        => $user->phone,
            
                    'hospitalName' => optional($user->hospital)->name,   // اسم المستشفى من العلاقة
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $patients,
            'message' => 'تم جلب جميع المرضى بنجاح',
        ], 200);
    }
}
