<?php

namespace App\Http\Controllers\DepartmentAdmin;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;

class SupplyRequestControllerDepartmentAdmin extends BaseApiController
{
    /**
     * POST /api/department-admin/supply-requests
     * Create a new request for medicines
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.drugId' => 'required|exists:drug,id', // Assuming 'drug' table
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string'
        ]);

        // Logic:
        // 1. Create SupplyRequest (status: pending)
        // 2. Create SupplyRequestItems
        
        $mockResponse = [
            'id' => rand(100, 999),
            'requestNumber' => 'REQ-' . date('Y') . '-' . rand(100, 999),
            'status' => 'قيد المراجعة',
            'itemCount' => count($request->items)
        ];

        return $this->sendSuccess($mockResponse, 'تم إنشاء طلب التوريد بنجاح.');
    }
}
