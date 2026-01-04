<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\BaseApiController;
use App\Models\Drug;
use App\Models\Inventory;
use Illuminate\Http\Request;

class DrugSupplierController extends BaseApiController
{
    /**
     * عرض قائمة الأدوية في مخزون المورد
     * GET /api/supplier/drugs
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== 'supplier_admin') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            // جلب الأدوية من مخزون المورد
            $drugs = Inventory::with(['drug'])
                ->where('supplier_id', $user->supplier_id)
                ->whereHas('drug')
                ->get()
                ->map(function ($inventory) {
                    return [
                        'id' => $inventory->drug->id,
                        'drugName' => $inventory->drug->name,
                        'strength' => $inventory->drug->strength ?? 'غير محدد',
                        'quantity' => $inventory->current_quantity,
                        'neededQuantity' => $inventory->minimum_level,
                        'expiryDate' => $inventory->drug->expiry_date ?? 'غير محدد',
                        'category' => $inventory->drug
                            ? (is_object($inventory->drug->category)
                                ? ($inventory->drug->category->name ?? $inventory->drug->category)
                                : ($inventory->drug->category ?? 'غير مصنف'))
                            : 'غير مصنف',
                    ];
                });

            return $this->sendSuccess($drugs, 'تم جلب قائمة الأدوية بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Drugs Index Error');
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
