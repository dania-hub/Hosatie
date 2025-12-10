<?php
namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class OperationLogController extends BaseApiController
{
    public function index(Request $request)
    {
        $logs = AuditLog::latest()->get();

        $data = $logs->map(function ($log) {
            $user = User::find($log->user_id);

            return [
                'fileNumber'    => $log->record_id,                       // أو صيغة أخرى
                'name'          => $user->full_name ?? 'غير معروف',
                'operationType' => $log->action,                          // حوّليه لعربي في الفرونت أو هنا
                'operationDate' => $log->created_at?->format('Y/m/d'),
            ];
        });

        return response()->json($data);
    }
}
