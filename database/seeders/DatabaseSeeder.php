<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Department;
use App\Models\Dispensing;
use App\Models\Drug;
use App\Models\ExternalSupplyRequest;
use App\Models\ExternalSupplyRequestItem;
use App\Models\Hospital;
use App\Models\Inventory;
use App\Models\InternalSupplyRequest;
use App\Models\InternalSupplyRequestItem;
use App\Models\Notification;
use App\Models\OtpVerification;
use App\Models\PatientTransferRequest;
use App\Models\Pharmacy;
use App\Models\Prescription;
use App\Models\PrescriptionDrug;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $drugs = Drug::factory()->count(120)->create();
        $suppliers = Supplier::factory()->count(3)->create();

        $hospitals = collect();
        $warehouses = collect();
        $pharmacies = collect();
        $departments = collect();

        $hospitalPharmacies = [];
        $hospitalDepartments = [];
        $hospitalWarehouses = [];

        foreach ($suppliers as $supplier) {
            $createdHospitals = Hospital::factory()->count(2)->for($supplier)->create();
            foreach ($createdHospitals as $hospital) {
                $hospitals->push($hospital);
                $warehouse = Warehouse::factory()->for($hospital)->create();
                $warehouses->push($warehouse);
                $hospitalWarehouses[$hospital->id] = $warehouse;
                $pharmacyBatch = Pharmacy::factory()->count(2)->for($hospital)->create();
                $hospitalPharmacies[$hospital->id] = $pharmacyBatch;
                $pharmacies = $pharmacies->merge($pharmacyBatch);
                $departmentBatch = Department::factory()->count(2)->for($hospital)->create();
                $hospitalDepartments[$hospital->id] = $departmentBatch;
                $departments = $departments->merge($departmentBatch);
            }
        }

        $patients = collect();
        $doctors = collect();
        $pharmacists = collect();
        $departmentHeads = collect();
        $warehouseManagers = collect();

        foreach ($hospitals as $hospital) {
            $patients = $patients->merge(
                User::factory()->count(8)->patient()->for($hospital)->create()
            );

            $doctors = $doctors->merge(
                User::factory()->count(3)->doctor()->for($hospital)->create()
            );

            $pharmacyIds = $hospitalPharmacies[$hospital->id]->pluck('id');
            $pharmacists = $pharmacists->merge(
                User::factory()->count(3)->pharmacist()->for($hospital)
                    ->state(function () use ($pharmacyIds) {
                        return ['pharmacy_id' => $pharmacyIds->random()];
                    })
                    ->create()
            );

            $departmentHeads = $departmentHeads->merge(
                User::factory()->count(2)->departmentHead()->for($hospital)->create()
            );

            $warehouseManagers = $warehouseManagers->merge(
                User::factory()->warehouseManager()->for($hospital)
                    ->state(['warehouse_id' => $hospitalWarehouses[$hospital->id]->id])
                    ->create()
            );
        }

        $departmentHeadsByHospital = $departmentHeads->groupBy('hospital_id');
        foreach ($hospitalDepartments as $hospitalId => $departmentBatch) {
            $heads = $departmentHeadsByHospital[$hospitalId]->values();
            foreach ($departmentBatch->values() as $index => $department) {
                $head = $heads->get($index);
                if ($head) {
                    $department->update(['head_user_id' => $head->id]);
                }
            }
        }

        $inventoryDrugIds = $drugs->pluck('id');

        foreach ($warehouses as $warehouse) {
            $supplierId = $warehouse->hospital->supplier_id;
            $stock = $inventoryDrugIds->shuffle()->take(40);
            foreach ($stock as $drugId) {
                Inventory::factory()
                    ->for($warehouse)
                    ->state([
                        'drug_id' => $drugId,
                        'supplier_id' => $supplierId,
                        'pharmacy_id' => null,
                    ])
                    ->create();
            }
        }

        foreach ($pharmacies as $pharmacy) {
            $supplierId = $pharmacy->hospital->supplier_id;
            $stock = $inventoryDrugIds->shuffle()->take(20);
            foreach ($stock as $drugId) {
                Inventory::factory()->state([
                    'drug_id' => $drugId,
                    'pharmacy_id' => $pharmacy->id,
                    'supplier_id' => $supplierId,
                    'warehouse_id' => null,
                ])->create();
            }
        }

        $pharmacistsByHospital = $pharmacists->groupBy('hospital_id');
        $pharmaciesByHospital = $pharmacies->groupBy('hospital_id');
        $patientsByHospital = $patients->groupBy('hospital_id');
        $doctorsByHospital = $doctors->groupBy('hospital_id');
        $warehouseManagersByHospital = $warehouseManagers->groupBy('hospital_id');

        $internalStatuses = ['pending', 'approved', 'rejected', 'fulfilled', 'cancelled'];
        $externalStatuses = ['pending', 'approved', 'fulfilled', 'rejected'];

        foreach ($pharmacies as $pharmacy) {
            $hospitalId = $pharmacy->hospital_id;
            $requesterPool = $pharmacistsByHospital[$hospitalId];
            $approverPool = ($departmentHeadsByHospital[$hospitalId] ?? collect())->concat(
                $warehouseManagersByHospital[$hospitalId] ?? collect()
            );
            for ($i = 0; $i < 10; $i++) {
                $status = fake()->randomElement($internalStatuses);
                $handlerId = in_array($status, ['approved', 'fulfilled', 'rejected']) && $approverPool->isNotEmpty()
                    ? $approverPool->random()->id
                    : null;
                $request = InternalSupplyRequest::factory()
                    ->for($pharmacy)
                    ->state([
                        'requested_by' => $requesterPool->random()->id,
                        'status' => $status,
                        'handeled_by' => $handlerId,
                        'handeled_at' => $handlerId ? fake()->dateTimeBetween('-3 months', 'now') : null,
                    ])
                    ->create();
                $itemCount = fake()->numberBetween(2, 6);
                $items = $drugs->random($itemCount);
                foreach ($items as $drug) {
                    $requestedQty = fake()->numberBetween(10, 200);
                    $approvedQty = in_array($status, ['approved', 'fulfilled'])
                        ? fake()->numberBetween(max(1, $requestedQty - 15), $requestedQty + 20)
                        : null;
                    $fulfilledQty = $status === 'fulfilled' ? ($approvedQty ?? $requestedQty) : null;
                    InternalSupplyRequestItem::factory()
                        ->for($request, 'request')
                        ->for($drug)
                        ->create([
                            'requested_qty' => $requestedQty,
                            'approved_qty' => $approvedQty,
                            'fulfilled_qty' => $fulfilledQty,
                        ]);
                }
            }
        }

        foreach ($hospitals as $hospital) {
            $approverPool = $warehouseManagersByHospital[$hospital->id] ?? collect();
            $requesterPool = $departmentHeadsByHospital[$hospital->id];
            for ($i = 0; $i < 6; $i++) {
                $status = fake()->randomElement($externalStatuses);
                $handlerId = in_array($status, ['approved', 'fulfilled', 'rejected']) && $approverPool->isNotEmpty()
                    ? $approverPool->random()->id
                    : null;
                $request = ExternalSupplyRequest::factory()
                    ->for($hospital)
                    ->state([
                        'supplier_id' => $hospital->supplier_id,
                        'requested_by' => $requesterPool->random()->id,
                        'status' => $status,
                        'handeled_by' => $handlerId,
                        'handeled_at' => $handlerId ? fake()->dateTimeBetween('-3 months', 'now') : null,
                    ])
                    ->create();
                $itemCount = fake()->numberBetween(3, 8);
                $items = $drugs->random($itemCount);
                foreach ($items as $drug) {
                    $requestedQty = fake()->numberBetween(20, 300);
                    $approvedQty = in_array($status, ['approved', 'fulfilled'])
                        ? fake()->numberBetween(max(1, $requestedQty - 20), $requestedQty + 30)
                        : null;
                    $fulfilledQty = $status === 'fulfilled' ? ($approvedQty ?? $requestedQty) : null;
                    ExternalSupplyRequestItem::factory()
                        ->for($request, 'request')
                        ->for($drug)
                        ->create([
                            'requested_qty' => $requestedQty,
                            'approved_qty' => $approvedQty,
                            'fulfilled_qty' => $fulfilledQty,
                        ]);
                }
            }
        }

        foreach ($patients as $patient) {
            $hospital = $hospitals->firstWhere('id', $patient->hospital_id);
            if (!$hospital) {
                continue;
            }
            $doctorPool = $doctorsByHospital[$hospital->id] ?? collect();
            $doctor = $doctorPool->isNotEmpty()
                ? $doctorPool->random()
                : $doctors->random();
            $status = fake()->randomElement(['active', 'cancelled', 'suspended']);
            $cancelledAt = $status === 'cancelled' ? fake()->dateTimeBetween('-60 days', 'now') : null;
            $prescription = Prescription::factory()
                ->for($patient, 'patient')
                ->for($doctor, 'doctor')
                ->for($hospital)
                ->state([
                    'status' => $status,
                    'start_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
                    'end_date' => fake()->boolean(60)
                        ? fake()->dateTimeBetween('now', '+120 days')->format('Y-m-d')
                        : null,
                    'cancelled_at' => $cancelledAt,
                ])
                ->create();
            $drugCount = fake()->numberBetween(1, 4);
            $prescriptionDrugs = $drugs->random($drugCount);
            foreach ($prescriptionDrugs as $drug) {
                PrescriptionDrug::factory()
                    ->for($prescription)
                    ->for($drug)
                    ->create();
                $dispenseRounds = fake()->numberBetween(0, 4);
                $usedMonths = collect();
                for ($j = 0; $j < $dispenseRounds; $j++) {
                    $month = Carbon::now()->subMonths(fake()->numberBetween(0, 12))->startOfMonth();
                    while ($usedMonths->contains($month->format('Y-m-d'))) {
                        $month = $month->subMonth();
                    }
                    $usedMonths->push($month->format('Y-m-d'));
                    $shouldRevert = fake()->boolean(12);
                    $revertedBy = $shouldRevert
                        ? $pharmacistsByHospital[$hospital->id]->random()->id
                        : null;
                    $revertedAt = $shouldRevert ? Carbon::parse($month)->addDays(fake()->numberBetween(1, 20)) : null;
                    Dispensing::factory()
                        ->state([
                            'prescription_id' => $prescription->id,
                            'patient_id' => $patient->id,
                            'drug_id' => $drug->id,
                            'pharmacist_id' => $pharmacistsByHospital[$hospital->id]->random()->id,
                            'pharmacy_id' => $pharmaciesByHospital[$hospital->id]->random()->id,
                            'dispense_month' => $month->format('Y-m-d'),
                            'quantity_dispensed' => fake()->numberBetween(1, 80),
                            'reverted' => $shouldRevert,
                            'reverted_at' => $revertedAt,
                            'reverted_by' => $revertedBy,
                        ])
                        ->create();
                }
            }
        }

        for ($i = 0; $i < 80; $i++) {
            $patient = $patients->random();
            $fromHospital = $hospitals->firstWhere('id', $patient->hospital_id);
            $toHospital = $hospitals->where('id', '!=', $fromHospital->id)->random();
            $status = fake()->randomElement(['pending', 'approved', 'rejected']);
            $requesterPool = $departmentHeadsByHospital->get($fromHospital->id, collect());
            if ($requesterPool->isEmpty()) {
                $requesterPool = $departmentHeads;
            }
            $requester = $requesterPool->random()->id;
            $transfer = PatientTransferRequest::factory()
                ->state([
                    'patient_id' => $patient->id,
                    'from_hospital_id' => $fromHospital->id,
                    'to_hospital_id' => $toHospital->id,
                    'requested_by' => $requester,
                    'status' => $status,
                    'reason' => fake()->sentence(),
                ])
                ->create();
            if ($status === 'approved') {
                $approverPool = $warehouseManagersByHospital->get($fromHospital->id, collect());
                $transfer->update([
                    'handeled_by' => $approverPool->isNotEmpty() ? $approverPool->random()->id : null,
                    'handeled_at' => fake()->dateTimeBetween('-30 days', 'now'),
                ]);
            } elseif ($status === 'rejected') {
                $rejectionPool = $departmentHeadsByHospital->get($fromHospital->id, collect());
                $transfer->update([
                    'handeled_by' => $rejectionPool->isNotEmpty() ? $rejectionPool->random()->id : null,
                    'handeled_at' => fake()->dateTimeBetween('-30 days', 'now'),
                    'rejection_reason' => fake()->sentence(),
                ]);
            }
        }

        Complaint::factory()
            ->count(120)
            ->state(function () use ($patients, $doctors, $departmentHeads) {
                $patient = $patients->random();
                $status = fake()->randomElement(['قيد المراجعة', 'تمت المراجعة']);
                $state = [
                    'patient_id' => $patient->id,
                    'status' => $status,
                ];
                if ($status === 'تمت المراجعة') {
                    $replier = $doctors->concat($departmentHeads)->random();
                    $state['replied_by'] = $replier->id;
                    $state['reply_message'] = fake()->sentence();
                    $state['replied_at'] = fake()->dateTimeBetween('-30 days', 'now');
                }
                return $state;
            })
            ->create();

        $allUsers = $patients
            ->concat($doctors)
            ->concat($pharmacists)
            ->concat($departmentHeads)
            ->concat($warehouseManagers);

        $userIds = array_values(array_filter($allUsers->pluck('id')->all(), fn ($id) => $id !== null));

        if (empty($userIds)) {
            return;
        }

        Notification::factory()
            ->count(800)
            ->state(fn () => ['user_id' => fake()->randomElement($userIds) ?? $userIds[array_rand($userIds)]])
            ->create();

        User::whereIn('id', $userIds)
            ->cursor()
            ->each(function (User $user) use ($hospitals) {
                $hospitalId = $user->hospital_id ?? $hospitals->random()->id;
                AuditLog::factory()
                    ->count(5)
                    ->state([
                        'user_id' => $user->id,
                        'hospital_id' => $hospitalId,
                    ])
                    ->create();
            });

        OtpVerification::factory()->count(150)->create();
    }
}
