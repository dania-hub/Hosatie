<?php

namespace App\Http\Controllers\StoreKeeper;

use App\Http\Controllers\BaseApiController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InternalSupplyRequest;
use App\Models\ExternalSupplyRequest;
use App\Models\Inventory;

class DashboardStoreKeeperController extends BaseApiController
{
    // GET /api/storekeeper/dashboard/stats
    public function stats(Request $request)
    {
        $user = $request->user();
        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $hospitalId = $user->hospital_id;

        // 1) إجمالي عدد الطلبات (داخلية + خارجية) لهذه المستشفى
        $totalInternal = InternalSupplyRequest::whereHas('pharmacy', function ($q) use ($hospitalId) {
                $q->where('hospital_id', $hospitalId);
            })
            ->count();

        $totalExternal = ExternalSupplyRequest::where('hospital_id', $hospitalId)->count();

        $totalRegistered = $totalInternal + $totalExternal;

        // 2) عدد الأصناف التي وصلت للحد الحرج في مخزن هذه المستشفى
        // نفترض أن warehouse لهذه المستشفى نميّزه بـ warehouse_id مرتبط بها،
        // أو مؤقتاً نجلب كل inventory للمستودعات التابعة لها.
        $criticalItems = Inventory::whereNotNull('warehouse_id')
            ->whereColumn('current_quantity', '<=', 'minimum_level')
            ->count();

        // 3) عدد طلبات قيد التجهيز (internal)
        $preparingRequests = InternalSupplyRequest::whereHas('pharmacy', function ($q) use ($hospitalId) {
                $q->where('hospital_id', $hospitalId);
            })
            ->whereIn('status', ['approved', 'pending']) // اعتبرتها "قيد التجهيز"
            ->count();

        return response()->json([
            'totalRegistered' => $totalRegistered,
            'todayRegistered' => $criticalItems,
            'weekRegistered'  => $preparingRequests,
        ]);
    }
}
