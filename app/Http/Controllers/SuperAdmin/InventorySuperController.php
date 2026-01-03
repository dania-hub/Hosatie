<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Inventory;

class InventorySuperController extends BaseApiController
{
    public function index()
    {
        $inventories = Inventory::with(['drug', 'warehouse.hospital', 'pharmacy.hospital'])
            ->whereNotNull('warehouse_id')
            ->get()
            ->map(function ($inventory) {
                $hospitalName = 'N/A';
                if ($inventory->warehouse && $inventory->warehouse->hospital) {
                    $hospitalName = $inventory->warehouse->hospital->name;
                } elseif ($inventory->pharmacy && $inventory->pharmacy->hospital) {
                    $hospitalName = $inventory->pharmacy->hospital->name;
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
                    'hospital_name' => $hospitalName,
                ];
            });

        // Group by Drug Name, Strength and Hospital Name to merge duplicates
        $groupedInventories = $inventories->groupBy(function ($item) {
            return $item['drug_name'] . '_' . $item['strength'] . '_' . $item['hospital_name'];
        })->map(function ($group) {
            $first = $group->first();
            return [
                'id' => md5($first['drug_name'] . $first['strength'] . $first['hospital_name']), // Generate a unique ID
                'drug_name' => $first['drug_name'],
                'strength' => $first['strength'],
                'hospital_name' => $first['hospital_name'],
                'current_quantity' => $group->sum('current_quantity'),
                'needed_quantity' => $group->sum('needed_quantity'),
            ];
        })->values();

        return $this->sendSuccess($groupedInventories, 'تم جلب المخزون بنجاح');
    }
}
