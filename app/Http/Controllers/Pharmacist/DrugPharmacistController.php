<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Drug;
use App\Models\Inventory; // Using Inventory model
use Illuminate\Support\Facades\DB;

class DrugPharmacistController extends BaseApiController
{
    /**
     * GET /api/pharmacist/drugs
     * List drugs currently in the pharmacy inventory
     */
    public function index(Request $request)
    {
        // Fetch inventory items with their drug details
        $inventory = Inventory::with('drug')->get()->map(function ($item) {
            return [
                'id' => $item->id, // Inventory ID
                'drugCode' => $item->drug->id, // Or specific code column if exists
                'drugName' => $item->drug->name,
                'quantity' => $item->current_quantity,
                'neededQuantity' => $item->minimum_level, // Assuming minimum_level acts as needed baseline
                'expiryDate' => $item->drug->expiry_date,
                'categoryId' => $item->drug->category, // Assuming category is stored as ID or string
                'description' => $item->drug->description ?? '',
                'type' => $item->drug->form ?? 'Tablet'
            ];
        });

        return $this->sendSuccess($inventory, 'تم تحميل قائمة الأدوية بنجاح.');
    }

    /**
     * GET /api/pharmacist/drugs/all
     * Search global drug database (for adding to inventory)
     */
    public function searchAll(Request $request)
    {
        // Search master Drug table
        $drugs = Drug::select('id', 'name as drugName', 'category')
            ->limit(20)
            ->get();
            
        return $this->sendSuccess($drugs, 'تم جلب قائمة الأدوية العامة.');
    }

    /**
     * POST /api/pharmacist/drugs
     * Add existing global drug to pharmacy inventory
     */
    public function store(Request $request)
    {
        $request->validate([
            'drugId' => 'required|exists:drug,id', // 'drug' table name based on migration
            'quantity' => 'required|integer|min:0',
            'minimumLevel' => 'nullable|integer|min:0'
        ]);

        // Create or Update Inventory record
        $inventory = Inventory::updateOrCreate(
            ['drug_id' => $request->drugId],
            [
                'current_quantity' => $request->quantity,
                'minimum_level' => $request->minimumLevel ?? 50
            ]
        );
        
        return $this->sendSuccess($inventory, 'تم إضافة الدواء للمخزون بنجاح.');
    }

    /**
     * PUT /api/pharmacist/drugs/{id}
     * Update inventory details (stock, needed quantity)
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'integer|min:0',
            'neededQuantity' => 'integer|min:0',
        ]);

        $inventory = Inventory::findOrFail($id);
        
        if ($request->has('quantity')) {
            $inventory->current_quantity = $request->quantity;
        }
        if ($request->has('neededQuantity')) {
            $inventory->minimum_level = $request->neededQuantity;
        }
        
        $inventory->save();

        return $this->sendSuccess($inventory, 'تم تحديث بيانات الدواء بنجاح.');
    }

    /**
     * DELETE /api/pharmacist/drugs/{id}
     * Remove from inventory
     */
    public function destroy($id)
    {
        $inventory = Inventory::findOrFail($id);
        $inventory->delete();
        
        return $this->sendSuccess([], 'تم حذف الدواء من المخزون بنجاح.');
    }

    /**
     * GET /api/pharmacist/drugs/low-stock
     * Get drugs where quantity < minimum_level
     */
    public function lowStock(Request $request)
    {
        $lowStock = Inventory::with('drug')
            ->whereColumn('current_quantity', '<', 'minimum_level')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'drugName' => $item->drug->name,
                    'quantity' => $item->current_quantity,
                    'neededQuantity' => $item->minimum_level,
                    'expiryDate' => $item->drug->expiry_date
                ];
            });

        return $this->sendSuccess($lowStock, 'تم جلب الأدوية منخفضة المخزون.');
    }

    /**
     * GET /api/pharmacist/drugs/search
     * Search within inventory with query params
     */
    public function search(Request $request)
    {
        $query = $request->query('search');
        $catName = $request->query('categoryId'); // Assuming category is passed as name string

        $inventory = Inventory::with('drug')
            ->whereHas('drug', function($q) use ($query, $catName) {
                if ($query) {
                    $q->where('name', 'like', "%{$query}%");
                }
                if ($catName) {
                    $q->where('category', $catName);
                }
            })
            ->get();

        return $this->sendSuccess($inventory, 'تم البحث بنجاح.');
    }
}
