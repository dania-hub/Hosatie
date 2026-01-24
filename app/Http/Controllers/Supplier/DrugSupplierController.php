<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\BaseApiController;
use App\Models\Drug;
use App\Models\Inventory;
use App\Models\ExternalSupplyRequest;
use App\Models\ExternalSupplyRequestItem;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class DrugSupplierController extends BaseApiController
{
    /**
     * عرض قائمة الأدوية في مخزون المورد + الأدوية غير المسجلة ولكن المطلوبة في الطلبات المستقبلة
     * GET /api/supplier/drugs
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin' && $user->type !== 'super_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            // التأكد من وجود supplier_id و hospital_id
            $supplier_id = $user->supplier_id;
            $hospital_id = $user->hospital_id ?: $request->input('hospital_id');

            if ($user->type === 'super_admin') {
                $supplier_id = $supplier_id ?: $request->input('supplier_id') ?: (\App\Models\Supplier::first()?->id);
                $hospital_id = $hospital_id ?: $request->input('hospital_id') ?: (\App\Models\Hospital::first()?->id);
            }

            if (!$supplier_id) {
                return $this->sendError('المستخدم غير مرتبط بمورد', null, 403);
            }

            // جمع معلومات الأدوية المنتهية قبل التصفير وحفظها في audit_log
            $today = Carbon::now()->format('Y-m-d');
            
            // جلب الأدوية المنتهية الصلاحية من المخزون قبل التصفير
            $expiredInventories = DB::table('inventories')
                ->join('drugs', 'inventories.drug_id', '=', 'drugs.id')
                ->where('inventories.supplier_id', $supplier_id)
                ->whereNull('inventories.warehouse_id')
                ->whereNull('inventories.pharmacy_id')
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
                
                // جلب جميع السجلات المتعلقة بهذا الدواء والمورد
                $logQuery = AuditLog::where('action', 'drug_expired_zeroed')
                    ->where('table_name', 'inventories');
                
                if ($hospital_id) {
                    $logQuery->where('hospital_id', $hospital_id);
                }
                
                $existingLogs = $logQuery->get();
                
                $exists = false;
                foreach ($existingLogs as $log) {
                    $newValues = json_decode($log->new_values, true);
                    if ($newValues && 
                        isset($newValues['drugId']) && $newValues['drugId'] == $expired->drug_id &&
                        isset($newValues['supplierId']) && $newValues['supplierId'] == $supplier_id &&
                        isset($newValues['expiryDate']) && $newValues['expiryDate'] == $drugExpiryDate) {
                        $exists = true;
                        break;
                    }
                }
                
                if (!$exists) {
                    // حفظ في audit_log
                    AuditLog::create([
                        'user_id' => $user->id,
                        'hospital_id' => $hospital_id ?: 0, // 0 if not linked to a specific hospital context
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
                            'supplierId' => $supplier_id,
                        ]),
                        'ip_address' => $request->ip() ?? request()->ip(),
                    ]);

                    // تصفير الكمية فعلياً في قاعدة البيانات
                    DB::table('inventories')
                        ->where('id', $expired->inventory_id)
                        ->update(['current_quantity' => 0]);
                }
            }

            // جلب طلبات التوريد الخارجية المعتمدة والمُرسلة للمورد
            // الطلبات المعتمدة (approved) هي التي تمت الموافقة عليها من مدير المستشفى وتم إرسالها للمورد
            // هذه الطلبات تظهر بحالة "جديد" في واجهة المورد
            $reqQuery = ExternalSupplyRequest::where('status', 'approved') // فقط الطلبات المعتمدة والمُرسلة للمورد
                ->where('supplier_id', $supplier_id); // التأكد من أن الطلب مُرسل لهذا المورد
            
            if ($hospital_id) {
                $reqQuery->where('hospital_id', $hospital_id);
            }

            // جلب معرفات الطلبات أولاً
            $requestIds = $reqQuery->pluck('id')->toArray();

            // جلب العناصر مباشرة من جدول external_supply_request_item
            // للطلبات بحالة "جديد" (approved)، نستخدم requested_qty مباشرة
            // لأن approved_qty قد يكون null أو 0 في هذه المرحلة (قبل أن يحدد المورد الكمية المعتمدة)
            $requestItems = collect();
            if (!empty($requestIds)) {
                $requestItems = ExternalSupplyRequestItem::whereIn('request_id', $requestIds)
                    ->whereNotNull('drug_id')
                    ->select('drug_id', 'requested_qty')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'drug_id' => $item->drug_id,
                            'requested_qty' => (int)($item->requested_qty ?? 0),
                        ];
                    })
                    ->filter(function ($item) {
                        return $item['requested_qty'] > 0;
                    });
            }

            // تجميع الأدوية حسب drug_id وحساب المجموع المطلوب من جميع الطلبات
            $drugsRequestedQuantities = collect($requestItems)
                ->groupBy('drug_id')
                ->map(function ($items, $drugId) {
                    return [
                        'drug_id' => $drugId,
                        'total_requested_qty' => $items->sum('requested_qty')
                    ];
                });

            // جلب الأدوية من مخزون المورد
            $items = Inventory::with(['drug'])
                ->where('supplier_id', $supplier_id)
                ->whereHas('drug', function($q) {
                    $q->where('status', '!=', Drug::STATUS_ARCHIVED);
                })
                ->get();

            // جلب معرفات الأدوية المسجلة في مخزون المورد
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
                $neededQuantity = max(0, $totalRequestedQty - $totalQuantity);
                
                // تحديث minimum_level في قاعدة البيانات لتسجيل النقص
                foreach ($group as $inventory) {
                    if ($inventory->minimum_level != $neededQuantity) {
                        $inventory->minimum_level = $neededQuantity;
                        $inventory->save();
                    }
                }
                
                return [
                    'id' => $drug->id,
                    'drugCode' => $drug->id,
                    'drugName' => $drug->name,
                    'name' => $drug->name,
                    'genericName' => $drug->generic_name ?? null,
                    'strength' => $drug->strength ?? 'غير محدد',
                    'quantity' => $totalQuantity,
                    'quantity_boxes' => floor($totalQuantity / ($drug->units_per_box ?: 1)),
                    'quantity_remainder' => $totalQuantity % ($drug->units_per_box ?: 1),
                    'neededQuantity' => $neededQuantity, // الكمية المحتاجة المحسوبة ديناميكياً
                    'needed_quantity_boxes' => floor($neededQuantity / ($drug->units_per_box ?: 1)),
                    'units_per_box' => $drug->units_per_box,
                    'unit' => $drug->unit,
                    'batches' => $batches, // قائمة الدفعات وتواريخ الصلاحية
                    'expiryDate' => $group->min('expiry_date') ? date('Y/m/d', strtotime($group->min('expiry_date'))) : null, // للأغراض الفرز (أقرب تاريخ)
                    'category' => $drug
                        ? (is_object($drug->category)
                            ? ($drug->category->name ?? $drug->category)
                            : ($drug->category ?? 'غير مصنف'))
                        : 'غير مصنف',
                    'isUnregistered' => false, // دواء مسجل في مخزون المورد
                ];
            });

            // جلب الأدوية غير المسجلة ولكن المطلوبة في طلبات التوريد الخارجية
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
                        'genericName' => $drug->generic_name ?? null,
                        'strength' => $drug->strength ?? 'غير محدد',
                        'quantity' => 0, // الكمية في المخزون = 0 لأنها غير مسجلة
                        'quantity_boxes' => 0,
                        'neededQuantity' => $item['total_requested_qty'], // الكمية المطلوبة من جميع الطلبات
                        'needed_quantity_boxes' => floor($item['total_requested_qty'] / ($drug->units_per_box ?: 1)),
                        'units_per_box' => $drug->units_per_box,
                        'unit' => $drug->unit,
                        'category' => is_object($drug->category)
                            ? ($drug->category->name ?? 'غير مصنف')
                            : ($drug->category ?? 'غير مصنف'),
                        'isUnregistered' => true, // دواء غير مسجل في مخزون المورد
                    ];
                })->filter(); // إزالة القيم null
            }

            // دمج الأدوية المسجلة وغير المسجلة
            $data = $registeredDrugs->merge($unregisteredDrugs);

            return $this->sendSuccess($data->values(), 'تم جلب قائمة الأدوية بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Drugs Index Error');
        }
    }

    /**
     * جلب تفاصيل دواء معين من قاعدة البيانات
     * GET /api/supplier/drugs/{id}
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            // التأكد من وجود supplier_id
            if (!$user->supplier_id) {
                return $this->sendError('المستخدم غير مرتبط بمورد', null, 403);
            }

            $hospital_id = $user->hospital_id;

            // التحقق من أن المعرف هو معرف Inventory أو معرف Drug
            // إذا كان يبدأ بـ "unregistered_"، فهو دواء غير مسجل
            if (strpos($id, 'unregistered_') === 0) {
                // دواء غير مسجل - جلب من جدول drugs مباشرة
                $drugId = str_replace('unregistered_', '', $id);
                $drug = Drug::find($drugId);
                
                if (!$drug) {
                    return $this->sendError('الدواء غير موجود', null, 404);
                }

                // حساب الكمية المحتاجة من الطلبات
                $reqQuery = ExternalSupplyRequest::where('status', 'approved')
                    ->where('supplier_id', $user->supplier_id);
                
                if ($hospital_id) {
                    $reqQuery->where('hospital_id', $hospital_id);
                }

                $externalRequests = $reqQuery->with(['items' => function($query) use ($drugId) {
                        $query->where('drug_id', $drugId);
                    }])
                    ->get();

                $totalRequestedQty = 0;
                foreach ($externalRequests as $request) {
                    foreach ($request->items as $item) {
                        // للطلبات بحالة "جديد" (approved)، نستخدم requested_qty مباشرة
                        $qty = (int)($item->requested_qty ?? 0);
                        $totalRequestedQty += $qty;
                    }
                }

                $data = [
                    'id' => 'unregistered_' . $drug->id,
                    'drugCode' => $drug->id,
                    'drugName' => $drug->name,
                    'name' => $drug->name,
                    'genericName' => $drug->generic_name,
                    'strength' => $drug->strength,
                    'form' => $drug->form,
                    'category' => is_object($drug->category)
                        ? ($drug->category->name ?? 'غير مصنف')
                        : ($drug->category ?? 'غير مصنف'),
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
                    'units_per_box' => $drug->units_per_box,
                    'isUnregistered' => true,
                ];

                return $this->sendSuccess($data, 'تم جلب تفاصيل الدواء بنجاح');
            } else {
                // دواء مسجل - جلب من Inventory مع معلومات Drug
                $inventory = Inventory::with('drug')
                    ->where('supplier_id', $user->supplier_id)
                    ->where('drug_id', $id)
                    ->whereNull('warehouse_id')
                    ->whereNull('pharmacy_id')
                    ->first();

                if (!$inventory || !$inventory->drug) {
                    return $this->sendError('الدواء غير موجود في المخزون', null, 404);
                }

                $drug = $inventory->drug;

                // حساب الكمية المحتاجة من الطلبات
                $reqQuery = ExternalSupplyRequest::where('status', 'approved')
                    ->where('supplier_id', $user->supplier_id);
                
                if ($hospital_id) {
                    $reqQuery->where('hospital_id', $hospital_id);
                }

                $externalRequests = $reqQuery->with(['items' => function($query) use ($drug) {
                        $query->where('drug_id', $drug->id);
                    }])
                    ->get();

                $totalRequestedQty = 0;
                foreach ($externalRequests as $request) {
                    foreach ($request->items as $item) {
                        // للطلبات بحالة "جديد" (approved)، نستخدم requested_qty مباشرة
                        $qty = (int)($item->requested_qty ?? 0);
                        $totalRequestedQty += $qty;
                    }
                }

                $neededQuantity = max(0, $totalRequestedQty - $inventory->current_quantity);
                
                // تحديث minimum_level في قاعدة البيانات تلقائياً بالقيمة المحسوبة
                if ($inventory->minimum_level != $neededQuantity) {
                    $inventory->minimum_level = $neededQuantity;
                    $inventory->save();
                }

                $data = [
                    'id' => $drug->id,
                    'drugCode' => $drug->id,
                    'drugName' => $drug->name,
                    'name' => $drug->name,
                    'genericName' => $drug->generic_name,
                    'strength' => $drug->strength,
                    'form' => $drug->form,
                    'category' => is_object($drug->category)
                        ? ($drug->category->name ?? 'غير مصنف')
                        : ($drug->category ?? 'غير مصنف'),
                    'unit' => $drug->unit,
                    'maxMonthlyDose' => $drug->max_monthly_dose,
                    'status' => $drug->status,
                    'manufacturer' => $drug->manufacturer,
                    'country' => $drug->country,
                    'utilizationType' => $drug->utilization_type,
                    'indications' => $drug->indications,
                    'warnings' => $drug->warnings,
                    'contraindications' => $drug->contraindications,
                    'quantity' => $inventory->current_quantity,
                    'neededQuantity' => $neededQuantity,
                    'units_per_box' => $drug->units_per_box,
                    'expiryDate' => $inventory->expiry_date ? date('Y/m/d', strtotime($inventory->expiry_date)) : 'غير محدد',
                    'isUnregistered' => false,
                ];

                return $this->sendSuccess($data, 'تم جلب تفاصيل الدواء بنجاح');
            }
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Drug Show Error');
        }
    }

    /**
     * جلب جميع الأدوية للبحث
     * GET /api/supplier/drugs/all
     */
    public function all(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $drugs = Drug::select('id', 'name', 'generic_name', 'strength', 'form', 'category', 'unit', 'status', 'units_per_box')
                ->where('status', Drug::STATUS_AVAILABLE)
                ->get()
                ->map(function ($drug) {
                    return [
                        'id' => $drug->id,
                        'name' => $drug->name,
                        'genericName' => $drug->generic_name,
                        'strength' => $drug->strength,
                        'form' => $drug->form,
                        'type' => $drug->form, // للتوافق مع الواجهة
                        'category' => is_object($drug->category)
                            ? ($drug->category->name ?? 'غير مصنف')
                            : ($drug->category ?? 'غير مصنف'),
                        'categoryId' => $drug->category, // استخدام category مباشرة
                        'unit' => $drug->unit ?? 'قرص',
                        'status' => $drug->status,
                        'units_per_box' => $drug->units_per_box ?? 1,
                    ];
                });

            return $this->sendSuccess($drugs, 'تم جلب جميع الأدوية بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Drugs All Error');
        }
    }

    /**
     * البحث عن أدوية
     * GET /api/supplier/drugs/search
     */
    public function search(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $searchTerm = $request->input('query', '');

            $drugs = Drug::select('id', 'name', 'category')
                ->where('status', Drug::STATUS_AVAILABLE)
                ->where(function ($query) use ($searchTerm) {
                    $query->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('category', 'like', "%{$searchTerm}%");
                })
                ->limit(20)
                ->get()
                ->map(function ($drug) {
                    return [
                        'id' => $drug->id,
                        'name' => $drug->name,
                        'category' => is_object($drug->category)
                            ? ($drug->category->name ?? 'غير مصنف')
                            : ($drug->category ?? 'غير مصنف'),
                    ];
                });

            return $this->sendSuccess($drugs, 'تم البحث بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Drugs Search Error');
        }
    }


    /**
     * جلب الفئات من الأدوية الموجودة
     * GET /api/supplier/categories
     */
    public function categories(Request $request)
    {
        try {
            $user = $request->user();

            // التحقق من نوع المستخدم
            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            // جلب الفئات المميزة من عمود category في جدول drug
            $categories = \DB::table('drugs')
                ->select('category')
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()
                ->orderBy('category')
                ->get()
                ->map(function ($item, $index) {
                    return [
                        'id' => $index + 1,
                        'name' => $item->category,
                    ];
                })
                ->values(); // إعادة ترتيب المفاتيح

            return $this->sendSuccess($categories, 'تم جلب الفئات بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Categories Error');
        }
    }

    /**
     * تسجيل استلام الأدوية وإضافتها للمخزون
     * POST /api/supplier/drugs/register
     */
    public function register(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $request->validate([
                'items' => 'required|array|min:1',
                'items.*.drugId' => 'required|exists:drugs,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.batch_number' => 'nullable|string',
                'items.*.expiry_date' => 'nullable|date',
            ]);

            $supplierId = $user->supplier_id;
            $registeredItems = [];

            foreach ($request->items as $item) {
                // التحقق من حالة الدواء قبل الإضافة للمخزون
                $drug = Drug::find($item['drugId']);
                if (!$drug) {
                    throw new \Exception("الدواء رقم #{$item['drugId']} غير موجود.");
                }

                if ($drug->status === Drug::STATUS_ARCHIVED) {
                    throw new \Exception("لا يمكن إضافة الدواء '{$drug->name}' للمخزون لأنه مؤرشف وغير مدعوم.");
                }

                if ($drug->status === Drug::STATUS_PHASING_OUT) {
                    throw new \Exception("لا يمكن إضافة كميات جديدة للدواء '{$drug->name}' لأنه في مرحلة الإيقاف التدريجي.");
                }

                // البحث عن سجل المخزون الحالي
                $inventory = Inventory::where('drug_id', $item['drugId'])
                    ->where('supplier_id', $supplierId)
                    ->where('batch_number', $item['batch_number'] ?? null)
                    ->where('expiry_date', $item['expiry_date'] ?? null)
                    ->whereNull('warehouse_id')
                    ->whereNull('pharmacy_id')
                    ->first();

                if ($inventory) {
                    // إذا كان موجود، نضيف الكمية للكمية الحالية
                    $inventory->current_quantity += $item['quantity'];
                    $inventory->save();
                } else {
                    // إذا لم يكن موجود، ننشئ سجل جديد
                    $inventory = Inventory::create([
                        'drug_id' => $item['drugId'],
                        'supplier_id' => $supplierId,
                        'warehouse_id' => null,
                        'pharmacy_id' => null,
                        'current_quantity' => $item['quantity'],
                        'batch_number' => $item['batch_number'] ?? null,
                        'expiry_date' => $item['expiry_date'] ?? null,
                        'minimum_level' => 50, // القيمة الافتراضية
                    ]);
                }

                $registeredItems[] = [
                    'drugId' => $item['drugId'],
                    'drugName' => $inventory->drug->name ?? 'غير معروف',
                    'quantity' => $item['quantity'],
                    'currentQuantity' => $inventory->current_quantity,
                ];
            }

            return $this->sendSuccess($registeredItems, 'تم تسجيل الاستلام وإضافة الأدوية للمخزون بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Register Error');
        }
    }

    /**
     * دالة مساعدة لجلب الأدوية المُصفرة من audit_log
     */
    private function getExpiredDrugsFromAuditLog($hospitalId, $supplierId)
    {
        // جلب جميع inventory_ids التي تنتمي لنفس المورد
        $supplierInventoryIds = Inventory::where('supplier_id', $supplierId)
            ->whereNull('warehouse_id')
            ->whereNull('pharmacy_id')
            ->pluck('id')
            ->toArray();
        
        if (empty($supplierInventoryIds)) {
            return collect();
        }
        
        $expiredDrugsLogs = AuditLog::where('action', 'drug_expired_zeroed')
            ->where('hospital_id', $hospitalId)
            ->whereIn('record_id', $supplierInventoryIds) // الأدوية من نفس المورد فقط
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
                // التحقق من أن الدواء لا يحتوي على pharmacyId أو warehouseId (لأنها للصيدلية والمستودع)
                // إذا كان record_id ينتمي لنفس المورد (تم التحقق منه مسبقاً في whereIn)،
                // فإن الدواء من نفس المورد، بغض النظر عن وجود supplierId في audit_log
                $hasPharmacyId = isset($newValues['pharmacyId']) && $newValues['pharmacyId'] !== null;
                $hasWarehouseId = isset($newValues['warehouseId']) && $newValues['warehouseId'] !== null;
                
                // نعرض الأدوية التي:
                // 1. لا تحتوي على pharmacyId (لأنها للصيدلية)
                // 2. لا تحتوي على warehouseId (لأنها للمستودع)
                // 3. record_id ينتمي لنفس المورد (تم التحقق منه مسبقاً في whereIn)
                if (!$hasPharmacyId && !$hasWarehouseId) {
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
     * GET /api/supplier/drugs/expired
     * جلب قائمة الأدوية المُصفرة من audit_log (للصفحة المخصصة)
     */
    public function expired(Request $request)
    {
        try {
            $user = $request->user();
            $hospitalId = $user->hospital_id;
            $supplierId = $user->supplier_id;

            if (!$supplierId) {
                return $this->sendError('المستخدم غير مرتبط بمورد.', null, 400);
            }

            // جمع معلومات الأدوية المنتهية قبل التصفير وحفظها في audit_log
            $today = Carbon::now()->format('Y-m-d');
            
            // جلب جميع الأدوية المنتهية الصلاحية من المخزون (سواء كانت كميتها > 0 أو = 0)
            // هذا يضمن أننا نلتقط الأدوية التي تم تصفيرها سابقاً
            $expiredInventories = DB::table('inventories')
                ->join('drugs', 'inventories.drug_id', '=', 'drugs.id')
                ->where('inventories.supplier_id', $supplierId)
                ->whereNull('inventories.warehouse_id')
                ->whereNull('inventories.pharmacy_id')
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
            
            // جلب جميع الأدوية المُصفرة من audit_log أولاً (للتأكد من عدم التكرار)
            $expiredDrugsFromLog = $this->getExpiredDrugsFromAuditLog($hospitalId, $supplierId);
            
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
                        'supplierId' => $supplierId,
                    ]),
                    'ip_address' => $request->ip() ?? request()->ip(),
                ]);
            }

            // جلب جميع الأدوية المُصفرة من audit_log (بعد الحفظ)
            $allExpiredDrugs = $this->getExpiredDrugsFromAuditLog($hospitalId, $supplierId);

            return $this->sendSuccess($allExpiredDrugs->values(), 'تم جلب قائمة الأدوية المُصفرة بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Expired Drugs Error');
        }
    }
}
