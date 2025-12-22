<?php
namespace App\Observers;

use App\Models\Inventory;

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
}
