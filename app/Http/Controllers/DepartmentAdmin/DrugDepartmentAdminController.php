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
        $query = Drug::query();

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

        return $this->sendSuccess($drugs, 'تم جلب قائمة الأدوية بنجاح.');
    }

    /**
     * GET /api/department-admin/drugs/search
     * Simple search for dropdowns
     */
    public function search(Request $request)
    {
        $request->validate(['q' => 'required|string']);

        // Join with inventory and sum current_quantity as stock
        $drugs = Drug::leftJoin('inventory', 'drug.id', '=', 'inventory.drug_id')
            ->where('drug.name', 'like', '%' . $request->q . '%')
            ->select(
                'drug.id',
                'drug.name',
                'drug.strength',
                DB::raw('COALESCE(SUM(inventory.current_quantity), 0) as stock')
            )
            ->groupBy('drug.id', 'drug.name', 'drug.strength')
            ->limit(10)
            ->get();

        return $this->sendSuccess($drugs, 'نتائج البحث.');
    }
}
