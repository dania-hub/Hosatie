<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Drug;

class LookupDoctorController extends BaseApiController
{
    /**
     * Get All Drugs for Dropdown
     */
    public function drugs()
    {
        // Return minimal data needed for the dropdown
        $drugs = Drug::where('status', 'متوفر') // Optional: only active drugs?
            ->select('id', 'name', 'strength', 'category')
            ->get();

        return $this->sendSuccess($drugs, 'تم جلب قائمة الأدوية بنجاح.');
    }

    /**
     * Get All Drug Categories
     */
    public function categories()
    {
        // If you don't have a separate categories table, 
        // get unique categories from the drugs table.
        $categories = Drug::select('category')
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        // Format as objects if frontend expects objects
        $formatted = $categories->map(function($cat, $index) {
            return ['id' => $index + 1, 'name' => $cat];
        });

        return $this->sendSuccess($formatted, 'تم جلب قائمة الفئات بنجاح.');
    }
}
