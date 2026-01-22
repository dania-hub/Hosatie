<?php

namespace App\Http\Controllers\StoreKeeper;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\Drug;
use App\Models\InternalSupplyRequest;
use App\Models\InternalSupplyRequestItem;
use App\Models\Pharmacy;
use App\Models\AuditLog;
use App\Models\Warehouse;
use App\Models\Hospital;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WarehouseInventoryController extends BaseApiController
{
    // GET /api/storekeeper/drugs
    // يعرض كل الأدوية الموجودة في مخزون المستودع (warehouse inventory) + الأدوية غير المسجلة ولكن المطلوبة في الطلبات
    public function index(Request $request)
    {
        $user = $request->user();

        // نتأكد أن المستخدم مدير مخزن
        if ($user->type !== 'warehouse_manager' && $user->type !== 'super_admin') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        // التأكد من وجود warehouse_id و hospital_id
        $warehouse_id = $user->warehouse_id;
        $hospital_id = $user->hospital_id;

        if ($user->type === 'super_admin') {
            $warehouse_id = $warehouse_id ?: $request->input('warehouse_id') ?: (\App\Models\Warehouse::first()?->id);
            $hospital_id = $hospital_id ?: $request->input('hospital_id') ?: (\App\Models\Hospital::first()?->id);
        }

        if (!$warehouse_id) {
            return response()->json(['message' => 'المستخدم غير مرتبط بمخزن'], 403);
        }

        if (!$hospital_id) {
            return response()->json(['message' => 'المستخدم غير مرتبط بمستشفى'], 403);
        }

        // جمع معلومات الأدوية المنتهية قبل التصفير وحفظها في audit_log
        $today = Carbon::now()->format('Y-m-d');
        
        // جلب الأدوية المنتهية الصلاحية من المخزون قبل التصفير
        $expiredInventories = DB::table('inventories')
            ->join('drugs', 'inventories.drug_id', '=', 'drugs.id')
            ->where('inventories.warehouse_id', $warehouse_id)
            ->where('inventories.current_quantity', '>', 0)
            ->whereNotNull('inventories.expiry_date')
            ->whereRaw("DATE(inventories.expiry_date) <= ?", [$today])
            ->select(
                'inventories.id as inventory_id',
                'drugs.id as drug_id',
                'drugs.name as drug_name',
                'drugs.strength',
                'inventories.current_quantity',
                'inventories.expiry_date'
            )
            ->get();
        
        // حفظ الأدوية المُصفرة في audit_log (إذا لم تكن محفوظة مسبقاً)
        foreach ($expiredInventories as $expired) {
            $drugExpiryDate = $expired->expiry_date ? date('Y/m/d', strtotime($expired->expiry_date)) : null;
            
            // جلب جميع السجلات المتعلقة بهذا الدواء والمستودع
            $existingLogs = AuditLog::where('action', 'drug_expired_zeroed')
                ->where('hospital_id', $hospital_id)
                ->where('table_name', 'inventories')
                ->get();
            
            $exists = false;
            foreach ($existingLogs as $log) {
                $newValues = json_decode($log->new_values, true);
                if ($newValues && 
                    isset($newValues['drugId']) && $newValues['drugId'] == $expired->drug_id &&
                    isset($newValues['warehouseId']) && $newValues['warehouseId'] == $warehouse_id &&
                    isset($newValues['expiryDate']) && $newValues['expiryDate'] == $drugExpiryDate) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                // حفظ في audit_log
                AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => $hospital_id,
                    'action' => 'drug_expired_zeroed',
                    'table_name' => 'inventories',
                    'record_id' => $expired->inventory_id,
                    'old_values' => json_encode([
                        'quantity' => $expired->current_quantity,
                    ]),
                    'new_values' => json_encode([
                        'drugName' => $expired->drug_name,
                        'drugId' => $expired->drug_id,
                        'strength' => $expired->strength ?? null,
                        'quantity' => $expired->current_quantity,
                        'expiryDate' => $drugExpiryDate,
                        'warehouseId' => $warehouse_id,
                    ]),
                    'ip_address' => $request->ip() ?? request()->ip(),
                ]);

                // تصفير الكمية فعلياً في قاعدة البيانات
                DB::table('inventories')
                    ->where('id', $expired->inventory_id)
                    ->update(['current_quantity' => 0]);
            }
        }

        // جلب طلبات التوريد الداخلية من نفس المستشفى والتي حالتها "جديد" فقط
        $internalRequests = InternalSupplyRequest::where('status', 'pending') // فقط الطلبات بحالة "جديد"
            ->whereHas('pharmacy', function($query) use ($hospital_id) {
                $query->where('hospital_id', $hospital_id);
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

        // عرض الأدوية التي تنتمي فقط لمخزن الـ storekeeper وتليست مؤرشفة
        $items = Inventory::with('drug')
            ->where('warehouse_id', $warehouse_id)
            ->whereHas('drug', function($q) {
                $q->where('status', '!=', Drug::STATUS_ARCHIVED);
            })
            ->get();

        // جلب معرفات الأدوية المسجلة في المستودع
        $registeredDrugIds = $items->pluck('drug_id')->toArray();

        // تجميع الأدوية حسب drug_id وحساب المجموع وتفاصيل الدفعات
        $registeredDrugs = $items->groupBy('drug_id')->map(function ($group) use ($drugsRequestedQuantities) {
            $firstItem = $group->first();
            $drug = $firstItem->drug;
            
            $totalQuantity = $group->sum('current_quantity');
            
            // تفاصيل الدفعات (الشحنات)
            $batches = $group->map(function ($inv) {
                return [
                    'batchNumber' => $inv->batch_number ?? 'غير محدد',
                    'expiryDate' => $inv->expiry_date ? date('Y/m/d', strtotime($inv->expiry_date)) : 'غير محدد',
                    'quantity' => $inv->current_quantity,
                ];
            })->values();
            
            // حساب الكمية المطلوبة من الطلبات لهذا الدواء
            $totalRequestedQty = 0;
            if ($drugsRequestedQuantities->has($drug->id)) {
                $totalRequestedQty = $drugsRequestedQuantities->get($drug->id)['total_requested_qty'];
            }
            
            // الكمية المحتاجة = مجموع الكميات المطلوبة من الطلبات - الكمية المتوفرة
            // إذا كانت النتيجة <= 0، تصبح 0
            $neededQuantity = max(0, $totalRequestedQty - $totalQuantity);
            
            // تحديث minimum_level في قاعدة البيانات تلقائياً بالقيمة المحسوبة
            foreach ($group as $item) {
                if ($item->minimum_level != $neededQuantity) {
                    $item->minimum_level = $neededQuantity;
                    $item->save();
                }
            }
            
            return [
                'id'             => $drug->id, // Use Drug ID for grouping
                'drugCode'       => $drug->id ?? null,
                'drugName'       => $drug->name ?? null,
                'name'           => $drug->name ?? null,
                'genericName'    => $drug->generic_name ?? null,
                'strength'       => $drug->strength ?? null,
                'category'       => $drug->category ?? null,
                'status'         => $drug->status ?? null,
                'quantity'       => $totalQuantity,
                'neededQuantity' => $neededQuantity, // الكمية المحتاجة المحسوبة ديناميكياً
                'batches'        => $batches, // قائمة الدفعات وتواريخ الصلاحية
                'expiryDate'     => $group->min('expiry_date') ? date('Y/m/d', strtotime($group->min('expiry_date'))) : null, // أقرب تاريخ انتهاء
                'isUnregistered' => false, // دواء مسجل في المستودع
                'units_per_box'  => $drug->units_per_box ?? 1,
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
                    'isUnregistered' => true, // دواء غير مسجل في المستودع
                    'units_per_box' => $drug->units_per_box ?? 1,
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

        $hospital = Hospital::find($user->hospital_id);
        $supplierId = $hospital->supplier_id ?? null;

        $drugs = Drug::select('id', 'id as drugCode', 'name as drugName', 'generic_name as genericName', 'strength', 'category', 'form', 'unit', 'max_monthly_dose as maxMonthlyDose', 'status', 'manufacturer', 'country', 'utilization_type as utilizationType', 'units_per_box')
            ->where('status', '!=', Drug::STATUS_ARCHIVED)
            ->where(function($query) use ($supplierId) {
                $query->where('status', Drug::STATUS_AVAILABLE)
                    ->orWhere(function($q) use ($supplierId) {
                        $q->where('status', Drug::STATUS_PHASING_OUT);
                        if ($supplierId) {
                            $q->whereHas('inventories', function($inv) use ($supplierId) {
                                $inv->where('supplier_id', $supplierId)
                                    ->where('current_quantity', '>', 0);
                            });
                        } else {
                            // If no specific supplier, check if ANY supplier has it
                            $q->whereHas('inventories', function($inv) {
                                $inv->whereNotNull('supplier_id')
                                    ->where('current_quantity', '>', 0);
                            });
                        }
                    });
            })
            ->orderBy('name')
            ->get();

        return response()->json($drugs);
    }

    // GET /api/storekeeper/drugs/{id}
    // جلب تفاصيل دواء معين من قاعدة البيانات
    public function show(Request $request, $id)
    {
        $user = $request->user();

        if ($user->type !== 'warehouse_manager' && $user->type !== 'super_admin') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        // التأكد من وجود warehouse_id و hospital_id
        $warehouse_id = $user->warehouse_id;
        $hospital_id = $user->hospital_id;

        if ($user->type === 'super_admin') {
            $warehouse_id = $warehouse_id ?: $request->input('warehouse_id') ?: (\App\Models\Warehouse::first()?->id);
            $hospital_id = $hospital_id ?: $request->input('hospital_id') ?: (\App\Models\Hospital::first()?->id);
        }

        if (!$warehouse_id) {
            return response()->json(['message' => 'المستخدم غير مرتبط بمخزن'], 403);
        }

        if (!$hospital_id) {
            return response()->json(['message' => 'المستخدم غير مرتبط بمستشفى'], 403);
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
                ->whereHas('pharmacy', function($query) use ($hospital_id) {
                    $query->where('hospital_id', $hospital_id);
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
                'isUnregistered' => true,
                'units_per_box' => $drug->units_per_box ?? 1,
            ]);
        } else {
            // دواء مسجل - جلب من Inventory مع معلومات Drug
            $items = Inventory::with('drug')
                ->where('warehouse_id', $warehouse_id)
                ->where('drug_id', $id)
                ->whereHas('drug', function($q) {
                    $q->where('status', '!=', Drug::STATUS_ARCHIVED);
                })
                ->get();

            if ($items->isEmpty() || !$items->first()->drug) {
                return response()->json(['message' => 'الدواء غير موجود في المخزون'], 404);
            }

            $drug = $items->first()->drug;
            $totalQuantity = $items->sum('current_quantity');

            // تفاصيل الدفعات (الشحنات)
            $batches = $items->map(function ($inv) {
                return [
                    'batchNumber' => $inv->batch_number ?? 'غير محدد',
                    'expiryDate' => $inv->expiry_date ? date('Y/m/d', strtotime($inv->expiry_date)) : 'غير محدد',
                    'quantity' => $inv->current_quantity,
                ];
            })->values();

            // حساب الكمية المحتاجة من الطلبات
            $internalRequests = InternalSupplyRequest::where('status', 'pending')
                ->whereHas('pharmacy', function($query) use ($hospital_id) {
                    $query->where('hospital_id', $hospital_id);
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

            $neededQuantity = max(0, $totalRequestedQty - $totalQuantity);
            
            // تحديث minimum_level في قاعدة البيانات تلقائياً بالقيمة المحسوبة
            foreach ($items as $item) {
                if ($item->minimum_level != $neededQuantity) {
                    $item->minimum_level = $neededQuantity;
                    $item->save();
                }
            }

            return response()->json([
                'id' => $drug->id,
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
                'quantity' => $totalQuantity,
                'neededQuantity' => $neededQuantity,
                'batches' => $batches,
                'isUnregistered' => false,
                'units_per_box' => $drug->units_per_box ?? 1,
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

        // التحقق من الأرشفة التلقائية بعد التحديث
        try {
            $drug = $item->drug;
            if ($drug && $drug->status === Drug::STATUS_PHASING_OUT) {
                $drug->checkAndArchiveIfNoStock();
            }
        } catch (\Exception $e) {
            \Log::error('Auto-archiving check failed in WarehouseInventoryController@update', ['error' => $e->getMessage()]);
        }

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
            'expiryDate'     => $item->expiry_date ? date('Y/m/d', strtotime($item->expiry_date)) : null,
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

    /**
     * دالة مساعدة لجلب الأدوية المُصفرة من audit_log
     */
    private function getExpiredDrugsFromAuditLog($hospitalId, $warehouseId)
    {
        // جلب جميع inventory_ids التي تنتمي لنفس المستودع
        $warehouseInventoryIds = Inventory::where('warehouse_id', $warehouseId)
            ->pluck('id')
            ->toArray();
        
        if (empty($warehouseInventoryIds)) {
            return collect();
        }
        
        $expiredDrugsLogs = AuditLog::where('action', 'drug_expired_zeroed')
            ->where('hospital_id', $hospitalId)
            ->whereIn('record_id', $warehouseInventoryIds) // الأدوية من نفس المستودع فقط
            ->orderBy('created_at', 'desc')
            ->get();

        // 1. تجميع معرفات الأدوية التي تفتقد للتركيز (strength)
        $drugIdsToFetch = [];
        foreach ($expiredDrugsLogs as $log) {
            $newValues = json_decode($log->new_values, true);
            if ($newValues && isset($newValues['drugName'])) {
                // إذا كان التركيز غير موجود أو فارغ ولكن لدينا drugId
                if (empty($newValues['strength']) && !empty($newValues['drugId'])) {
                    $drugIdsToFetch[] = $newValues['drugId'];
                }
            }
        }

        // 2. جلب التركيزات الناقصة من قاعدة البيانات دفعة واحدة
        $drugStrengths = [];
        if (!empty($drugIdsToFetch)) {
            $drugStrengths = Drug::whereIn('id', array_unique($drugIdsToFetch))
                 ->pluck('strength', 'id')
                 ->toArray();
        }

        $expiredDrugs = collect();
        
        foreach ($expiredDrugsLogs as $log) {
            $newValues = json_decode($log->new_values, true);
            
            if ($newValues && isset($newValues['drugName'])) {
                // التحقق من أن الدواء لا يحتوي على pharmacyId (لأنها للصيدلية)
                // إذا كان record_id ينتمي لنفس المستودع (تم التحقق منه مسبقاً في whereIn)،
                // فإن الدواء من نفس المستودع، بغض النظر عن وجود warehouseId في audit_log
                $hasPharmacyId = isset($newValues['pharmacyId']) && $newValues['pharmacyId'] !== null;
                
                // نعرض الأدوية التي:
                // 1. لا تحتوي على pharmacyId (لأنها للصيدلية)
                // 2. record_id ينتمي لنفس المستودع (تم التحقق منه مسبقاً في whereIn)
                if (!$hasPharmacyId) {
                    // تجنب التكرار (نفس الدواء وتاريخ الانتهاء)
                    $exists = $expiredDrugs->contains(function ($existing) use ($newValues) {
                        return $existing['drugName'] === $newValues['drugName'] && 
                               $existing['expiryDate'] === ($newValues['expiryDate'] ?? null);
                    });
                    
                    if (!$exists) {
                        $strength = $newValues['strength'] ?? null;
                        
                        // محاولة استكمال التركيز الناقص
                        if (empty($strength) && !empty($newValues['drugId']) && isset($drugStrengths[$newValues['drugId']])) {
                            $strength = $drugStrengths[$newValues['drugId']];
                        }

                        $expiredDrugs->push([
                            'drugName' => $newValues['drugName'] ?? null,
                            'strength' => $strength,
                            'quantity' => $newValues['quantity'] ?? 0,
                            'expiryDate' => $newValues['expiryDate'] ?? null,
                            'zeroedDate' => $log->created_at ? date('Y/m/d H:i', strtotime($log->created_at)) : null,
                        ]);
                    }
                }
            }
        }

        return $expiredDrugs;
    }

    /**
     * GET /api/storekeeper/drugs/expired
     * جلب قائمة الأدوية المُصفرة من audit_log (للصفحة المخصصة)
     */
    public function expired(Request $request)
    {
        $user = $request->user();
        $hospitalId = $user->hospital_id;
        $warehouseId = $user->warehouse_id;

        if (!$hospitalId) {
            return response()->json(['message' => 'المستخدم غير مرتبط بمستشفى.'], 400);
        }

        if (!$warehouseId) {
            return response()->json(['message' => 'المستخدم غير مرتبط بمخزن.'], 400);
        }

        // جمع معلومات الأدوية المنتهية قبل التصفير وحفظها في audit_log
        $today = Carbon::now()->format('Y-m-d');
        
        // جلب جميع الأدوية المنتهية الصلاحية من المخزون (سواء كانت كميتها > 0 أو = 0)
        // هذا يضمن أننا نلتقط الأدوية التي تم تصفيرها سابقاً
        $expiredInventories = DB::table('inventories')
            ->join('drugs', 'inventories.drug_id', '=', 'drugs.id')
            ->where('inventories.warehouse_id', $warehouseId)
            ->whereNotNull('inventories.expiry_date')
            ->whereRaw("DATE(inventories.expiry_date) <= ?", [$today])
            ->select(
                'inventories.id as inventory_id',
                'drugs.id as drug_id',
                'drugs.name as drug_name',
                'drugs.strength',
                'inventories.current_quantity',
                'drugs.expiry_date'
            )
            ->get();
        
        // جلب جميع الأدوية المُصفرة من audit_log أولاً (للتأكد من عدم التكرار)
        $expiredDrugsFromLog = $this->getExpiredDrugsFromAuditLog($hospitalId, $warehouseId);
        
        // حفظ الأدوية المُصفرة الجديدة في audit_log
        foreach ($expiredInventories as $expired) {
            $drugExpiryDate = $expired->expiry_date ? date('Y/m/d', strtotime($expired->expiry_date)) : null;
            
            // التحقق من عدم وجود هذا الدواء في audit_log
            $exists = $expiredDrugsFromLog->contains(function ($existing) use ($expired, $drugExpiryDate) {
                return $existing['drugName'] === $expired->drug_name && 
                       $existing['expiryDate'] === $drugExpiryDate;
            });
            
            // إذا كان موجوداً في audit_log، نتخطاه
            if ($exists) {
                continue;
            }
            
            // تحديد الكمية الأصلية
            $originalQuantity = $expired->current_quantity;
            
            // إذا كانت الكمية = 0 (تم تصفيرها سابقاً)، نحاول الحصول على الكمية الأصلية من audit_log
            if ($originalQuantity == 0) {
                // البحث في audit_log عن آخر تحديث لهذا المخزون
                $lastUpdate = AuditLog::where('table_name', 'inventories')
                    ->where('record_id', $expired->inventory_id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if ($lastUpdate) {
                    // محاولة الحصول على الكمية من old_values
                    if ($lastUpdate->old_values) {
                        $oldValues = json_decode($lastUpdate->old_values, true);
                        if (isset($oldValues['current_quantity']) && $oldValues['current_quantity'] > 0) {
                            $originalQuantity = $oldValues['current_quantity'];
                        } elseif (isset($oldValues['quantity']) && $oldValues['quantity'] > 0) {
                            $originalQuantity = $oldValues['quantity'];
                        }
                    }
                    
                    // إذا لم نجد الكمية من old_values، نحاول الحصول عليها من new_values
                    if ($originalQuantity == 0 && $lastUpdate->new_values) {
                        $newValues = json_decode($lastUpdate->new_values, true);
                        if (isset($newValues['current_quantity']) && $newValues['current_quantity'] > 0) {
                            $originalQuantity = $newValues['current_quantity'];
                        } elseif (isset($newValues['quantity']) && $newValues['quantity'] > 0) {
                            $originalQuantity = $newValues['quantity'];
                        }
                    }
                }
                
                // إذا لم نتمكن من الحصول على الكمية الأصلية، نتخطى هذا الدواء
                // لأننا لا نعرف الكمية الأصلية التي تم تصفيرها
                if ($originalQuantity == 0) {
                    continue;
                }
            }
            
            // حفظ في audit_log
            AuditLog::create([
                'user_id' => $user->id,
                'hospital_id' => $hospitalId,
                'action' => 'drug_expired_zeroed',
                'table_name' => 'inventories',
                'record_id' => $expired->inventory_id,
                'old_values' => json_encode([
                    'quantity' => $originalQuantity,
                ]),
                    'new_values' => json_encode([
                        'drugName' => $expired->drug_name,
                        'drugId' => $expired->drug_id,
                        'strength' => $expired->strength ?? null,
                        'quantity' => $originalQuantity,
                        'expiryDate' => $drugExpiryDate,
                        'warehouseId' => $warehouseId,
                    ]),
                'ip_address' => $request->ip() ?? request()->ip(),
            ]);
        }

        // جلب جميع الأدوية المُصفرة من audit_log (بعد الحفظ)
        $allExpiredDrugs = $this->getExpiredDrugsFromAuditLog($hospitalId, $warehouseId);

        return response()->json($allExpiredDrugs->values());
    }
}
