<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Drug;

class DrugDoctorController extends BaseApiController
{
    /**
     * GET /api/doctor/drugs
     * List drugs with optional filtering by category or search term.
     */
    public function index(Request $request)
    {
        // 1. Get Query Parameters
        $category = $request->query('category');
        $search = $request->query('search');
        $hospitalId = $request->user()->hospital_id;

        // 2. Build Query
        $query = Drug::select('id', 'name', 'generic_name', 'strength', 'form', 'category', 'unit', 'max_monthly_dose', 'status')
            ->where('status', '!=', Drug::STATUS_ARCHIVED)
            ->where(function($q) use ($hospitalId) {
                $q->where('status', Drug::STATUS_AVAILABLE)
                  ->orWhere(function($sub) use ($hospitalId) {
                      $sub->where('status', Drug::STATUS_PHASING_OUT);
                      if ($hospitalId) {
                          $sub->whereHas('inventories', function($inv) use ($hospitalId) {
                              $inv->where('current_quantity', '>', 0)
                                  ->where(function($q) use ($hospitalId) {
                                      // Check Warehouse within hospital
                                      $q->where(function($wh) use ($hospitalId) {
                                          $wh->whereNotNull('warehouse_id')
                                             ->whereHas('warehouse', function($w) use ($hospitalId) {
                                                 $w->where('hospital_id', $hospitalId);
                                             });
                                      })
                                      // OR Check Pharmacy within hospital
                                      ->orWhere(function($ph) use ($hospitalId) {
                                          $ph->whereNotNull('pharmacy_id')
                                             ->whereHas('pharmacy', function($p) use ($hospitalId) {
                                                 $p->where('hospital_id', $hospitalId);
                                             });
                                      });
                                  });
                          });
                      } else {
                          // Fallback: check ANY warehouse or pharmacy with stock
                          $sub->whereHas('inventories', function($inv) {
                              $inv->where('current_quantity', '>', 0)
                                  ->where(function($q) {
                                      $q->whereNotNull('warehouse_id')
                                        ->orWhereNotNull('pharmacy_id');
                                  });
                          });
                      }
                  });
            });

        // 3. Filter by Category (if provided)
        if ($category) {
            $query->where('category', $category);
        }

        // 4. Search by Name (if provided)
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('generic_name', 'like', "%{$search}%");
            });
        }

        // 5. Execute & Return (Limit to 50 to avoid huge payloads)
        $drugs = $query->orderBy('name')
                       ->limit(50)
                       ->get()
                       ->map(function($drug) {
                           $drug->isPhasingOut = $drug->status === Drug::STATUS_PHASING_OUT;
                           $drug->phasingOutWarning = $drug->isPhasingOut 
                               ? "هذا الدواء قيد الإيقاف التدريجي. للمرضى الجدد: يرجى اختيار بديل. للمرضى الحاليين: يرجى التخطيط للانتقال لبديل."
                               : null;
                           return $drug;
                       });

        return $this->sendSuccess($drugs, 'Drugs retrieved successfully.');
    }

    /**
     * GET /api/doctor/drug-categories
     * List all unique drug categories.
     */
    public function categories()
    {
        $categories = Drug::select('category')
            ->distinct()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->orderBy('category')
            ->pluck('category'); // Returns a simple array of strings

        return $this->sendSuccess($categories, 'Categories retrieved successfully.');
    }

    /**
     * GET /api/doctor/drugs/{id}
     * Get single drug details.
     */
    public function show($id)
    {
        $drug = Drug::find($id);

        if (!$drug) {
            return $this->sendError('الدواء غير موجود.', [], 404);
        }

        return $this->sendSuccess([
            'id' => $drug->id,
            'name' => $drug->name,
            'generic_name' => $drug->generic_name,
            'strength' => $drug->strength,
            'form' => $drug->form,
            'category' => $drug->category,
            'unit' => $drug->unit,
            'max_monthly_dose' => $drug->max_monthly_dose,
            'isPhasingOut' => $drug->status === Drug::STATUS_PHASING_OUT,
            'phasingOutWarning' => $drug->status === Drug::STATUS_PHASING_OUT 
                ? "هذا الدواء قيد الإيقاف التدريجي. للمرضى الجدد: يرجى اختيار بديل. للمرضى الحاليين: يرجى التخطيط للانتقال لبديل."
                : null,
        ], 'تم جلب بيانات الدواء بنجاح.');
    }
}
