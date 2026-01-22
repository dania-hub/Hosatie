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
use App\Models\AuditLog;
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
     * دالة مساعدة لجلب الأدوية المُصفرة من audit_log
     */
    private function getExpiredDrugsFromAuditLog($hospitalId)
    {
        $expiredDrugsLogs = AuditLog::where('action', 'drug_expired_zeroed')
            ->where('hospital_id', $hospitalId)
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

        return $expiredDrugs;
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

        // جمع معلومات الأدوية المنتهية قبل التصفير وحفظها في audit_log
        $expiredDrugsInfo = collect();
        $today = Carbon::now()->format('Y-m-d');
        
        // جلب الأدوية المنتهية الصلاحية من المخزون قبل التصفير
        $expiredInventories = DB::table('inventories')
            ->join('drugs', 'inventories.drug_id', '=', 'drugs.id')
            ->where('inventories.pharmacy_id', $pharmacyId)
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
            // التحقق من عدم وجود نفس الدواء محفوظاً في audit_log من نفس المستشفى والصيدلية
            // نبحث في new_values حسب معرف الدواء والصيدلية لتجنب التكرار
            $drugExpiryDate = $expired->expiry_date ? date('Y/m/d', strtotime($expired->expiry_date)) : null;
            
            // جلب جميع السجلات المتعلقة بهذا الدواء والصيدلية
            $existingLogs = AuditLog::where('action', 'drug_expired_zeroed')
                ->where('hospital_id', $user->hospital_id)
                ->where('table_name', 'inventories')
                ->get();
            
            $exists = false;
            foreach ($existingLogs as $log) {
                $newValues = json_decode($log->new_values, true);
                if ($newValues && 
                    isset($newValues['drugId']) && $newValues['drugId'] == $expired->drug_id &&
                    isset($newValues['pharmacyId']) && $newValues['pharmacyId'] == $pharmacyId &&
                    isset($newValues['expiryDate']) && $newValues['expiryDate'] == $drugExpiryDate) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                // حفظ في audit_log
                AuditLog::create([
                    'user_id' => $user->id,
                    'hospital_id' => $user->hospital_id,
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
                        'pharmacyId' => $pharmacyId,
                    ]),
                    'ip_address' => $request->ip() ?? request()->ip(),
                ]);

                // تصفير الكمية فعلياً في قاعدة البيانات
                DB::table('inventories')
                    ->where('id', $expired->inventory_id)
                    ->update(['current_quantity' => 0]);
            }
            
            $expiredDrugsInfo->push([
                'drugName' => $expired->drug_name,
                'strength' => $expired->strength ?? null,
                'quantity' => $expired->current_quantity,
                'expiryDate' => $drugExpiryDate,
            ]);
        }
        
        // جلب جميع الأدوية المُصفرة من audit_log (للعرض الدائم)
        $expiredDrugsFromLog = $this->getExpiredDrugsFromAuditLog($user->hospital_id);

        // جلب الأدوية الموجودة في مخزون هذه الصيدلية فقط
        // سيتم التصفير التلقائي عند جلب البيانات بسبب Inventory::boot()
        $inventoriesGrouped = Inventory::with('drug')
            ->where('pharmacy_id', $pharmacyId)
            ->whereHas('drug', function($q) {
                $q->where('status', '!=', Drug::STATUS_ARCHIVED);
            })
            ->get()
            ->groupBy('drug_id');

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
        $registeredDrugs = $inventoriesGrouped->map(function ($group) use ($calculateNeededQuantity, $user, $pharmacyId) {
            $firstInventory = $group->first();
            $drug = $firstInventory->drug;
            
            if (!$drug) {
                return null;
            }
            
            $availableQuantity = $group->sum('current_quantity');
            $hospitalId = $user->hospital_id;
            
            // تفاصيل الدفعات (الشحنات)
            $batches = $group->map(function ($inv) {
                return [
                    'batchNumber' => $inv->batch_number ?? 'غير محدد',
                    'expiryDate' => $inv->expiry_date ? date('Y/m/d', strtotime($inv->expiry_date)) : 'غير محدد',
                    'quantity' => $inv->current_quantity,
                ];
            })->values();

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
            
            // تحديث minimum_level في قاعدة البيانات تلقائياً بالقيمة المحسوبة
            foreach ($group as $inventory) {
                if ($inventory->minimum_level != $neededQuantity) {
                    $inventory->minimum_level = $neededQuantity;
                    $inventory->save();
                }
            }
            
            return [
                'id' => $drug->id, // ID الدواء للمجموعات
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
                'warnings' => $drug->warnings,
                'indications' => $drug->indications,
                'contraindications' => $drug->contraindications,
                'quantity' => $availableQuantity, // الكمية في المخزون
                'neededQuantity' => $neededQuantity, // الكمية المحتاجة المحسوبة ديناميكياً
                'batches' => $batches,
                'description' => $drug->description ?? '',
                'type' => $drug->form ?? 'Tablet',
                'isUnregistered' => false // دواء مسجل في الصيدلية
            ];
        })->filter(); // إزالة القيم null

        // جلب معرفات الأدوية المسجلة في الصيدلية
        $registeredDrugIds = $inventoriesGrouped->keys()->toArray();

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
                // فلترة الأدوية المؤرشفة - حتى لو كانت موصوفة للمرضى
                $drugs = Drug::whereIn('id', $drugIds)
                    ->where('status', '!=', Drug::STATUS_ARCHIVED)
                    ->get()
                    ->keyBy('id');
                
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
                        'warnings' => $drug->warnings,
                        'indications' => $drug->indications,
                        'contraindications' => $drug->contraindications,
                        'quantity' => 0, // الكمية في المخزون = 0 لأنها غير مسجلة
                        'neededQuantity' => $totalNeededQuantity, // الكمية المحتاجة من المرضى المستحقين
                        'description' => $drug->description ?? '',
                        'type' => $drug->form ?? 'Tablet',
                        'isUnregistered' => true // دواء غير مسجل في الصيدلية
                    ];
                })->filter(); // إزالة القيم null
            }
        }

        // دمج الأدوية المسجلة وغير المسجلة
        $result = $registeredDrugs->merge($unregisteredDrugs);

        // دمج الأدوية المُصفرة الجديدة مع القديمة من audit_log (تجنب التكرار)
        $allExpiredDrugs = $expiredDrugsFromLog;
        foreach ($expiredDrugsInfo as $newExpired) {
            $exists = $allExpiredDrugs->contains(function ($existing) use ($newExpired) {
                return $existing['drugName'] === $newExpired['drugName'] && 
                       $existing['expiryDate'] === $newExpired['expiryDate'];
            });
            
            if (!$exists) {
                $allExpiredDrugs->push($newExpired);
            }
        }

        // إرجاع البيانات مع قائمة الأدوية المُصفرة من audit_log (دائمة)
        return $this->sendSuccess([
            'drugs' => $result->values(),
            'expiredDrugs' => $allExpiredDrugs->values()
        ], 'تم تحميل قائمة الأدوية بنجاح.');
    }

    /**
     * GET /api/pharmacist/drugs/{id}
     * جلب تفاصيل دواء معين من قاعدة البيانات
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $pharmacyId = $this->getPharmacistPharmacyId($user);
        $hospitalId = $user->hospital_id;

        if (!$pharmacyId) {
            return $this->sendError('المستخدم غير مرتبط بصيدلية.', [], 400);
        }

        // دالة مساعدة لحساب الكمية المحتاجة
        $calculateNeededQuantity = function($drugId, $availableQuantity, $hospitalId) {
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

                    $monthlyQty = (int)($drug->pivot->monthly_quantity ?? 0);
                    if ($monthlyQty === 0 && isset($drug->pivot->daily_quantity)) {
                        $monthlyQty = (int)($drug->pivot->daily_quantity ?? 0) * 30;
                    }

                    if ($monthlyQty > 0) {
                        $totalDispensedThisMonth = (int)Dispensing::where('patient_id', $prescription->patient_id)
                            ->where('drug_id', $drugId)
                            ->where('reverted', false)
                            ->whereBetween('dispense_month', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
                            ->sum('quantity_dispensed');

                        $remainingQuantity = max(0, $monthlyQty - $totalDispensedThisMonth);
                        if ($remainingQuantity > 0) {
                            $totalNeededQuantity += $remainingQuantity;
                        }
                    }
                }
            }

            return max(0, $totalNeededQuantity - $availableQuantity);
        };

        // التحقق من أن المعرف هو معرف Inventory أو معرف Drug غير مسجل
        if (strpos($id, 'unregistered_') === 0) {
            // دواء غير مسجل - جلب من جدول drugs مباشرة
            $drugId = str_replace('unregistered_', '', $id);
            $drug = Drug::find($drugId);
            
            if (!$drug) {
                return $this->sendError('الدواء غير موجود', [], 404);
            }

            // حساب الكمية المحتاجة
            $neededQuantity = $hospitalId ? $calculateNeededQuantity($drug->id, 0, $hospitalId) : 0;

            $data = [
                'id' => 'unregistered_' . $drug->id,
                'drugCode' => $drug->id,
                'drugName' => $drug->name,
                'name' => $drug->name,
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
                'indications' => $drug->indications,
                'warnings' => $drug->warnings,
                'contraindications' => $drug->contraindications,
                'quantity' => 0,
                'neededQuantity' => $neededQuantity,
                'isUnregistered' => true,
            ];

            return $this->sendSuccess($data, 'تم جلب تفاصيل الدواء بنجاح');
        } else {
            // دواء مسجل - جلب من Inventory مع معلومات Drug
            $inventory = Inventory::with('drug')
                ->where('pharmacy_id', $pharmacyId)
                ->find($id);

            if (!$inventory || !$inventory->drug) {
                return $this->sendError('الدواء غير موجود في المخزون', [], 404);
            }

            $drug = $inventory->drug;

            // حساب الكمية المحتاجة
            $neededQuantity = $hospitalId ? $calculateNeededQuantity($drug->id, $inventory->current_quantity, $hospitalId) : 0;
            
            // تحديث minimum_level في قاعدة البيانات تلقائياً بالقيمة المحسوبة
            if ($inventory->minimum_level != $neededQuantity) {
                $inventory->minimum_level = $neededQuantity;
                $inventory->save();
            }

            $data = [
                'id' => $inventory->id,
                'drugCode' => $drug->id,
                'drugName' => $drug->name,
                'name' => $drug->name,
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
                'indications' => $drug->indications,
                'warnings' => $drug->warnings,
                'contraindications' => $drug->contraindications,
                'quantity' => $inventory->current_quantity,
                'neededQuantity' => $neededQuantity,
                'expiryDate' => $inventory->expiry_date ? date('Y/m/d', strtotime($inventory->expiry_date)) : null,
                'isUnregistered' => false,
            ];

            return $this->sendSuccess($data, 'تم جلب تفاصيل الدواء بنجاح');
        }
    }

    /**
     * GET /api/pharmacist/drugs/all
     * جلب جميع الأدوية من قاعدة البيانات (لإضافتها لمخزون الصيدلية).
     */
    public function searchAll(Request $request)
    {
            $hospitalId = $request->user()->hospital_id;

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
            ->where('status', '!=', Drug::STATUS_ARCHIVED)
            ->where(function($query) use ($hospitalId) {
                $query->where('status', Drug::STATUS_AVAILABLE)
                    ->orWhere(function($sub) use ($hospitalId) {
                        $sub->where('status', Drug::STATUS_PHASING_OUT);
                        if ($hospitalId) {
                            $sub->whereHas('inventories', function($inv) use ($hospitalId) {
                                $inv->whereNotNull('warehouse_id')
                                    ->whereHas('warehouse', function($w) use ($hospitalId) {
                                        $w->where('hospital_id', $hospitalId);
                                    })
                                    ->where('current_quantity', '>', 0);
                            });
                        } else {
                            $sub->whereHas('inventories', function($inv) {
                                $inv->whereNotNull('warehouse_id')
                                    ->where('current_quantity', '>', 0);
                            });
                        }
                    });
            })
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

        // التحقق من الأرشفة التلقائية بعد التحديث
        try {
            $drug = $inventory->drug; // جلب الدواء المرتبط
            if ($drug && $drug->status === Drug::STATUS_PHASING_OUT) {
                $drug->checkAndArchiveIfNoStock();
            }
        } catch (\Exception $e) {
            \Log::error('Auto-archiving check failed in DrugPharmacistController@update', ['error' => $e->getMessage()]);
        }

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
            ->whereHas('drug', function($q) {
                $q->where('status', '!=', Drug::STATUS_ARCHIVED);
            });
        
        // تطبيق فلاتر البحث على الأدوية
        if ($query || $catName) {
            $inventoriesQuery->whereHas('drug', function($q) use ($query, $catName) {
                $q->where('status', '!=', Drug::STATUS_ARCHIVED); // تأكيد إضافي
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

    /**
     * GET /api/pharmacist/drugs/expired
     * جلب قائمة الأدوية المُصفرة من audit_log (للصفحة المخصصة)
     */
    public function expired(Request $request)
    {
        $user = $request->user();
        $hospitalId = $user->hospital_id;

        if (!$hospitalId) {
            return $this->sendError('المستخدم غير مرتبط بمستشفى.', [], 400);
        }

        // جلب جميع الأدوية المُصفرة من audit_log
        $expiredDrugs = $this->getExpiredDrugsFromAuditLog($hospitalId);

        return $this->sendSuccess($expiredDrugs->values(), 'تم جلب قائمة الأدوية المُصفرة بنجاح.');
    }
    
}
