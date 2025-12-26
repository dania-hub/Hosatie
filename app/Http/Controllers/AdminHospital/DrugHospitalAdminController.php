<?php

namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Drug;
use App\Models\Inventory;
use App\Models\Pharmacy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DrugHospitalAdminController extends BaseApiController
{
    /**
     * GET /api/admin-hospital/drugs
     * عرض جميع الأدوية من جميع الصيدليات في المستشفى مع تجميع الكميات
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
            }

            $hospitalId = $user->hospital_id;
            
            if (!$hospitalId) {
                return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
            }

            // جلب جميع الصيدليات في المستشفى
            $pharmacies = Pharmacy::where('hospital_id', $hospitalId)
                ->where('status', 'active')
                ->get();
            
            $pharmacyIds = $pharmacies->pluck('id')->toArray();

            // جلب جميع المستودعات النشطة في المستشفى
            $warehouses = \App\Models\Warehouse::where('hospital_id', $hospitalId)
                ->where('status', 'active')
                ->get();
            
            $warehouseIds = $warehouses->pluck('id')->toArray();

            // إذا لم توجد صيدليات ولا مستودعات، نرجع قائمة فارغة
            if (empty($pharmacyIds) && empty($warehouseIds)) {
                return $this->sendSuccess([], 'لا توجد صيدليات أو مستودعات نشطة في المستشفى.');
            }

            // تسجيل للمساعدة في التصحيح
            Log::debug('Hospital Pharmacies and Warehouses', [
                'hospital_id' => $hospitalId,
                'pharmacy_ids' => $pharmacyIds,
                'pharmacy_names' => $pharmacies->pluck('name')->toArray(),
                'warehouse_ids' => $warehouseIds,
            ]);

            // جلب المخزون من جميع الصيدليات والمستودعات في المستشفى فقط
            // استخدام joins للتأكد من أن الصيدليات والمستودعات تتبع المستشفى الصحيح
            $inventoriesQuery = Inventory::query()
                ->where(function($query) use ($hospitalId, $pharmacyIds, $warehouseIds) {
                    // المخزون من الصيدليات التابعة للمستشفى
                    if (!empty($pharmacyIds)) {
                        $query->where(function($q) use ($hospitalId, $pharmacyIds) {
                            $q->whereNotNull('pharmacy_id')
                              ->whereNull('warehouse_id')
                              ->whereIn('pharmacy_id', $pharmacyIds)
                              // تأكيد إضافي: التأكد من أن الصيدلية تتبع المستشفى الصحيح
                              ->whereHas('pharmacy', function($pharmacyQuery) use ($hospitalId) {
                                  $pharmacyQuery->where('hospital_id', $hospitalId)
                                                 ->where('status', 'active');
                              });
                        });
                    }
                    
                    // المخزون من المستودعات التابعة للمستشفى
                    if (!empty($warehouseIds)) {
                        if (!empty($pharmacyIds)) {
                            $query->orWhere(function($q) use ($hospitalId, $warehouseIds) {
                                $q->whereNotNull('warehouse_id')
                                  ->whereNull('pharmacy_id')
                                  ->whereIn('warehouse_id', $warehouseIds)
                                  // تأكيد إضافي: التأكد من أن المستودع يتبع المستشفى الصحيح
                                  ->whereHas('warehouse', function($warehouseQuery) use ($hospitalId) {
                                      $warehouseQuery->where('hospital_id', $hospitalId)
                                                      ->where('status', 'active');
                                  });
                            });
                        } else {
                            $query->where(function($q) use ($hospitalId, $warehouseIds) {
                                $q->whereNotNull('warehouse_id')
                                  ->whereNull('pharmacy_id')
                                  ->whereIn('warehouse_id', $warehouseIds)
                                  // تأكيد إضافي: التأكد من أن المستودع يتبع المستشفى الصحيح
                                  ->whereHas('warehouse', function($warehouseQuery) use ($hospitalId) {
                                      $warehouseQuery->where('hospital_id', $hospitalId)
                                                      ->where('status', 'active');
                                  });
                            });
                        }
                    }
                });
            
            $inventories = $inventoriesQuery->with(['pharmacy', 'warehouse', 'drug'])->get()->groupBy('drug_id');
            
            // جلب الأدوية فقط التي لها مخزون في الصيدليات أو المستودعات التابعة للمستشفى
            $drugIds = $inventories->keys()->toArray();
            
            if (empty($drugIds)) {
                return $this->sendSuccess([], 'لا توجد أدوية في مخزون المستشفى حالياً.');
            }
            
            $drugs = Drug::whereIn('id', $drugIds)->orderBy('name')->get();
            
            // تسجيل للمساعدة في التصحيح
            $allInventories = $inventoriesQuery->get();
            Log::debug('Inventory Data', [
                'pharmacy_ids' => $pharmacyIds,
                'warehouse_ids' => $warehouseIds,
                'total_inventories' => $allInventories->count(),
                'drugs_with_inventory' => $inventories->keys()->toArray(),
                'all_inventories_by_drug' => $allInventories->groupBy('drug_id')->map(function($items, $drugId) {
                    return [
                        'drug_id' => $drugId,
                        'total_quantity' => $items->sum('current_quantity'),
                        'items' => $items->map(function($inv) {
                            return [
                                'id' => $inv->id,
                                'pharmacy_id' => $inv->pharmacy_id,
                                'warehouse_id' => $inv->warehouse_id,
                                'pharmacy_name' => $inv->pharmacy?->name,
                                'warehouse_name' => $inv->warehouse?->name,
                                'quantity' => $inv->current_quantity,
                                'minimum_level' => $inv->minimum_level,
                            ];
                        })->toArray(),
                    ];
                })->toArray(),
            ]);

            // تجميع البيانات: لكل دواء، نجمع الكميات من جميع الصيدليات
            $result = $drugs->map(function ($drug) use ($inventories) {
                $drugInventories = $inventories->get($drug->id, collect());
                
                // حساب الكمية الإجمالية من جميع الصيدليات (باستخدام reduce للتأكد من الدقة)
                $totalQuantity = $drugInventories->reduce(function ($carry, $inventory) {
                    return $carry + (int)($inventory->current_quantity ?? 0);
                }, 0);
                
                // حساب الحد الأدنى الإجمالي (نجمع جميع القيم)
                $totalMinimumLevel = $drugInventories->reduce(function ($carry, $inventory) {
                    $minLevel = (int)($inventory->minimum_level ?? 0);
                    return $carry + $minLevel;
                }, 0);
                
                // معلومات الصيدليات والمستودعات التي تحتوي على هذا الدواء
                $pharmaciesInfo = $drugInventories->map(function ($inventory) {
                    $locationType = $inventory->pharmacy_id ? 'pharmacy' : 'warehouse';
                    $locationId = $inventory->pharmacy_id ?? $inventory->warehouse_id;
                    $locationName = $inventory->pharmacy?->name ?? $inventory->warehouse?->name ?? 'غير معروف';
                    
                    return [
                        'location_type' => $locationType,
                        'pharmacy_id' => $inventory->pharmacy_id,
                        'warehouse_id' => $inventory->warehouse_id,
                        'location_id' => $locationId,
                        'location_name' => $locationName,
                        'quantity' => (int)($inventory->current_quantity ?? 0),
                        'minimum_level' => (int)($inventory->minimum_level ?? 0),
                        'inventory_id' => $inventory->id,
                    ];
                })->values();

                // تسجيل للمساعدة في التصحيح
                if ($drugInventories->isNotEmpty()) {
                    Log::debug('Drug aggregation', [
                        'drug_id' => $drug->id,
                        'drug_name' => $drug->name,
                        'inventories_count' => $drugInventories->count(),
                        'inventories_raw' => $drugInventories->map(function($inv) {
                            return [
                                'id' => $inv->id,
                                'pharmacy_id' => $inv->pharmacy_id,
                                'current_quantity' => $inv->current_quantity,
                                'minimum_level' => $inv->minimum_level,
                            ];
                        })->toArray(),
                        'total_quantity' => $totalQuantity,
                        'pharmacies_count' => $pharmaciesInfo->count(),
                        'pharmacies_details' => $pharmaciesInfo->toArray(),
                    ]);
                }

                return [
                    'id' => $drugInventories->first()?->id ?? null, // ID أول مخزون إن وجد
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
                    'quantity' => $totalQuantity, // الكمية الإجمالية من جميع الصيدليات
                    'neededQuantity' => $totalMinimumLevel, // الحد الأدنى المطلوب (مجموع من جميع الصيدليات والمستودعات)
                    'expiryDate' => $drug->expiry_date ? date('Y/m/d', strtotime($drug->expiry_date)) : null,
                    'description' => $drug->description ?? '',
                    'type' => $drug->form ?? 'Tablet',
                    'pharmacies' => $pharmaciesInfo, // معلومات الصيدليات التي تحتوي على الدواء
                    'pharmacyCount' => $pharmaciesInfo->count(), // عدد الصيدليات التي تحتوي على الدواء
                ];
            })
            ->filter(function($drug) {
                // استبعاد الأدوية التي ليس لها مخزون (quantity = 0)
                return $drug['quantity'] > 0;
            })
            ->values(); // إعادة فهرسة المصفوفة

            return $this->sendSuccess($result, 'تم جلب قائمة الأدوية من جميع الصيدليات بنجاح.');

        } catch (\Exception $e) {
            Log::error('Get Hospital Drugs Error: ' . $e->getMessage(), [
                'exception' => $e,
                'hospital_id' => $hospitalId ?? null
            ]);
            return $this->sendError('فشل في جلب قائمة الأدوية.', [], 500);
        }
    }

    /**
     * GET /api/admin-hospital/drugs/all
     * جلب جميع الأدوية للبحث (بدون معلومات المخزون)
     */
    public function searchAll(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->sendError('المستخدم غير مسجل دخول.', [], 401);
            }

            $search = $request->query('search', '');

            $query = Drug::select('id', 'name', 'generic_name', 'strength', 'form', 'category', 'unit');

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('generic_name', 'like', "%{$search}%");
                });
            }

            $drugs = $query->orderBy('name')->limit(100)->get();

            return $this->sendSuccess($drugs, 'تم جلب قائمة الأدوية للبحث بنجاح.');

        } catch (\Exception $e) {
            Log::error('Search Hospital Drugs Error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->sendError('فشل في البحث عن الأدوية.', [], 500);
        }
    }

    /**
     * GET /api/admin-hospital/categories
     * جلب قائمة الفئات
     */
    public function categories(Request $request)
    {
        try {
            $categories = Drug::select('category')
                ->distinct()
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->orderBy('category')
                ->pluck('category');

            return $this->sendSuccess($categories, 'تم جلب قائمة الفئات بنجاح.');

        } catch (\Exception $e) {
            Log::error('Get Categories Error: ' . $e->getMessage(), ['exception' => $e]);
            return $this->sendError('فشل في جلب قائمة الفئات.', [], 500);
        }
    }
}

