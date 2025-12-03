<?php

namespace App\Http\Controllers\DepartmentAdmin;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;

class ShipmentDepartmentAdminController extends BaseApiController
{
    /**
     * GET /api/department-admin/shipments
     * List incoming shipments for this department
     */
    public function index(Request $request)
    {
        // Mock Data (Replace with Shipment::where('department_id', ...)->get())
        $shipments = [
            [
                'id' => 1,
                'shipmentNumber' => 'S-509',
                'requestDate' => now()->subDays(2)->toIso8601String(),
                'status' => 'قيد التجهيز',
                'itemCount' => 5
            ],
            [
                'id' => 2,
                'shipmentNumber' => 'S-510',
                'requestDate' => now()->subDays(5)->toIso8601String(),
                'status' => 'تم الإستلام',
                'itemCount' => 10
            ]
        ];

        return $this->sendSuccess($shipments, 'تم جلب الشحنات الواردة بنجاح.');
    }

    /**
     * GET /api/department-admin/shipments/{id}
     * Show details of one shipment
     */
    public function show($id)
    {
        // Mock Data
        $shipment = [
            'id' => $id,
            'shipmentNumber' => 'S-509',
            'status' => 'قيد التجهيز',
            'items' => [
                ['id' => 1, 'drugName' => 'Panadol', 'quantity' => 50, 'unit' => 'Box'],
                ['id' => 2, 'drugName' => 'Brufen', 'quantity' => 30, 'unit' => 'Box'],
            ]
        ];

        return $this->sendSuccess($shipment, 'تم جلب تفاصيل الشحنة.');
    }

    /**
     * POST /api/department-admin/shipments/{id}/confirm
     * Confirm receipt and update stock
     */
    public function confirm(Request $request, $id)
    {
        // Logic:
        // 1. Find Shipment by ID
        // 2. Update status to 'received'
        // 3. Loop through items and increment Drug Stock
        
        return $this->sendSuccess([], 'تم تأكيد استلام الشحنة وتحديث المخزون.');
    }
}
