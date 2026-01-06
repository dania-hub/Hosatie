<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Drug;
use App\Models\Inventory;
use App\Models\Pharmacy;
use App\Models\Prescription;
use App\Models\PrescriptionDrug;
use App\Models\User;
use App\Models\Dispensing;
use Carbon\Carbon;
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
     * عرض الأدوية الموجودة في مخزون الصيدلية بالإضافة للأدوية غير المسجلة ولكن موصوفة للمرضى.
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

        // دالة مساعدة لحساب الكمية المحتاجة بناءً على المرضى المستحقين
        $calculateNeededQuantity = function($drugId, $availableQuantity, $hospitalId, $pharmacyId) {
            // جلب جميع الوصفات النشطة التي تحتوي على هذا الدواء
            $activePrescriptions = Prescription::where('hospital_id', $hospitalId)
                ->where('status', 'active')
                ->whereHas('patient', function($query) use ($hospitalId) {
                    $query->where('hospital_id', $hospitalId)
                          ->where('type', 'patient');
                })
                ->whereHas('drugs', function($query) use ($drugId) {
                    $query->where('drugs.id', $drugId);
                })
                ->with(['drugs' => function($query) use ($drugId) {
                    $query->where('drugs.id', $drugId);
                }])
                ->get();

            $totalNeededQuantity = 0;
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();

            foreach ($activePrescriptions as $prescription) {
                foreach ($prescription->drugs as $drug) {
                    if ($drug->id != $drugId) continue;

                    // حساب الكمية الشهرية المطلوبة
                    $monthlyQty = (int)($drug->pivot->monthly_quantity ?? 0);
                    if ($monthlyQty === 0 && isset($drug->pivot->daily_quantity)) {
                        $monthlyQty = (int)($drug->pivot->daily_quantity ?? 0) * 30;
                    }

                    if ($monthlyQty > 0) {
                        // حساب الكمية المصروفة في الشهر الحالي
                        $totalDispensedThisMonth = (int)Dispensing::where('patient_id', $prescription->patient_id)
                            ->where('drug_id', $drugId)
                            ->where('reverted', false)
                            ->whereBetween('dispense_month', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
                            ->sum('quantity_dispensed');

                        // حساب الكمية المتبقية (الكمية المطلوبة - المصروفة)
                        // المريض مستحق إذا كانت الكمية المتبقية > 0
                        $remainingQuantity = max(0, $monthlyQty - $totalDispensedThisMonth);
                        
                        // إضافة الكمية المتبقية من المرضى المستحقين
                        if ($remainingQuantity > 0) {
                            $totalNeededQuantity += $remainingQuantity;
                        }
                    }
                }
            }

            // الكمية المحتاجة = مجموع الكميات المتبقية من المرضى المستحقين - الكمية المتوفرة
            // إذا كانت النتيجة <= 0، تصبح 0
            $neededQuantity = max(0, $totalNeededQuantity - $availableQuantity);
            
            return $neededQuantity;
        };

        // تحويل البيانات إلى الشكل المطلوب للأدوية المسجلة
        $registeredDrugs = $inventories->map(function ($inventory) use ($calculateNeededQuantity, $user, $pharmacyId) {
            $drug = $inventory->drug;
            
            if (!$drug) {
                return null;
            }
            
            $availableQuantity = $inventory->current_quantity;
            $hospitalId = $user->hospital_id;
            
            // حساب الكمية المحتاجة ديناميكياً
            $neededQuantity = 0;
            if ($hospitalId) {
                $neededQuantity = $calculateNeededQuantity(
                    $drug->id,
                    $availableQuantity,
                    $hospitalId,
                    $pharmacyId
                );
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
                'quantity' => $availableQuantity, // الكمية في المخزون
                'neededQuantity' => $neededQuantity, // الكمية المحتاجة المحسوبة ديناميكياً
                'expiryDate' => $drug->expiry_date ? date('Y/m/d', strtotime($drug->expiry_date)) : null,
                'description' => $drug->description ?? '',
                'type' => $drug->form ?? 'Tablet',
                'isUnregistered' => false // دواء مسجل في الصيدلية
            ];
        })->filter(); // إزالة القيم null

        // جلب معرفات الأدوية المسجلة في الصيدلية
        $registeredDrugIds = $inventories->pluck('drug_id')->toArray();

        // جلب الأدوية غير المسجلة ولكن موصوفة للمرضى
        $unregisteredDrugs = collect();
        
        // الحصول على hospital_id من المستخدم
        $hospitalId = $user->hospital_id;
        
        if ($hospitalId) {
            // جلب جميع الوصفات النشطة للمرضى في نفس المستشفى فقط
            // التأكد من أن الوصفة والمريض كلاهما من نفس المستشفى
            $activePrescriptions = Prescription::where('hospital_id', $hospitalId)
                ->where('status', 'active')
                ->whereHas('patient', function($query) use ($hospitalId) {
                    $query->where('hospital_id', $hospitalId)
                          ->where('type', 'patient');
                })
                ->with('drugs')
                ->get();

            // جمع جميع الأدوية من الوصفات النشطة
            $prescriptionDrugs = collect();
            foreach ($activePrescriptions as $prescription) {
                foreach ($prescription->drugs as $drug) {
                    $monthlyQty = (int)($drug->pivot->monthly_quantity ?? 0);
                    // إذا كانت الكمية الشهرية 0، نحسبها من الكمية اليومية
                    if ($monthlyQty === 0 && isset($drug->pivot->daily_quantity)) {
                        $monthlyQty = (int)($drug->pivot->daily_quantity ?? 0) * 30;
                    }
                    if ($monthlyQty > 0) {
                        $prescriptionDrugs->push([
                            'drug_id' => $drug->id,
                            'monthly_quantity' => $monthlyQty
                        ]);
                    }
                }
            }

            // تجميع الأدوية حسب drug_id وحساب المجموع الشهري
            $unregisteredDrugsData = $prescriptionDrugs
                ->whereNotIn('drug_id', $registeredDrugIds) // استبعاد الأدوية المسجلة
                ->groupBy('drug_id')
                ->map(function ($items, $drugId) {
                    return [
                        'drug_id' => $drugId,
                        'total_monthly_quantity' => $items->sum('monthly_quantity')
                    ];
                })
                ->values();

            // جلب بيانات الأدوية من قاعدة البيانات
            $drugIds = $unregisteredDrugsData->pluck('drug_id')->toArray();
            
            if (!empty($drugIds)) {
                $drugs = Drug::whereIn('id', $drugIds)->get()->keyBy('id');
                
                // حساب الكمية المحتاجة للأدوية غير المسجلة بناءً على المرضى المستحقين
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                
                $unregisteredDrugs = $unregisteredDrugsData->map(function ($item) use ($drugs, $activePrescriptions, $startOfMonth, $endOfMonth) {
                    $drug = $drugs->get($item['drug_id']);
                    
                    if (!$drug) {
                        return null;
                    }
                    
                    // حساب الكمية المحتاجة من المرضى المستحقين فقط
                    $totalNeededQuantity = 0;
                    
                    foreach ($activePrescriptions as $prescription) {
                        foreach ($prescription->drugs as $prescriptionDrug) {
                            if ($prescriptionDrug->id != $drug->id) continue;
                            
                            // حساب الكمية الشهرية المطلوبة
                            $monthlyQty = (int)($prescriptionDrug->pivot->monthly_quantity ?? 0);
                            if ($monthlyQty === 0 && isset($prescriptionDrug->pivot->daily_quantity)) {
                                $monthlyQty = (int)($prescriptionDrug->pivot->daily_quantity ?? 0) * 30;
                            }
                            
                            if ($monthlyQty > 0) {
                                // حساب الكمية المصروفة في الشهر الحالي
                                $totalDispensedThisMonth = (int)Dispensing::where('patient_id', $prescription->patient_id)
                                    ->where('drug_id', $drug->id)
                                    ->where('reverted', false)
                                    ->whereBetween('dispense_month', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
                                    ->sum('quantity_dispensed');
                                
                                // حساب الكمية المتبقية (الكمية المطلوبة - المصروفة)
                                $remainingQuantity = max(0, $monthlyQty - $totalDispensedThisMonth);
                                
                                // إضافة الكمية المتبقية (لأن الدواء غير متوفر في المخزون)
                                $totalNeededQuantity += $remainingQuantity;
                            }
                        }
                    }
                    
                    return [
                        'id' => 'unregistered_' . $drug->id, // معرف مؤقت للدواء غير المسجل
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
                        'quantity' => 0, // الكمية في المخزون = 0 لأنها غير مسجلة
                        'neededQuantity' => $totalNeededQuantity, // الكمية المحتاجة من المرضى المستحقين
                        'expiryDate' => $drug->expiry_date ? date('Y/m/d', strtotime($drug->expiry_date)) : null,
                        'description' => $drug->description ?? '',
                        'type' => $drug->form ?? 'Tablet',
                        'isUnregistered' => true // دواء غير مسجل في الصيدلية
                    ];
                })->filter(); // إزالة القيم null
            }
        }

        // دمج الأدوية المسجلة وغير المسجلة
        $result = $registeredDrugs->merge($unregisteredDrugs);

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
