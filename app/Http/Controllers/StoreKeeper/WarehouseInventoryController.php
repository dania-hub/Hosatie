<?php

namespace App\Http\Controllers\StoreKeeper;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\Drug;

class WarehouseInventoryController extends BaseApiController
{
    // GET /api/storekeeper/drugs
    // يعرض كل الأدوية الموجودة في مخزون المستودع (warehouse inventory)
    public function index(Request $request)
    {
        $user = $request->user();

        // نتأكد أن المستخدم مدير مخزن
        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        // نعتبر أن كل سطر له warehouse_id يخص مخزن المستشفى
        $items = Inventory::with('drug')
            ->whereNotNull('warehouse_id')
            ->get();

        // تحويل النتيجة للفورمات الذي يحتاجه الـ frontend
        $data = $items->map(function ($item) {
            return [
                'id'             => $item->id,
                'drugCode'       => $item->drug->code ?? null,
                'drugName'       => $item->drug->name ?? null,
                'quantity'       => $item->current_quantity,  // الكمية المتوفرة في المخزن
                'neededQuantity' => $item->minimum_level,     // الحد الأدنى/المستهدف
                'expiryDate'     => null, // جدول inventory الحالي لا يحتوي expiry_date
            ];
        });

        return response()->json($data);
    }

    // GET /api/storekeeper/drugs/all
    // قائمة الأدوية التعريفية لاختيارها في طلب التوريد الخارجي (external)
    public function allDrugs(Request $request)
    {
        $user = $request->user();

        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $drugs = Drug::select('id', 'code as drugCode', 'name as drugName')
            ->orderBy('name')
            ->get();

        return response()->json($drugs);
    }

    // هذه الثلاثة فقط لأن الواجهة الحالية تناديها، لكن منطقك الحقيقي
    // يعتمد على external/internal supply وليس على CRUD مباشر.
    // يمكنك لاحقاً تعطيلها أو حصرها.

    // POST /api/storekeeper/drugs
    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $validated = $request->validate([
            'drug_id'         => 'required|exists:drug,id',
            'warehouse_id'    => 'required|integer',
            'current_quantity'=> 'required|integer|min:0',
            'minimum_level'   => 'nullable|integer|min:0',
        ]);

        $item = Inventory::create([
            'drug_id'         => $validated['drug_id'],
            'warehouse_id'    => $validated['warehouse_id'],
            'current_quantity'=> $validated['current_quantity'],
            'minimum_level'   => $validated['minimum_level'] ?? 50,
        ]);

        return response()->json($item->load('drug'), 201);
    }

    // PUT /api/storekeeper/drugs/{id}
    public function update(Request $request, $id)
    {
        $user = $request->user();
        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $validated = $request->validate([
            'current_quantity' => 'nullable|integer|min:0',
            'minimum_level'    => 'nullable|integer|min:0',
        ]);

        $item = Inventory::with('drug')
            ->whereNotNull('warehouse_id')
            ->findOrFail($id);

        if (isset($validated['current_quantity'])) {
            $item->current_quantity = $validated['current_quantity'];
        }
        if (isset($validated['minimum_level'])) {
            $item->minimum_level = $validated['minimum_level'];
        }

        $item->save();

        return response()->json([
            'id'             => $item->id,
            'drugCode'       => $item->drug->code ?? null,
            'drugName'       => $item->drug->name ?? null,
            'quantity'       => $item->current_quantity,
            'neededQuantity' => $item->minimum_level,
            'expiryDate'     => null,
        ]);
    }

    // DELETE /api/storekeeper/drugs/{id}
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $item = Inventory::whereNotNull('warehouse_id')->findOrFail($id);
        $item->delete();

        return response()->json(['message' => 'تم حذف الصنف من مخزون المستودع']);
    }
}
