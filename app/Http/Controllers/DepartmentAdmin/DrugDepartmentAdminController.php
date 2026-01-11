<?php

namespace App\Http\Controllers\DepartmentAdmin;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Drug;
use Illuminate\Support\Facades\DB;

class DrugDepartmentAdminController extends BaseApiController
{
    /**
     * GET /api/department-admin/drugs
     * Supports search, pagination, and filtering
     */
    public function index(Request $request)
    {
        $hospitalId = $request->user()->hospital_id;
        $query = Drug::where('status', '!=', Drug::STATUS_ARCHIVED);

        $query->where(function($q) use ($hospitalId) {
            $q->where('status', Drug::STATUS_AVAILABLE)
                ->orWhere(function($sub) use ($hospitalId) {
                    $sub->where('status', Drug::STATUS_PHASING_OUT);
                    if ($hospitalId) {
                        $sub->whereHas('inventories', function($inv) use ($hospitalId) {
                            $inv->where('current_quantity', '>', 0)
                                ->where(function($qinv) use ($hospitalId) {
                                    $qinv->where(function($wh) use ($hospitalId) {
                                        $wh->whereNotNull('warehouse_id')
                                           ->whereHas('warehouse', function($w) use ($hospitalId) {
                                               $w->where('hospital_id', $hospitalId);
                                           });
                                    })->orWhere(function($ph) use ($hospitalId) {
                                        $ph->whereNotNull('pharmacy_id')
                                           ->whereHas('pharmacy', function($p) use ($hospitalId) {
                                               $p->where('hospital_id', $hospitalId);
                                           });
                                    });
                                });
                        });
                    }
                });
        });

        // Search by Name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Filter by Category
        if ($request->has('categoryId')) {
            // Assuming you pass the category Name or ID depending on your DB structure
            // $query->where('category_id', $request->categoryId);
        }

        $drugs = $query->paginate($request->limit ?? 50);

        $drugs->getCollection()->transform(function($drug) {
            $drug->isPhasingOut = $drug->status === Drug::STATUS_PHASING_OUT;
            $drug->phasingOutWarning = $drug->isPhasingOut 
                ? "هذا الدواء قيد الإيقاف التدريجي. يرجى التخطيط لنقل المريض أو القسم إلى بديل مناسب."
                : null;
            return $drug;
        });

        return $this->sendSuccess($drugs, 'تم جلب قائمة الأدوية بنجاح.');
    }

    /**
     * GET /api/department-admin/drugs/search
     * Simple search for dropdowns
     */
    public function search(Request $request)
    {
        $request->validate(['q' => 'required|string']);

        $hospitalId = $request->user()->hospital_id;
        $query = Drug::where('status', '!=', Drug::STATUS_ARCHIVED)
            ->where('name', 'like', '%' . $request->q . '%');

        $query->where(function($q) use ($hospitalId) {
            $q->where('status', Drug::STATUS_AVAILABLE)
                ->orWhere(function($sub) use ($hospitalId) {
                    $sub->where('status', Drug::STATUS_PHASING_OUT);
                    if ($hospitalId) {
                        $sub->whereHas('inventories', function($inv) use ($hospitalId) {
                            $inv->where('current_quantity', '>', 0)
                                ->where(function($qinv) use ($hospitalId) {
                                    $qinv->where(function($wh) use ($hospitalId) {
                                        $wh->whereNotNull('warehouse_id')
                                           ->whereHas('warehouse', function($w) use ($hospitalId) {
                                               $w->where('hospital_id', $hospitalId);
                                           });
                                    })->orWhere(function($ph) use ($hospitalId) {
                                        $ph->whereNotNull('pharmacy_id')
                                           ->whereHas('pharmacy', function($p) use ($hospitalId) {
                                               $p->where('hospital_id', $hospitalId);
                                           });
                                    });
                                });
                        });
                    }
                });
        });

        $drugs = $query->limit(10)->get()->map(function($drug) {
            $drug->isPhasingOut = $drug->status === Drug::STATUS_PHASING_OUT;
            $drug->phasingOutWarning = $drug->isPhasingOut 
                ? "هذا الدواء قيد الإيقاف التدريجي. يرجى التخطيط لنقل القسم إلى بديل مناسب."
                : null;
            return $drug;
        });

        return $this->sendSuccess($drugs, 'نتائج البحث.');
    }
}
