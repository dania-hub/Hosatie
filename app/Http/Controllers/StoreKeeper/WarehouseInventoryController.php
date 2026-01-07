<?php

namespace App\Http\Controllers\StoreKeeper;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\Drug;
use App\Models\InternalSupplyRequest;
use App\Models\InternalSupplyRequestItem;
use App\Models\Pharmacy;

class WarehouseInventoryController extends BaseApiController
{
    // GET /api/storekeeper/drugs
    // يعرض كل الأدوية الموجودة في مخزون المستودع (warehouse inventory) + الأدوية غير المسجلة ولكن المطلوبة في الطلبات
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

        // التأكد من وجود hospital_id للمستخدم
        if (!$user->hospital_id) {
            return response()->json(['message' => 'المستخدم غير مرتبط بمستشفى'], 403);
        }

        // جلب طلبات التوريد الداخلية من نفس المستشفى والتي حالتها "جديد" فقط
        $internalRequests = InternalSupplyRequest::where('status', 'pending') // فقط الطلبات بحالة "جديد"
            ->whereHas('pharmacy', function($query) use ($user) {
                $query->where('hospital_id', $user->hospital_id);
            })
            ->with(['items.drug'])
            ->get();

        // جمع جميع الأدوية من الطلبات مع الكميات المطلوبة
        $requestItems = collect();
        foreach ($internalRequests as $request) {
            foreach ($request->items as $item) {
                if ($item->drug) {
                    $requestItems->push([
                        'drug_id' => $item->drug_id,
                        'requested_qty' => (int)($item->requested_qty ?? 0),
                    ]);
                }
            }
        }

        // تجميع الأدوية حسب drug_id وحساب المجموع المطلوب من جميع الطلبات
        $drugsRequestedQuantities = $requestItems
            ->groupBy('drug_id')
            ->map(function ($items, $drugId) {
                return [
                    'drug_id' => $drugId,
                    'total_requested_qty' => $items->sum('requested_qty')
                ];
            });

        // عرض الأدوية التي تنتمي فقط لمخزن الـ storekeeper
        $items = Inventory::with('drug')
            ->where('warehouse_id', $user->warehouse_id)
            ->get();

        // جلب معرفات الأدوية المسجلة في المستودع
        $registeredDrugIds = $items->pluck('drug_id')->toArray();

        // تحويل النتيجة للفورمات الذي يحتاجه الـ frontend للأدوية المسجلة
        // مع حساب الكمية المحتاجة ديناميكياً من الطلبات
        $registeredDrugs = $items->map(function ($item) use ($drugsRequestedQuantities) {
            $drugId = $item->drug_id;
            $availableQuantity = $item->current_quantity;
            
            // حساب الكمية المطلوبة من الطلبات لهذا الدواء
            $totalRequestedQty = 0;
            if ($drugsRequestedQuantities->has($drugId)) {
                $totalRequestedQty = $drugsRequestedQuantities->get($drugId)['total_requested_qty'];
            }
            
            // الكمية المحتاجة = مجموع الكميات المطلوبة من الطلبات - الكمية المتوفرة
            // إذا كانت النتيجة <= 0، تصبح 0
            $neededQuantity = max(0, $totalRequestedQty - $availableQuantity);
            
            return [
                'id'             => $item->id,
                'drugCode'       => $item->drug->id ?? null,
                'drugName'       => $item->drug->name ?? null,
                'name'           => $item->drug->name ?? null,
                'genericName'    => $item->drug->generic_name ?? null,
                'strength'       => $item->drug->strength ?? null,
                'category'       => $item->drug->category ?? null,
                'status'         => $item->drug->status ?? null,
                'quantity'       => $availableQuantity,
                'neededQuantity' => $neededQuantity, // الكمية المحتاجة المحسوبة ديناميكياً
                'expiryDate'     => $item->drug->expiry_date ? date('Y/m/d', strtotime($item->drug->expiry_date)) : null,
                'isUnregistered' => false, // دواء مسجل في المستودع
            ];
        });

        // جلب الأدوية غير المسجلة ولكن المطلوبة في طلبات التوريد الداخلية
        $unregisteredDrugs = collect();
        
        // تجميع الأدوية غير المسجلة حسب drug_id وحساب المجموع المطلوب
        $unregisteredDrugsData = $drugsRequestedQuantities
            ->whereNotIn('drug_id', $registeredDrugIds) // استبعاد الأدوية المسجلة
            ->values();

        // جلب بيانات الأدوية من قاعدة البيانات
        $drugIds = $unregisteredDrugsData->pluck('drug_id')->toArray();
        
        if (!empty($drugIds)) {
            $drugs = Drug::whereIn('id', $drugIds)->get()->keyBy('id');
            
            $unregisteredDrugs = $unregisteredDrugsData->map(function ($item) use ($drugs) {
                $drug = $drugs->get($item['drug_id']);
                
                if (!$drug) {
                    return null;
                }
                
                return [
                    'id' => 'unregistered_' . $drug->id, // معرف مؤقت للدواء غير المسجل
                    'drugCode' => $drug->id,
                    'drugName' => $drug->name,
                    'name' => $drug->name,
                    'genericName' => $drug->generic_name,
                    'strength' => $drug->strength,
                    'category' => $drug->category,
                    'status' => $drug->status,
                    'quantity' => 0, // الكمية في المخزون = 0 لأنها غير مسجلة
                    'neededQuantity' => $item['total_requested_qty'], // الكمية المطلوبة من جميع الطلبات
                    'expiryDate' => $drug->expiry_date ? date('Y/m/d', strtotime($drug->expiry_date)) : null,
                    'isUnregistered' => true, // دواء غير مسجل في المستودع
                ];
            })->filter(); // إزالة القيم null
        }

        // دمج الأدوية المسجلة وغير المسجلة
        $data = $registeredDrugs->merge($unregisteredDrugs);

        return response()->json($data->values());
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

    // GET /api/storekeeper/drugs/{id}
    // جلب تفاصيل دواء معين من قاعدة البيانات
    public function show(Request $request, $id)
    {
        $user = $request->user();

        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        // التحقق من أن المعرف هو معرف Inventory أو معرف Drug
        // إذا كان يبدأ بـ "unregistered_"، فهو دواء غير مسجل
        if (strpos($id, 'unregistered_') === 0) {
            // دواء غير مسجل - جلب من جدول drugs مباشرة
            $drugId = str_replace('unregistered_', '', $id);
            $drug = Drug::find($drugId);
            
            if (!$drug) {
                return response()->json(['message' => 'الدواء غير موجود'], 404);
            }

            // حساب الكمية المحتاجة من الطلبات
            $internalRequests = InternalSupplyRequest::where('status', 'pending')
                ->whereHas('pharmacy', function($query) use ($user) {
                    $query->where('hospital_id', $user->hospital_id);
                })
                ->with(['items' => function($query) use ($drugId) {
                    $query->where('drug_id', $drugId);
                }])
                ->get();

            $totalRequestedQty = 0;
            foreach ($internalRequests as $request) {
                foreach ($request->items as $item) {
                    $totalRequestedQty += (int)($item->requested_qty ?? 0);
                }
            }

            return response()->json([
                'id' => 'unregistered_' . $drug->id,
                'drugCode' => $drug->id,
                'drugName' => $drug->name,
                'name' => $drug->name,
                'genericName' => $drug->generic_name,
                'strength' => $drug->strength,
                'form' => $drug->form,
                'category' => $drug->category,
                'unit' => $drug->unit,
                'maxMonthlyDose' => $drug->max_monthly_dose,
                'status' => $drug->status,
                'manufacturer' => $drug->manufacturer,
                'country' => $drug->country,
                'utilizationType' => $drug->utilization_type,
                'indications' => $drug->indications,
                'warnings' => $drug->warnings,
                'contraindications' => $drug->contraindications,
                'quantity' => 0,
                'neededQuantity' => $totalRequestedQty,
                'expiryDate' => $drug->expiry_date ? date('Y/m/d', strtotime($drug->expiry_date)) : null,
                'isUnregistered' => true,
            ]);
        } else {
            // دواء مسجل - جلب من Inventory مع معلومات Drug
            $item = Inventory::with('drug')
                ->where('warehouse_id', $user->warehouse_id)
                ->find($id);

            if (!$item || !$item->drug) {
                return response()->json(['message' => 'الدواء غير موجود في المخزون'], 404);
            }

            $drug = $item->drug;

            // حساب الكمية المحتاجة من الطلبات
            $internalRequests = InternalSupplyRequest::where('status', 'pending')
                ->whereHas('pharmacy', function($query) use ($user) {
                    $query->where('hospital_id', $user->hospital_id);
                })
                ->with(['items' => function($query) use ($drug) {
                    $query->where('drug_id', $drug->id);
                }])
                ->get();

            $totalRequestedQty = 0;
            foreach ($internalRequests as $request) {
                foreach ($request->items as $requestItem) {
                    $totalRequestedQty += (int)($requestItem->requested_qty ?? 0);
                }
            }

            $neededQuantity = max(0, $totalRequestedQty - $item->current_quantity);

            return response()->json([
                'id' => $item->id,
                'drugCode' => $drug->id,
                'drugName' => $drug->name,
                'name' => $drug->name,
                'genericName' => $drug->generic_name,
                'strength' => $drug->strength,
                'form' => $drug->form,
                'category' => $drug->category,
                'unit' => $drug->unit,
                'maxMonthlyDose' => $drug->max_monthly_dose,
                'status' => $drug->status,
                'manufacturer' => $drug->manufacturer,
                'country' => $drug->country,
                'utilizationType' => $drug->utilization_type,
                'indications' => $drug->indications,
                'warnings' => $drug->warnings,
                'contraindications' => $drug->contraindications,
                'quantity' => $item->current_quantity,
                'neededQuantity' => $neededQuantity,
                'expiryDate' => $drug->expiry_date ? date('Y/m/d', strtotime($drug->expiry_date)) : null,
                'isUnregistered' => false,
            ]);
        }
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
