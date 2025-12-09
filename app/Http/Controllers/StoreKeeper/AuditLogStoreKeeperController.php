<?php

namespace App\Http\Controllers\StoreKeeper;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\AuditLog;

class AuditLogStoreKeeperController extends BaseApiController
{
    // GET /api/storekeeper/operations
    public function index(Request $request)
    {
        $user = $request->user();

        // فقط مدير المخزن
        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        // يمكن تخصيص المنطق: هنا نعرض العمليات التي نفذها هذا المستخدم فقط
        $logs = AuditLog::where('user_id', $user->id)
            ->where('hospital_id', $user->hospital_id)
            ->orderBy('created_at', 'desc')
            ->get();

        // تحويلها إلى الفورمات الذي تتوقعه الواجهة
        $data = $logs->map(function ($log) {
            return [
                'fileNumber'    => $log->id,          // معرف العملية
                'operationType' => $log->action,      // نوع العملية (مثلاً create_external_request)
                'operationDate' => $log->created_at   // تاريخ العملية بصيغة YYYY/MM/DD
                    ? $log->created_at->format('Y/m/d')
                    : '',
            ];
        });

        return response()->json($data);
    }
}
