<?php

namespace App\Http\Controllers\Mobile; // تأكد من الـ Namespace الصحيح

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
                'generic_name' => $drug->generic_name ?? 'N/A',
                'strength' => $drug->strength,
                'form' => $drug->form,
                'manufacturer' => $drug->manufacturer,
                'warnings' => $drug->warnings,
                
                // ✅ الحقول الجديدة المطلوبة
                'indications' => $drug->indications ?? 'غير متوفر', // دواعي الاستعمال
                'contraindications' => $drug->contraindications ?? 'غير متوفر', // موانع الاستعمال
            ]
        ]);
    }
}
