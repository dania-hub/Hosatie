<?php

namespace App\Http\Controllers\StoreKeeper;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ExternalSupplyRequest;
use App\Models\ExternalSupplyRequestItem;
use App\Models\AuditLog;

class ExternalSupplyRequestController extends BaseApiController
{
    // POST /api/storekeeper/supply-requests
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $validated = $request->validate([
            'supplier_id'             => 'required|exists:supplier,id',
            'items'                   => 'required|array|min:1',
            'items.*.drug_id'         => 'required|exists:drug,id',
            'items.*.requested_qty'   => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            // إنشاء الطلب الرئيسي
            $externalRequest = ExternalSupplyRequest::create([
                'hospital_id' => $user->hospital_id,
                'supplier_id' => $validated['supplier_id'],
                'requested_by'=> $user->id,
                'status'      => 'pending',
            ]);

            // عناصر الطلب
            foreach ($validated['items'] as $item) {
                ExternalSupplyRequestItem::create([
                    'request_id'    => $externalRequest->id,
                    'drug_id'       => $item['drug_id'],
                    'requested_qty' => $item['requested_qty'],
                    'approved_qty'  => null,
                    'fulfilled_qty' => null,
                ]);
            }

            DB::commit();

            // 🟢 تسجيل العملية في audit_log
            AuditLog::create([
                'user_id'    => $user->id,
                'action'     => 'create_external_supply_request',
                'table_name' => 'external_supply_request',
                'record_id'  => $externalRequest->id,
                'old_values' => null,
                'new_values' => json_encode([
                    'supplier_id' => $validated['supplier_id'],
                    'items'       => $validated['items'],
                    'status'      => 'pending',
                ]),
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'تم إنشاء طلب التوريد الخارجي بنجاح',
                'data'    => $externalRequest->load('items.drug', 'supplier'),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'فشل في إنشاء طلب التوريد الخارجي',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
