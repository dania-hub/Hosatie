<?php

namespace App\Http\Controllers\DepartmentAdmin;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Drug; 
// use App\Models\DrugCategory; // Use this if you have a dedicated table

class CategoryDepartmentAdminController extends BaseApiController
{
    /**
     * GET /api/department-admin/categories
     */
    public function index()
    {
        // Option A: If you use 'category' string column in drugs table
        $categories = Drug::select('category')
            ->whereNotNull('category')
            ->distinct()
            ->get()
            ->map(function ($item, $index) {
                return [
                    'id' => $index + 1,
                    'name' => $item->category,
                    'description' => 'وصف للفئة ' . $item->category, // Placeholder
                ];
            });

        // Option B: If you have a DrugCategory model
        // $categories = \App\Models\DrugCategory::all();

        return $this->sendSuccess($categories, 'تم جلب فئات الأدوية بنجاح.');
    }
}
