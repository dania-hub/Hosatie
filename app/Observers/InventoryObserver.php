<?php
namespace App\Observers;

use App\Models\Inventory;
use App\Models\Drug;

class InventoryObserver
{
    public function creating(Inventory $inventory)
    {
        // Polymorphic: supplier_id = null لو warehouse أو pharmacy
        if ($inventory->warehouse_id || $inventory->pharmacy_id) {
            $inventory->supplier_id = null;
        }
    }
    
    public function updating(Inventory $inventory)
    {
        // نفس المنطق عند التحديث
        if ($inventory->warehouse_id || $inventory->pharmacy_id) {
            $inventory->supplier_id = null;
        }
    }

    public function created(Inventory $inventory)
    {
        $this->updateDrugStatus($inventory);
    }

    public function updated(Inventory $inventory)
    {
        $this->updateDrugStatus($inventory);
    }

    public function deleted(Inventory $inventory)
    {
        $this->updateDrugStatus($inventory);
    }

    protected function updateDrugStatus(Inventory $inventory)
    {
        $drug = Drug::find($inventory->drug_id);
        if (!$drug) return;

        // CRITICAL: Do not override STATUS_PHASING_OUT or STATUS_ARCHIVED
        // These statuses are set by Super Admin and should not be changed automatically
        if ($drug->status === Drug::STATUS_PHASING_OUT || $drug->status === Drug::STATUS_ARCHIVED) {
            return;
        }

        // Only toggle between Available/Unavailable if drug is not in a protected state
        // متوفر إذا كان موجود في أي مكان (مستودع، صيدلية، أو مورد) وكميتها > 0
        $isAvailable = Inventory::where('drug_id', $drug->id)
            ->where('current_quantity', '>', 0)
            ->exists();

        $drug->update(['status' => $isAvailable ? Drug::STATUS_AVAILABLE : Drug::STATUS_UNAVAILABLE]);
    }
}
