<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Inventory;

class InventorySuperController extends BaseApiController
{
    public function index(Request $request)
    {
        $type = $request->query('type', 'hospital'); // Default to hospital

        $query = Inventory::with(['drug', 'warehouse.hospital', 'pharmacy.hospital', 'supplier']);

        if ($type === 'supplier') {
            $query->whereNotNull('supplier_id');
        } else {
            $query->whereNotNull('warehouse_id');
        }

        $inventories = $query->get()
            ->map(function ($inventory) use ($type) {
                $entityName = 'N/A';
                
                if ($type === 'supplier') {
                    $entityName = $inventory->supplier ? $inventory->supplier->name : 'N/A';
                } else {
                    if ($inventory->warehouse && $inventory->warehouse->hospital) {
                        $entityName = $inventory->warehouse->hospital->name;
                    } elseif ($inventory->pharmacy && $inventory->pharmacy->hospital) {
                        $entityName = $inventory->pharmacy->hospital->name;
                    }
                }

                $drugName = 'N/A';
                $strength = 'N/A';
                if ($inventory->drug) {
                    $drugName = $inventory->drug->name;
                    $strength = $inventory->drug->strength ?? 'N/A';
                }

                return [
                    'drug_name' => $drugName,
                    'strength' => $strength,
                    'current_quantity' => (int)$inventory->current_quantity,
                    'needed_quantity' => (int)($inventory->minimum_level ?? 0),
                    'units_per_box' => $inventory->drug && $inventory->drug->units_per_box ? (int)$inventory->drug->units_per_box : 1,
                    'unit' => $inventory->drug && $inventory->drug->unit ? $inventory->drug->unit : 'حبة',
                    'entity_name' => $entityName, // Generic name for Hospital or Supplier
                ];
            });

        // Group by Drug Name, Strength and Entity Name to merge duplicates
        $groupedInventories = $inventories->groupBy(function ($item) {
            return $item['drug_name'] . '_' . $item['strength'] . '_' . $item['entity_name'];
        })->map(function ($group) {
            $first = $group->first();
            return [
                'id' => md5($first['drug_name'] . $first['strength'] . $first['entity_name']), // Generate a unique ID
                'drug_name' => $first['drug_name'],
                'strength' => $first['strength'],
                'hospital_name' => $first['entity_name'], // Keep key as hospital_name for frontend compatibility, or change frontend
                'current_quantity' => $group->sum('current_quantity'),
                'needed_quantity' => $group->sum('needed_quantity'),
                'units_per_box' => $first['units_per_box'],
                'unit' => $first['unit'],
                'current_quantity_boxes' => floor($group->sum('current_quantity') / ($first['units_per_box'] ?: 1)),
                'current_quantity_remainder' => $group->sum('current_quantity') % ($first['units_per_box'] ?: 1),
                'needed_quantity_boxes' => floor($group->sum('needed_quantity') / ($first['units_per_box'] ?: 1)),
            ];
        })->values();

        return $this->sendSuccess($groupedInventories, 'تم جلب المخزون بنجاح');
    }
}
