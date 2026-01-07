<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\BaseApiController;
use App\Models\Drug;
use App\Models\Inventory;
use App\Models\ExternalSupplyRequest;
use App\Models\ExternalSupplyRequestItem;
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

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            // التأكد من وجود supplier_id و hospital_id
            if (!$user->supplier_id) {
                return $this->sendError('المستخدم غير مرتبط بمورد', null, 403);
            }

            if (!$user->hospital_id) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى', null, 403);
            }

            // جلب طلبات التوريد الخارجية المعتمدة والمُرسلة للمورد من نفس المستشفى
            // الطلبات المعتمدة (approved) هي التي تمت الموافقة عليها من مدير المستشفى وتم إرسالها للمورد
            $externalRequests = ExternalSupplyRequest::where('hospital_id', $user->hospital_id)
                ->where('status', 'approved') // فقط الطلبات المعتمدة والمُرسلة للمورد
                ->where('supplier_id', $user->supplier_id) // التأكد من أن الطلب مُرسل لهذا المورد
                ->with(['items.drug'])
                ->get();

            // جمع جميع الأدوية من الطلبات مع الكميات المطلوبة
            $requestItems = collect();
            foreach ($externalRequests as $request) {
                foreach ($request->items as $item) {
                    if ($item->drug) {
                        // استخدام approved_qty إذا كان موجوداً، وإلا requested_qty
                        $qty = (int)($item->approved_qty ?? $item->requested_qty ?? 0);
                        if ($qty > 0) {
                            $requestItems->push([
                                'drug_id' => $item->drug_id,
                                'requested_qty' => $qty,
                            ]);
                        }
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

            // جلب الأدوية من مخزون المورد
            $items = Inventory::with(['drug'])
                ->where('supplier_id', $user->supplier_id)
                ->whereHas('drug')
                ->get();

            // جلب معرفات الأدوية المسجلة في مخزون المورد
            $registeredDrugIds = $items->pluck('drug_id')->toArray();

            // تحويل النتيجة للفورمات الذي يحتاجه الـ frontend للأدوية المسجلة
            // مع حساب الكمية المحتاجة ديناميكياً من الطلبات
            $registeredDrugs = $items->map(function ($inventory) use ($drugsRequestedQuantities) {
                $drugId = $inventory->drug_id;
                $availableQuantity = $inventory->current_quantity;
                
                // حساب الكمية المطلوبة من الطلبات لهذا الدواء
                $totalRequestedQty = 0;
                if ($drugsRequestedQuantities->has($drugId)) {
                    $totalRequestedQty = $drugsRequestedQuantities->get($drugId)['total_requested_qty'];
                }
                
                // الكمية المحتاجة = مجموع الكميات المطلوبة من الطلبات - الكمية المتوفرة
                // إذا كانت النتيجة <= 0، تصبح 0
                $neededQuantity = max(0, $totalRequestedQty - $availableQuantity);
                
                return [
                    'id' => $inventory->drug->id,
                    'drugCode' => $inventory->drug->id,
                    'drugName' => $inventory->drug->name,
                    'name' => $inventory->drug->name,
                    'genericName' => $inventory->drug->generic_name ?? null,
                    'strength' => $inventory->drug->strength ?? 'غير محدد',
                    'quantity' => $availableQuantity,
                    'neededQuantity' => $neededQuantity, // الكمية المحتاجة المحسوبة ديناميكياً
                    'expiryDate' => $inventory->drug->expiry_date ? date('Y/m/d', strtotime($inventory->drug->expiry_date)) : 'غير محدد',
                    'category' => $inventory->drug
                        ? (is_object($inventory->drug->category)
                            ? ($inventory->drug->category->name ?? $inventory->drug->category)
                            : ($inventory->drug->category ?? 'غير مصنف'))
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
                        'neededQuantity' => $item['total_requested_qty'], // الكمية المطلوبة من جميع الطلبات
                        'expiryDate' => $drug->expiry_date ? date('Y/m/d', strtotime($drug->expiry_date)) : 'غير محدد',
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

            // التأكد من وجود supplier_id و hospital_id
            if (!$user->supplier_id) {
                return $this->sendError('المستخدم غير مرتبط بمورد', null, 403);
            }

            if (!$user->hospital_id) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى', null, 403);
            }

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
                $externalRequests = ExternalSupplyRequest::where('hospital_id', $user->hospital_id)
                    ->where('status', 'approved')
                    ->where('supplier_id', $user->supplier_id)
                    ->with(['items' => function($query) use ($drugId) {
                        $query->where('drug_id', $drugId);
                    }])
                    ->get();

                $totalRequestedQty = 0;
                foreach ($externalRequests as $request) {
                    foreach ($request->items as $item) {
                        $qty = (int)($item->approved_qty ?? $item->requested_qty ?? 0);
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
                    'expiryDate' => $drug->expiry_date ? date('Y/m/d', strtotime($drug->expiry_date)) : 'غير محدد',
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
                $externalRequests = ExternalSupplyRequest::where('hospital_id', $user->hospital_id)
                    ->where('status', 'approved')
                    ->where('supplier_id', $user->supplier_id)
                    ->with(['items' => function($query) use ($drug) {
                        $query->where('drug_id', $drug->id);
                    }])
                    ->get();

                $totalRequestedQty = 0;
                foreach ($externalRequests as $request) {
                    foreach ($request->items as $item) {
                        $qty = (int)($item->approved_qty ?? $item->requested_qty ?? 0);
                        $totalRequestedQty += $qty;
                    }
                }

                $neededQuantity = max(0, $totalRequestedQty - $inventory->current_quantity);

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
                    'expiryDate' => $drug->expiry_date ? date('Y/m/d', strtotime($drug->expiry_date)) : 'غير محدد',
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

            $drugs = Drug::select('id', 'name', 'generic_name', 'strength', 'form', 'category', 'unit', 'status')
                ->where('status', 'متوفر')
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
                ->where('status', 'متوفر')
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
            ]);

            $supplierId = $user->supplier_id;
            $registeredItems = [];

            foreach ($request->items as $item) {
                // البحث عن سجل المخزون الحالي
                $inventory = Inventory::where('drug_id', $item['drugId'])
                    ->where('supplier_id', $supplierId)
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
}
