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
     * عرض الأدوية الموجودة حالياً في "مخزن الصيدلية" فقط.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $pharmacyId = $this->getPharmacistPharmacyId($user);

        if (!$pharmacyId) {
            return $this->sendError('لم يتم العثور على صيدلية مرتبطة بهذا المستخدم.');
        }

        // جلب المخزون المرتبط بهذه الصيدلية فقط
        $inventory = Inventory::with('drug')
            ->where('pharmacy_id', $pharmacyId) // <--- التصفية حسب الصيدلية
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
                    'type' => $item->drug->form ?? 'Tablet'
                ];
            });

        return $this->sendSuccess($inventory, 'تم تحميل قائمة أدوية الصيدلية بنجاح.');
    }

    /**
     * GET /api/pharmacist/drugs/all
     * البحث في قاعدة بيانات الأدوية العامة (لإضافتها لمخزون الصيدلية).
     * (لم يتغير هذا الجزء لأنه يبحث في جدول drug العام)
     */
    public function searchAll(Request $request)
    {
        $drugs = Drug::select('id', 'name as drugName', 'category')
            ->limit(20)
            ->get();
            
        return $this->sendSuccess($drugs, 'تم جلب قائمة الأدوية العامة.');
    }

    /**
     * POST /api/pharmacist/drugs
     * إضافة دواء جديد إلى "مخزون الصيدلية".
     */
    public function store(Request $request)
    {
        $request->validate([
            'drugId' => 'required|exists:drug,id',
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
                    'drugName' => $item->drug->name,
                    'quantity' => $item->current_quantity,
                    'neededQuantity' => $item->minimum_level,
                    'expiryDate' => $item->drug->expiry_date
                ];
            });

        return $this->sendSuccess($lowStock, 'تم جلب الأدوية منخفضة المخزون.');
    }

    /**
     * GET /api/pharmacist/drugs/search
     * البحث داخل مخزون الصيدلية.
     */
    public function search(Request $request)
    {
        $user = $request->user();
        $pharmacyId = $this->getPharmacistPharmacyId($user);
        
        $query = $request->query('search');
        $catName = $request->query('categoryId');

        $inventory = Inventory::with('drug')
            ->where('pharmacy_id', $pharmacyId) // <--- البحث داخل الصيدلية فقط
            ->whereHas('drug', function($q) use ($query, $catName) {
                if ($query) {
                    $q->where('name', 'like', "%{$query}%");
                }
                if ($catName) {
                    $q->where('category', $catName);
                }
            })
            ->get();

        return $this->sendSuccess($inventory, 'تم البحث بنجاح.');
    }
    
}
