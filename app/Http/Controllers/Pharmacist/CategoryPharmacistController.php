<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Drug; // Using the Drug model you have

class CategoryPharmacistController extends BaseApiController
{
    /**
     * List all unique drug categories found in the drugs table.
     */
    public function index()
    {
        // Fetch unique category names from the 'drugs' table
        // This assumes you have a column named 'category' in your drugs table
        $categories = Drug::select('category as name')
            ->distinct()
            ->whereNotNull('category')
            ->get()
            ->map(function ($item, $index) {
                return [
                    'id' => $index + 1, // Generate a fake ID for the frontend
                    'name' => $item->name
                ];
            });

        return $this->sendSuccess($categories, 'تم جلب قائمة الفئات بنجاح.');
    }
}
