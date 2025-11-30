<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\BaseApiController;
use App\Models\Drug;

class DrugController extends BaseApiController
{
    public function show($id)
    {
        $drug = Drug::find($id);

        if (!$drug) {
            return response()->json(['success' => false, 'message' => 'الدواء لا يوجد.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $drug->name,
                'generic_name' => $drug->generic_name ?? 'N/A', // العمود في الميجريشن
                'strength' => $drug->strength,
                'form' => $drug->form,         // قرص، شراب..
                'manufacturer' => $drug->manufacturer,
                'warnings' => $drug->warnings, // التحذيرات
            ]
        ]);
    }
}
