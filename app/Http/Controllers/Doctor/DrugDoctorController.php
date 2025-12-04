<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Drug;

class DrugDoctorController extends BaseApiController
{
    /**
     * GET /api/doctor/drugs
     * List drugs with optional filtering by category or search term.
     */
    public function index(Request $request)
    {
        // 1. Get Query Parameters
        $category = $request->query('category');
        $search = $request->query('search');

        // 2. Build Query
        $query = Drug::select('id', 'name', 'generic_name', 'strength', 'form', 'category', 'unit');

        // 3. Filter by Category (if provided)
        if ($category) {
            $query->where('category', $category);
        }

        // 4. Search by Name (if provided)
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('generic_name', 'like', "%{$search}%");
            });
        }

        // 5. Execute & Return (Limit to 50 to avoid huge payloads)
        $drugs = $query->orderBy('name')
                       ->limit(50)
                       ->get();

        return $this->sendSuccess($drugs, 'Drugs retrieved successfully.');
    }

    /**
     * GET /api/doctor/drug-categories
     * List all unique drug categories.
     */
    public function categories()
    {
        $categories = Drug::select('category')
            ->distinct()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->orderBy('category')
            ->pluck('category'); // Returns a simple array of strings

        return $this->sendSuccess($categories, 'Categories retrieved successfully.');
    }
}
