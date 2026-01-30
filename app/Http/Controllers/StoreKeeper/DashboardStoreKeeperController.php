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
        
        // التحقق من نوع المستخدم
        if ($user->type !== 'warehouse_manager') {
            return $this->sendError('غير مصرح', [], 403);
        }

        // التحقق من وجود hospital_id للمستخدم
        if (!$user->hospital_id) {
            return $this->sendError('المستخدم غير مرتبط بمستشفى', [], 403);
        }

        $hospitalId = $user->hospital_id;

        // 1) عدد الطلبات الداخلية لهذه المستشفى (من الصيدليات أو الأقسام التابعة للمستشفى)
        $totalInternal = InternalSupplyRequest::where(function ($q) use ($hospitalId) {
                $q->whereHas('pharmacy', fn ($p) => $p->where('hospital_id', $hospitalId))
                    ->orWhere(function ($q2) use ($hospitalId) {
                        $q2->whereNotNull('department_id')
                            ->whereHas('department', fn ($d) => $d->where('hospital_id', $hospitalId));
                    });
            })
            ->count();

        // 2) عدد الطلبات الخارجية لهذه المستشفى
        // الطلبات الخارجية هي الطلبات التي يطلبها StoreKeeper (warehouse_manager) من المورد الخارجي
        // يجب أن تكون مرتبطة بنفس المستشفى وأن تكون من نوع warehouse_manager
        $totalExternal = ExternalSupplyRequest::where('hospital_id', $hospitalId)
            ->whereNotNull('hospital_id') // التأكد من أن hospital_id موجود
            ->whereHas('requester', function ($q) {
                // فقط الطلبات التي أنشأها warehouse_manager
                $q->where('type', 'warehouse_manager');
            })
            ->count();

        // 3) عدد الأصناف (الأدوية) التي وصلت للحد الحرج في مخزن هذه المستشفى
        // نحسب عدد الأدوية المميزة وليس عدد صفوف المخزون (لأن الصنف الواحد قد يكون له عدة دفعات)
        $criticalItems = (int) Inventory::whereNotNull('warehouse_id')
            ->whereHas('warehouse', function ($q) use ($hospitalId) {
                $q->where('hospital_id', $hospitalId);
            })
            ->selectRaw('drug_id, SUM(current_quantity) as total_qty, MIN(minimum_level) as min_level')
            ->groupBy('drug_id')
            ->havingRaw('SUM(current_quantity) <= MIN(minimum_level)')
            ->get()
            ->count();

        // 4) عدد طلبات قيد الاستلام (internal) — نفس نطاق قائمة الطلبات (صيدليات + أقسام)
        // الطلبات التي تمت الموافقة عليها من storekeeper وتم الإرسال لكن لم يتم استلامها بعد
        // pending: قيد الانتظار | approved: قيد الاستلام | fulfilled: تم الاستلام
        $preparingRequests = InternalSupplyRequest::where(function ($q) use ($hospitalId) {
                $q->whereHas('pharmacy', fn ($p) => $p->where('hospital_id', $hospitalId))
                    ->orWhere(function ($q2) use ($hospitalId) {
                        $q2->whereNotNull('department_id')
                            ->whereHas('department', fn ($d) => $d->where('hospital_id', $hospitalId));
                    });
            })
            ->where('status', 'approved')
            ->count();

        return $this->sendSuccess([
            'totalInternal' => $totalInternal,
            'totalExternal' => $totalExternal,
            'criticalItems' => $criticalItems,
            'preparingRequests'  => $preparingRequests,
        ], 'تم جلب الإحصائيات بنجاح');
    }
}
