<?php
namespace App\Observers;

use App\Models\Drug;
use App\Models\Inventory;

class DrugObserver
{
    public function updated(Inventory $inventory)
    {
        $drug = Drug::find($inventory->drug_id);
        if (!$drug) return;
        
        // متوفر إذا كان موجود في أي صيدلية وكميتها > 0
        $isAvailable = Inventory::where('drug_id', $drug->id)
            ->whereNotNull('pharmacy_id')
            ->where('current_quantity', '>', 0)
            ->exists();
            
        $drug->update(['status' => $isAvailable ? 'متوفر' : 'غير متوفر']);
    }
}
