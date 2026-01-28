<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;

class InventorySuperController extends BaseApiController
{
    public function index(Request $request)
    {
        try {
            $type = $request->query('type', 'hospital');

            $query = Inventory::with(['drug', 'warehouse.hospital', 'pharmacy.hospital', 'supplier']);

            if ($type === 'supplier') {
                $query->whereNotNull('supplier_id');
            } else {
                $query->whereNotNull('warehouse_id')
                      ->orWhereNotNull('pharmacy_id');
            }

            $inventories = $query->get();

            // Fetch pending requests
            $pendingRequested = collect();
            $internalPendingRequested = collect();

            if ($type === 'supplier') {
                $pendingRequested = \App\Models\ExternalSupplyRequestItem::join('external_supply_requests', 'external_supply_request_items.request_id', '=', 'external_supply_requests.id')
                    ->whereIn('external_supply_requests.status', ['pending', 'approved'])
                    ->select('external_supply_request_items.drug_id', 'external_supply_requests.supplier_id', DB::raw('SUM(external_supply_request_items.requested_qty) as total_requested'))
                    ->groupBy('external_supply_request_items.drug_id', 'external_supply_requests.supplier_id')
                    ->get()
                    ->groupBy('drug_id');
            } else {
                // External requests from warehouses to suppliers
                $pendingRequested = \App\Models\ExternalSupplyRequestItem::join('external_supply_requests', 'external_supply_request_items.request_id', '=', 'external_supply_requests.id')
                    ->whereIn('external_supply_requests.status', ['pending', 'approved'])
                    ->select('external_supply_request_items.drug_id', 'external_supply_requests.hospital_id', DB::raw('SUM(external_supply_request_items.requested_qty) as total_requested'))
                    ->groupBy('external_supply_request_items.drug_id', 'external_supply_requests.hospital_id')
                    ->get()
                    ->groupBy('drug_id');

                // Internal requests from pharmacies to warehouses
                $internalPendingRequested = \App\Models\InternalSupplyRequestItem::join('internal_supply_requests', 'internal_supply_request_items.request_id', '=', 'internal_supply_requests.id')
                    ->whereIn('internal_supply_requests.status', ['pending', 'approved'])
                    ->select('internal_supply_request_items.drug_id', 'internal_supply_requests.pharmacy_id', DB::raw('SUM(internal_supply_request_items.requested_qty) as total_requested'))
                    ->groupBy('internal_supply_request_items.drug_id', 'internal_supply_requests.pharmacy_id')
                    ->get()
                    ->groupBy('drug_id');
            }

            $rawItems = $inventories->map(function ($inventory) use ($type, $pendingRequested, $internalPendingRequested) {
                $entityName = 'N/A';
                $entityId = null;
                $totalRequested = 0;

                if ($type === 'supplier') {
                    $entityName = $inventory->supplier ? $inventory->supplier->name : 'N/A';
                    $entityId = $inventory->supplier_id;

                    if ($inventory->drug_id && $entityId && isset($pendingRequested[$inventory->drug_id])) {
                        $req = $pendingRequested[$inventory->drug_id]->firstWhere('supplier_id', $entityId);
                        if ($req) $totalRequested = (int)$req->total_requested;
                    }
                } else {
                    if ($inventory->warehouse && $inventory->warehouse->hospital) {
                        $entityName = $inventory->warehouse->hospital->name . ' (مخزن)';
                        $entityId = 'w_' . $inventory->warehouse_id;
                        $hospitalId = $inventory->warehouse->hospital_id;

                        if ($inventory->drug_id && $hospitalId && isset($pendingRequested[$inventory->drug_id])) {
                            $req = $pendingRequested[$inventory->drug_id]->firstWhere('hospital_id', $hospitalId);
                            if ($req) $totalRequested = (int)$req->total_requested;
                        }
                    } elseif ($inventory->pharmacy && $inventory->pharmacy->hospital) {
                        $entityName = $inventory->pharmacy->hospital->name . ' (صيدلية)';
                        $entityId = 'p_' . $inventory->pharmacy_id;
                        $pharmacyId = $inventory->pharmacy_id;

                        if ($inventory->drug_id && $pharmacyId && isset($internalPendingRequested[$inventory->drug_id])) {
                            $req = $internalPendingRequested[$inventory->drug_id]->firstWhere('pharmacy_id', $pharmacyId);
                            if ($req) $totalRequested = (int)$req->total_requested;
                        }
                    }
                }

                $drugName = $inventory->drug ? $inventory->drug->name : 'N/A';
                $strength = $inventory->drug ? ($inventory->drug->strength ?? 'N/A') : 'N/A';
                $drugId = $inventory->drug_id;

                return [
                    'id' => $inventory->id,
                    'drug_id' => $drugId,
                    'drug_name' => $drugName,
                    'strength' => $strength,
                    'entity_id' => $entityId,
                    'entity_name' => $entityName,
                    'current_quantity' => (int)$inventory->current_quantity,
                    'total_requested' => $totalRequested,
                    'units_per_box' => $inventory->drug && $inventory->drug->units_per_box ? (int)$inventory->drug->units_per_box : 1,
                    'unit' => $inventory->drug && $inventory->drug->unit ? $inventory->drug->unit : 'حبة',
                ];
            });

            // Group by Drug (Name + Strength)
            $grouped = $rawItems->groupBy(function ($item) {
                return $item['drug_name'] . '_' . $item['strength'];
            })->map(function ($group) {
                $first = $group->first();
                $unitsPerBox = $first['units_per_box'] ?: 1;
                
                $details = $group->groupBy('entity_id')->map(function ($subGroup) use ($unitsPerBox) {
                    $firstSub = $subGroup->first();
                    $currentQty = $subGroup->sum('current_quantity');
                    $totalRequested = $firstSub['total_requested'] ?? 0;
                    $requiredQty = max(0, $totalRequested - $currentQty);
                    
                    return [
                        'entity_id' => $firstSub['entity_id'],
                        'entity_name' => $firstSub['entity_name'],
                        'current_quantity' => $currentQty,
                        'required_quantity' => $requiredQty,
                        'total_requested' => $totalRequested,
                        'current_quantity_boxes' => floor($currentQty / $unitsPerBox),
                        'current_quantity_remainder' => $currentQty % $unitsPerBox,
                        'total_requested_boxes' => floor($totalRequested / $unitsPerBox),
                        'total_requested_remainder' => $totalRequested % $unitsPerBox,
                        'required_quantity_boxes' => floor($requiredQty / $unitsPerBox),
                        'required_quantity_remainder' => $requiredQty % $unitsPerBox,
                    ];
                })->values();

                $totalCurrent = $details->sum('current_quantity');
                $totalRequired = $details->sum('required_quantity');
                $totalRequested = $details->sum('total_requested');

                return [
                    'id' => md5($first['drug_name'] . $first['strength']),
                    'drug_name' => $first['drug_name'],
                    'strength' => $first['strength'],
                    'unit' => $first['unit'],
                    'units_per_box' => $unitsPerBox,
                    'total_current_quantity' => $totalCurrent,
                    'total_required_quantity' => $totalRequired,
                    'total_requested_quantity' => $totalRequested,
                    'total_current_boxes' => floor($totalCurrent / $unitsPerBox),
                    'total_current_remainder' => $totalCurrent % $unitsPerBox,
                    'total_required_boxes' => floor($totalRequired / $unitsPerBox),
                    'total_required_remainder' => $totalRequired % $unitsPerBox,
                    'total_requested_boxes' => floor($totalRequested / $unitsPerBox),
                    'total_requested_remainder' => $totalRequested % $unitsPerBox,
                    'details' => $details
                ];
            })->values();

            return $this->sendSuccess($grouped, 'تم جلب المخزون بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Inventory Super Admin Index Error');
        }
    }
}
