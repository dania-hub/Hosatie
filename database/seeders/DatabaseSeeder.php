<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
    SupplierSeeder::class,
        HospitalSeeder::class,
        WarehouseSeeder::class,
        DepartmentSeeder::class,
        PharmacySeeder::class,
        DrugSeeder::class,
        UserSeeder::class,
        InventorySeeder::class,
        PrescriptionSeeder::class,
        InternalSupplyRequestSeeder::class,
        InternalSupplyRequestItemSeeder::class,
        ExternalSupplyRequestSeeder::class,
        ExternalSupplyRequestItemSeeder::class,
        DispensingSeeder::class,
        ComplaintSeeder::class,
        PatientTransferRequestSeeder::class,
        AuditLogSeeder::class,
        NotificationSeeder::class,
        ]);
    }
}
