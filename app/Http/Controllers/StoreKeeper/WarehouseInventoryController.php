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

        // التأكد من وجود warehouse_id للمستخدم
        if (!$user->warehouse_id) {
            return response()->json(['message' => 'المستخدم غير مرتبط بمخزن'], 403);
        }

        // عرض الأدوية التي تنتمي فقط لمخزن الـ storekeeper
        $items = Inventory::with('drug')
            ->where('warehouse_id', $user->warehouse_id)
            ->get();

        // تحويل النتيجة للفورمات الذي يحتاجه الـ frontend
        $data = $items->map(function ($item) {
            return [
                'id'             => $item->id,
                'drugCode'       => $item->drug->id ?? null, // استخدام ID كرمز مؤقت
                'drugName'       => $item->drug->name ?? null,
                'name'           => $item->drug->name ?? null, // للتوافق مع pharmacist
                'genericName'    => $item->drug->generic_name ?? null,
                'strength'       => $item->drug->strength ?? null,
                'category'       => $item->drug->category ?? null,
                'status'         => $item->drug->status ?? null,
                'quantity'       => $item->current_quantity,  // الكمية المتوفرة في المخزن
                'neededQuantity' => $item->minimum_level,     // الحد الأدنى/المستهدف
                'expiryDate'     => $item->drug->expiry_date ? date('Y/m/d', strtotime($item->drug->expiry_date)) : null,
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

        $drugs = Drug::select('id', 'id as drugCode', 'name as drugName', 'generic_name as genericName', 'strength', 'category', 'form', 'unit', 'max_monthly_dose as maxMonthlyDose', 'status', 'manufacturer', 'country', 'utilization_type as utilizationType')
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

        // التأكد من وجود warehouse_id للمستخدم
        if (!$user->warehouse_id) {
            return response()->json(['message' => 'المستخدم غير مرتبط بمخزن'], 403);
        }

        $validated = $request->validate([
            'drug_id'         => 'required|exists:drugs,id',
            'current_quantity'=> 'required|integer|min:0',
            'minimum_level'   => 'nullable|integer|min:0',
        ]);

        // استخدام warehouse_id الخاص بالـ storekeeper تلقائياً
        $item = Inventory::create([
            'drug_id'         => $validated['drug_id'],
            'warehouse_id'    => $user->warehouse_id,
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

        // التأكد من وجود warehouse_id للمستخدم
        if (!$user->warehouse_id) {
            return response()->json(['message' => 'المستخدم غير مرتبط بمخزن'], 403);
        }

        $validated = $request->validate([
            'current_quantity' => 'nullable|integer|min:0',
            'minimum_level'    => 'nullable|integer|min:0',
        ]);

        $item = Inventory::with('drug')
            ->where('warehouse_id', $user->warehouse_id)
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
            'drugCode'       => $item->drug->id ?? null, // استخدام ID كرمز مؤقت
            'drugName'       => $item->drug->name ?? null,
            'name'           => $item->drug->name ?? null,
            'genericName'    => $item->drug->generic_name ?? null,
            'strength'       => $item->drug->strength ?? null,
            'category'       => $item->drug->category ?? null,
            'status'         => $item->drug->status ?? null,
            'quantity'       => $item->current_quantity,
            'neededQuantity' => $item->minimum_level,
            'expiryDate'     => $item->drug->expiry_date ? date('Y/m/d', strtotime($item->drug->expiry_date)) : null,
        ]);
    }

    // DELETE /api/storekeeper/drugs/{id}
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        // التأكد من وجود warehouse_id للمستخدم
        if (!$user->warehouse_id) {
            return response()->json(['message' => 'المستخدم غير مرتبط بمخزن'], 403);
        }

        $item = Inventory::where('warehouse_id', $user->warehouse_id)->findOrFail($id);
        $item->delete();

        return response()->json(['message' => 'تم حذف الصنف من مخزون المستودع']);
    }
}
