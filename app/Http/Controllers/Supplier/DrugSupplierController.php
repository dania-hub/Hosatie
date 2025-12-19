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
                        'drugCode' => $inventory->drug->code,
                        'drugName' => $inventory->drug->name,
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

            $drugs = Drug::select('id', 'name', 'category')
                ->where('status', 'متوفر')
                ->get()
                ->map(function ($drug) {
                    return [
                        'id' => $drug->id,
                        'name' => $drug->name,
                        'category' => is_object($drug->category)
                            ? ($drug->category->name ?? 'غير مصنف')
                            : ($drug->category ?? 'غير مصنف'),
                        'categoryId' => null,
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
            $categories = \DB::table('drug')
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
}
