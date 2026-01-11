<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Drug;
use App\Models\Inventory;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Pharmacy;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

class DrugPhasingOutTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function drug_archives_automatically_when_total_stock_is_zero()
    {
        // 1. Create a Phasing Out Drug
        $drug = Drug::create([
            'name' => 'Phasing Out Drug',
            'generic_name' => 'Generic',
            'strength' => '500mg',
            'form' => 'Tablet',
            'category' => 'Antibiotic',
            'unit' => 'Tablet',
            'max_monthly_dose' => 30,
            'status' => Drug::STATUS_PHASING_OUT,
            'manufacturer' => 'Test',
            'country' => 'Test',
            'utilization_type' => 'Oral',
            'warnings' => 'None',
            'indications' => 'None',
            'expiry_date' => now()->addYear(),
        ]);

        // 2. Create Inventory with some stock
        $warehouse = Warehouse::create(['name' => 'Central Store', 'hospital_id' => 1]);
        $inventory = Inventory::create([
            'drug_id' => $drug->id,
            'warehouse_id' => $warehouse->id,
            'current_quantity' => 10,
            'minimum_level' => 5,
        ]);

        $this->assertEquals(Drug::STATUS_PHASING_OUT, $drug->status);

        // 3. Set stock to zero and trigger check
        $inventory->update(['current_quantity' => 0]);
        $drug->checkAndArchiveIfNoStock();

        // 4. Verify status changed to Archived
        $drug->refresh();
        $this->assertEquals(Drug::STATUS_ARCHIVED, $drug->status);
    }

    /** @test */
    public function drug_does_not_archive_if_total_stock_is_greater_than_zero()
    {
        $drug = Drug::create([
            'name' => 'Phasing Out Drug',
            'status' => Drug::STATUS_PHASING_OUT,
            // ... other required fields
            'generic_name' => 'Generic', 'strength' => '500mg', 'form' => 'Tablet',
            'category' => 'Antibiotic', 'unit' => 'Tablet', 'max_monthly_dose' => 30,
            'manufacturer' => 'Test', 'country' => 'Test', 'utilization_type' => 'Oral',
            'warnings' => 'None', 'indications' => 'None', 'expiry_date' => now()->addYear(),
        ]);

        $warehouse = Warehouse::create(['name' => 'Central Store', 'hospital_id' => 1]);
        Inventory::create([
            'drug_id' => $drug->id,
            'warehouse_id' => $warehouse->id,
            'current_quantity' => 10,
        ]);

        $drug->checkAndArchiveIfNoStock();

        $drug->refresh();
        $this->assertEquals(Drug::STATUS_PHASING_OUT, $drug->status);
    }
}
