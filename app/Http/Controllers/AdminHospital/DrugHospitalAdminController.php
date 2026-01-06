<?php

namespace App\Http\Controllers\AdminHospital;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Drug;
use App\Models\Inventory;
use App\Models\Pharmacy;
use App\Models\Prescription;
use App\Models\PrescriptionDrug;
use App\Models\Dispensing;
use App\Models\InternalSupplyRequest;
use App\Models\InternalSupplyRequestItem;
use App\Models\User;
use Carbon\Carbon;
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
            
            // جلب الأدوية التي لها مخزون في الصيدليات أو المستودعات التابعة للمستشفى
            $drugIds = $inventories->keys()->toArray();
            
            // جلب طلبات التوريد الداخلية من نفس المستشفى والتي حالتها "جديد" فقط (لحساب الكمية المحتاجة للمستودع)
            $internalRequests = InternalSupplyRequest::where('status', 'pending')
                ->whereHas('pharmacy', function($query) use ($hospitalId) {
                    $query->where('hospital_id', $hospitalId);
                })
                ->with(['items.drug'])
                ->get();

            // جمع جميع الأدوية من الطلبات مع الكميات المطلوبة للمستودع
            $warehouseRequestItems = collect();
            foreach ($internalRequests as $request) {
                foreach ($request->items as $item) {
                    if ($item->drug) {
                        $qty = (int)($item->requested_qty ?? 0);
                        if ($qty > 0) {
                            $warehouseRequestItems->push([
                                'drug_id' => $item->drug_id,
                                'requested_qty' => $qty,
                            ]);
                        }
                    }
                }
            }

            // تجميع الأدوية حسب drug_id وحساب المجموع المطلوب من جميع الطلبات للمستودع
            $warehouseDrugsRequestedQuantities = $warehouseRequestItems
                ->groupBy('drug_id')
                ->map(function ($items, $drugId) {
                    return [
                        'drug_id' => $drugId,
                        'total_requested_qty' => $items->sum('requested_qty')
                    ];
                });
            
            // جلب الأدوية غير المسجلة التي لديها كمية محتاجة
            // 1. الأدوية الموصوفة للمرضى (لحساب الكمية المحتاجة للصيدلية)
            $activePrescriptions = Prescription::where('hospital_id', $hospitalId)
                ->where('status', 'active')
                ->whereHas('patient', function($query) use ($hospitalId) {
                    $query->where('hospital_id', $hospitalId)
                          ->where('type', 'patient');
                })
                ->with('drugs')
                ->get();

            $prescriptionDrugIds = collect();
            foreach ($activePrescriptions as $prescription) {
                foreach ($prescription->drugs as $drug) {
                    $prescriptionDrugIds->push($drug->id);
                }
            }
            $prescriptionDrugIds = $prescriptionDrugIds->unique()->toArray();

            // 2. الأدوية المطلوبة في الطلبات الداخلية (لحساب الكمية المحتاجة للمستودع)
            $warehouseRequestDrugIds = $warehouseDrugsRequestedQuantities->keys()->toArray();

            // دمج جميع معرفات الأدوية (المسجلة وغير المسجلة)
            $allDrugIds = array_unique(array_merge($drugIds, $prescriptionDrugIds, $warehouseRequestDrugIds));
            
            if (empty($allDrugIds)) {
                return $this->sendSuccess([], 'لا توجد أدوية في مخزون المستشفى حالياً.');
            }
            
            $drugs = Drug::whereIn('id', $allDrugIds)->orderBy('name')->get();

            // دالة مساعدة لحساب الكمية المحتاجة للصيدلية بناءً على المرضى المستحقين
            $calculatePharmacyNeededQuantity = function($drugId, $availablePharmacyQuantity, $hospitalId) {
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
                            $remainingQuantity = max(0, $monthlyQty - $totalDispensedThisMonth);
                            
                            // إذا كان الدواء متوفراً والكمية > 0، نضيف الكمية المتبقية
                            if ($availablePharmacyQuantity > 0 && $remainingQuantity > 0) {
                                $totalNeededQuantity += $remainingQuantity;
                            } elseif ($availablePharmacyQuantity <= 0) {
                                // إذا لم يكن الدواء متوفراً، نضيف الكمية المتبقية كاملة
                                $totalNeededQuantity += $remainingQuantity;
                            }
                        }
                    }
                }

                // الكمية المحتاجة = مجموع الكميات المتبقية من المرضى المستحقين - الكمية المتوفرة
                // إذا كانت النتيجة <= 0، تصبح 0
                $neededQuantity = max(0, $totalNeededQuantity - $availablePharmacyQuantity);
                
                return $neededQuantity;
            };

            // تجميع البيانات: لكل دواء، نجمع الكميات من جميع الصيدليات والمستودعات بشكل منفصل
            $result = $drugs->map(function ($drug) use ($inventories, $hospitalId, $warehouseDrugsRequestedQuantities, $calculatePharmacyNeededQuantity) {
                $drugInventories = $inventories->get($drug->id, collect());
                
                // حساب الكمية الإجمالية من جميع الصيدليات
                $pharmacyQuantity = $drugInventories
                    ->filter(function($inv) {
                        return $inv->pharmacy_id !== null && $inv->warehouse_id === null;
                    })
                    ->reduce(function ($carry, $inventory) {
                        return $carry + (int)($inventory->current_quantity ?? 0);
                    }, 0);
                
                // حساب الكمية الإجمالية من جميع المستودعات
                $warehouseQuantity = $drugInventories
                    ->filter(function($inv) {
                        return $inv->warehouse_id !== null && $inv->pharmacy_id === null;
                    })
                    ->reduce(function ($carry, $inventory) {
                        return $carry + (int)($inventory->current_quantity ?? 0);
                    }, 0);
                
                // حساب الكمية الإجمالية (للتوافق مع الكود القديم)
                $totalQuantity = $pharmacyQuantity + $warehouseQuantity;
                
                // حساب الكمية المحتاجة للصيدلية من الوصفات النشطة
                $pharmacyNeededQuantity = 0;
                if ($hospitalId) {
                    $pharmacyNeededQuantity = $calculatePharmacyNeededQuantity(
                        $drug->id,
                        $pharmacyQuantity,
                        $hospitalId
                    );
                }
                
                // حساب الكمية المحتاجة للمستودع من الطلبات الداخلية
                $warehouseNeededQuantity = 0;
                if ($warehouseDrugsRequestedQuantities->has($drug->id)) {
                    $totalRequestedQty = $warehouseDrugsRequestedQuantities->get($drug->id)['total_requested_qty'];
                    // الكمية المحتاجة = مجموع الكميات المطلوبة من الطلبات - الكمية المتوفرة
                    // إذا كانت النتيجة <= 0، تصبح 0
                    $warehouseNeededQuantity = max(0, $totalRequestedQty - $warehouseQuantity);
                }
                
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
                    'quantity' => $totalQuantity, // الكمية الإجمالية (للتوافق مع الكود القديم)
                    'pharmacyQuantity' => $pharmacyQuantity, // الكمية المتوفرة في الصيدليات
                    'warehouseQuantity' => $warehouseQuantity, // الكمية المتوفرة في المستودعات
                    'neededQuantity' => $pharmacyNeededQuantity + $warehouseNeededQuantity, // الكمية المحتاجة الإجمالية (للتوافق مع الكود القديم)
                    'pharmacyNeededQuantity' => $pharmacyNeededQuantity, // الكمية المحتاجة للصيدليات
                    'warehouseNeededQuantity' => $warehouseNeededQuantity, // الكمية المحتاجة للمستودعات
                    'expiryDate' => $drug->expiry_date ? date('Y/m/d', strtotime($drug->expiry_date)) : null,
                    'description' => $drug->description ?? '',
                    'type' => $drug->form ?? 'Tablet',
                    'pharmacies' => $pharmaciesInfo, // معلومات الصيدليات التي تحتوي على الدواء
                    'pharmacyCount' => $pharmaciesInfo->count(), // عدد الصيدليات التي تحتوي على الدواء
                ];
            })
            ->filter(function($drug) {
                // عرض الأدوية التي لديها مخزون أو كمية محتاجة
                return $drug['quantity'] > 0 || $drug['pharmacyNeededQuantity'] > 0 || $drug['warehouseNeededQuantity'] > 0;
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

