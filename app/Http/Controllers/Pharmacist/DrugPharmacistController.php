<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Drug;
use App\Models\Inventory;
use App\Models\Pharmacy;
use Illuminate\Support\Facades\DB;

class DrugPharmacistController extends BaseApiController
{
    /**
     * دالة مساعدة لجلب معرف صيدلية المستخدم الحالي
     */
    private function getPharmacistPharmacyId($user)
    {
        if ($user->pharmacy_id) {
            return $user->pharmacy_id;
        }
        if ($user->hospital_id) {
            $pharmacy = Pharmacy::where('hospital_id', $user->hospital_id)->first();
            return $pharmacy ? $pharmacy->id : null;
        }
        return null;
    }

    /**
     * GET /api/pharmacist/drugs
     * عرض الأدوية الموجودة في مخزون الصيدلية فقط.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $pharmacyId = $this->getPharmacistPharmacyId($user);

        // التأكد من أن المستخدم لديه pharmacy_id
        if (!$pharmacyId) {
            return $this->sendError('المستخدم غير مرتبط بصيدلية.', [], 400);
        }

        // جلب الأدوية الموجودة في مخزون هذه الصيدلية فقط
        $inventories = Inventory::with('drug')
            ->where('pharmacy_id', $pharmacyId)
            ->whereHas('drug') // التأكد من وجود الدواء
            ->orderBy('created_at', 'desc')
            ->get();

        // تحويل البيانات إلى الشكل المطلوب
        $result = $inventories->map(function ($inventory) {
            $drug = $inventory->drug;
            
            if (!$drug) {
                return null;
            }
            
            return [
                'id' => $inventory->id, // ID المخزون
                'drugCode' => $drug->id,
                'drugName' => $drug->name,
                'genericName' => $drug->generic_name,
                'strength' => $drug->strength,
                'form' => $drug->form,
                'category' => $drug->category,
                'categoryId' => $drug->category,
                'unit' => $drug->unit,
                'maxMonthlyDose' => $drug->max_monthly_dose,
                'status' => $drug->status,
                'manufacturer' => $drug->manufacturer,
                'country' => $drug->country,
                'utilizationType' => $drug->utilization_type,
                'quantity' => $inventory->current_quantity, // الكمية في المخزون
                'neededQuantity' => $inventory->minimum_level, // الحد الأدنى المطلوب
                'expiryDate' => $drug->expiry_date ? date('Y/m/d', strtotime($drug->expiry_date)) : null,
                'description' => $drug->description ?? '',
                'type' => $drug->form ?? 'Tablet'
            ];
        })->filter(); // إزالة القيم null

        return $this->sendSuccess($result->values(), 'تم تحميل قائمة الأدوية بنجاح.');
    }

    /**
     * GET /api/pharmacist/drugs/all
     * جلب جميع الأدوية من قاعدة البيانات (لإضافتها لمخزون الصيدلية).
     */
    public function searchAll(Request $request)
    {
        $drugs = Drug::select(
            'id',
            'name',
            'generic_name',
            'strength',
            'form',
            'category',
            'unit',
            'max_monthly_dose',
            'status',
            'manufacturer',
            'country',
            'utilization_type'
        )
            ->orderBy('name')
            ->get()
            ->map(function ($drug) {
                return [
                    'id' => $drug->id,
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
                ];
            });
            
        return $this->sendSuccess($drugs, 'تم جلب قائمة الأدوية العامة.');
    }

    /**
     * POST /api/pharmacist/drugs
     * إضافة دواء جديد إلى "مخزون الصيدلية".
     */
    public function store(Request $request)
    {
        $request->validate([
            'drugId' => 'required|exists:drugs,id',
            'quantity' => 'required|integer|min:0',
            'minimumLevel' => 'nullable|integer|min:0'
        ]);

        $user = $request->user();
        $pharmacyId = $this->getPharmacistPharmacyId($user);

        if (!$pharmacyId) {
            return $this->sendError('لا يوجد صيدلية مرتبطة لإضافة الدواء إليها.');
        }

        // إنشاء أو تحديث سجل المخزون الخاص بهذه الصيدلية
        $inventory = Inventory::updateOrCreate(
            [
                'drug_id' => $request->drugId,
                'pharmacy_id' => $pharmacyId // <--- الربط بالصيدلية
            ],
            [
                'warehouse_id' => null, // نؤكد أنه ليس مخزون مستودع
                'current_quantity' => $request->quantity,
                'minimum_level' => $request->minimumLevel ?? 50
            ]
        );
        
        return $this->sendSuccess($inventory, 'تم إضافة الدواء لمخزون الصيدلية بنجاح.');
    }

    /**
     * PUT /api/pharmacist/drugs/{id}
     * تحديث بيانات المخزون.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'integer|min:0',
            'neededQuantity' => 'integer|min:0',
        ]);

        $user = $request->user();
        $pharmacyId = $this->getPharmacistPharmacyId($user);

        // التأكد من أن سجل المخزون يتبع صيدلية هذا المستخدم
        $inventory = Inventory::where('id', $id)
            ->where('pharmacy_id', $pharmacyId)
            ->first();

        if (!$inventory) {
            return $this->sendError('السجل غير موجود أو غير تابع لصيدليتك.', [], 404);
        }
        
        if ($request->has('quantity')) {
            $inventory->current_quantity = $request->quantity;
        }
        if ($request->has('neededQuantity')) {
            $inventory->minimum_level = $request->neededQuantity;
        }
        
        $inventory->save();

        return $this->sendSuccess($inventory, 'تم تحديث بيانات الدواء بنجاح.');
    }

    /**
     * DELETE /api/pharmacist/drugs/{id}
     * حذف من مخزون الصيدلية.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $pharmacyId = $this->getPharmacistPharmacyId($user);

        $inventory = Inventory::where('id', $id)
            ->where('pharmacy_id', $pharmacyId)
            ->first();

        if (!$inventory) {
            return $this->sendError('السجل غير موجود أو غير تابع لصيدليتك.', [], 404);
        }

        $inventory->delete();
        
        return $this->sendSuccess([], 'تم حذف الدواء من مخزون الصيدلية بنجاح.');
    }

    /**
     * GET /api/pharmacist/drugs/low-stock
     * الأدوية التي قاربت على النفاد في الصيدلية.
     */
    public function lowStock(Request $request)
    {
        $user = $request->user();
        $pharmacyId = $this->getPharmacistPharmacyId($user);

        $lowStock = Inventory::with('drug')
            ->where('pharmacy_id', $pharmacyId) // <--- تصفية حسب الصيدلية
            ->whereColumn('current_quantity', '<', 'minimum_level')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'drugCode' => $item->drug->id,
                    'drugName' => $item->drug->name,
                    'quantity' => $item->current_quantity,
                    'neededQuantity' => $item->minimum_level,
                    'expiryDate' => $item->drug->expiry_date,
                    'categoryId' => $item->drug->category,
                    'description' => $item->drug->description ?? '',
                    'type' => $item->drug->form ?? 'Tablet',
                ];
            });

        return $this->sendSuccess($lowStock, 'تم جلب الأدوية منخفضة المخزون.');
    }

    /**
     * GET /api/pharmacist/drugs/search
     * البحث في الأدوية الموجودة في مخزون الصيدلية فقط.
     */
    public function search(Request $request)
    {
        $user = $request->user();
        $pharmacyId = $this->getPharmacistPharmacyId($user);
        
        // التأكد من أن المستخدم لديه pharmacy_id
        if (!$pharmacyId) {
            return $this->sendError('المستخدم غير مرتبط بصيدلية.', [], 400);
        }
        
        $query = $request->query('search');
        $catName = $request->query('categoryId');

        // البحث في الأدوية الموجودة في مخزون هذه الصيدلية فقط
        $inventoriesQuery = Inventory::with('drug')
            ->where('pharmacy_id', $pharmacyId)
            ->whereHas('drug');
        
        // تطبيق فلاتر البحث على الأدوية
        if ($query || $catName) {
            $inventoriesQuery->whereHas('drug', function($q) use ($query, $catName) {
                if ($query) {
                    $q->where(function($subQ) use ($query) {
                        $subQ->where('name', 'like', "%{$query}%")
                             ->orWhere('generic_name', 'like', "%{$query}%");
                    });
                }
                if ($catName) {
                    $q->where('category', $catName);
                }
            });
        }
        
        $inventories = $inventoriesQuery->orderBy('created_at', 'desc')->get();

        // تحويل البيانات إلى الشكل المطلوب
        $result = $inventories->map(function ($inventory) {
            $drug = $inventory->drug;
            
            if (!$drug) {
                return null;
            }
            
            return [
                'id' => $inventory->id,
                'drugCode' => $drug->id,
                'drugName' => $drug->name,
                'genericName' => $drug->generic_name,
                'strength' => $drug->strength,
                'form' => $drug->form,
                'category' => $drug->category,
                'categoryId' => $drug->category,
                'unit' => $drug->unit,
                'maxMonthlyDose' => $drug->max_monthly_dose,
                'status' => $drug->status,
                'manufacturer' => $drug->manufacturer,
                'country' => $drug->country,
                'utilizationType' => $drug->utilization_type,
                'quantity' => $inventory->current_quantity,
                'neededQuantity' => $inventory->minimum_level,
                'expiryDate' => $drug->expiry_date ? date('Y/m/d', strtotime($drug->expiry_date)) : null,
                'description' => $drug->description ?? '',
                'type' => $drug->form ?? 'Tablet',
            ];
        })->filter(); // إزالة القيم null

        return $this->sendSuccess($result->values(), 'تم البحث بنجاح.');
    }
    
}
