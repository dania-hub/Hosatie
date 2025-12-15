<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\BaseApiController;
use App\Models\Drug;
use App\Models\Category;
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

            if ($user->type !== 'supplier') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            // جلب الأدوية من مخزون المورد
            $drugs = Inventory::with(['drug.category'])
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
                        'category' => $inventory->drug->category->name ?? 'غير مصنف',
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

            if ($user->type !== 'supplier') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $drugs = Drug::with('category')
                ->where('status', 'active')
                ->get()
                ->map(function ($drug) {
                    return [
                        'id' => $drug->id,
                        'code' => $drug->code,
                        'name' => $drug->name,
                        'category' => $drug->category->name ?? 'غير مصنف',
                        'categoryId' => $drug->category_id,
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

            if ($user->type !== 'supplier') {
                return $this->sendError('غير مصرح لك بالوصول', null, 403);
            }

            $searchTerm = $request->input('query', '');

            $drugs = Drug::with('category')
                ->where('status', 'active')
                ->where(function ($query) use ($searchTerm) {
                    $query->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('code', 'like', "%{$searchTerm}%");
                })
                ->limit(20)
                ->get()
                ->map(function ($drug) {
                    return [
                        'id' => $drug->id,
                        'code' => $drug->code,
                        'name' => $drug->name,
                        'category' => $drug->drug->category->name ?? 'غير مصنف',
                    ];
                });

            return $this->sendSuccess($drugs, 'تم البحث بنجاح');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Supplier Drugs Search Error');
        }
    }

    /**
     * جلب الفئات
     * GET /api/supplier/categories
     */
//     public function categories(Request $request)
//     {
//         try {
//             $user = $request->user();

//             if ($user->type !== 'supplier') {
//                 return $this->sendError('غير مصرح لك بالوصول', null, 403);
//             }

//             $categories = Category::where('status', 'active')
//                 ->orderBy('name')
//                 ->get()
//                 ->map(function ($category) {
//                     return [
//                         'id' => $category->id,
//                         'name' => $category->name,
//                     ];
//                 });

//             return $this->sendSuccess($categories, 'تم جلب الفئات بنجاح');
//         } catch (\Exception $e) {
//             return $this->handleException($e, 'Supplier Categories Error');
//         }
//     }
 }
