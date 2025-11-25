<?php

namespace Database\Seeders;

use App\Models\Inventory;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run()
    {
        Inventory::create([
            'drug_id' => 1, // Panadol
            'warehouse_id' => 1, // Tripoli Warehouse
            'current_quantity' => 5000,
            'minimum_level' => 100,
            'supplier_id' => 1,
        ]);

        Inventory::create([
            'drug_id' => 2, // Amoclan
            'warehouse_id' => 1,
            'current_quantity' => 2000,
            'minimum_level' => 50,
            'supplier_id' => 1,
        ]);
    }
}
