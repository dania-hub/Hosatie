<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Dispensing;
use App\Models\AuditLog;
use App\Models\Prescription;
use App\Models\Drug;
use App\Models\Pharmacy;
use App\Models\Hospital;
use App\Models\PrescriptionDrug;
use Carbon\Carbon;

class DeactivateInactivePatientsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_deactivates_patients_with_no_dispensing_in_last_12_months_and_logs_action()
    {
        // 1. Arrange global dependencies
        $hospital = Hospital::factory()->create();
        $pharmacy = Pharmacy::factory()->create(['hospital_id' => $hospital->id]);
        $pharmacist = User::factory()->create([
            'hospital_id' => $hospital->id,
            'type' => 'pharmacist' 
        ]); 
        $drug = Drug::factory()->create();

        // Helper to create valid dispensing
        $createDispensing = function($user, $date) use ($pharmacist, $pharmacy, $drug, $hospital) {
             $prescription = Prescription::factory()->create([
                 'patient_id' => $user->id,
                 'doctor_id' => $pharmacist->id,
                 'hospital_id' => $hospital->id, 
             ]);

             // Link drug to prescription
             PrescriptionDrug::factory()->create([
                 'prescription_id' => $prescription->id,
                 'drug_id' => $drug->id,
             ]);
             
             return Dispensing::factory()->create([
                'patient_id' => $user->id,
                'dispense_month' => $date,
                'quantity_dispensed' => 1,
                'prescription_id' => $prescription->id,
                'pharmacist_id' => $pharmacist->id,
                'pharmacy_id' => $pharmacy->id,
                'drug_id' => $drug->id
            ]);
        };

        $cutoff = Carbon::now()->subMonths(12);

        // Case A: Target User - Active, Old Account, No recent dispensing
        $targetUser = User::factory()->create([
            'type' => 'patient',
            'status' => 'active',
            'created_at' => $cutoff->copy()->subMonth(), 
            'hospital_id' => $hospital->id
        ]);
        // Add old dispensing > 12 months ago
        $createDispensing($targetUser, $cutoff->copy()->subDay());

        // Case B: Active User - Has recent dispensing
        $activeUser = User::factory()->create([
            'type' => 'patient',
            'status' => 'active',
            'created_at' => $cutoff->copy()->subMonth(),
            'hospital_id' => $hospital->id
        ]);
        $createDispensing($activeUser, Carbon::now()->subMonth());

        // Case C: New User - No dispensing yet, but account < 12 months old
        $newUser = User::factory()->create([
            'type' => 'patient',
            'status' => 'active',
            'created_at' => Carbon::now()->subMonth(),
            'hospital_id' => $hospital->id
        ]);

        // Case D: Already Inactive User
        $inactiveUser = User::factory()->create([
            'type' => 'patient',
            'status' => 'inactive',
            'created_at' => $cutoff->copy()->subMonth(),
            'hospital_id' => $hospital->id
        ]);
        // No dispensing

        // 2. Act
        $this->artisan('users:deactivate-inactive-patients')
             ->assertExitCode(0);

        // 3. Assert
        
        // Target User should be inactive
        $this->assertEquals('inactive', $targetUser->fresh()->status);

        // Active User should remain active
        $this->assertEquals('active', $activeUser->fresh()->status);

        // New User should remain active
        $this->assertEquals('active', $newUser->fresh()->status);

        // Inactive User should remain inactive
        $this->assertEquals('inactive', $inactiveUser->fresh()->status);

        // Check Audit Log for Target User
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auto_deactivate_inactive_patient',
            'table_name' => 'users',
            'record_id' => $targetUser->id,
            'hospital_id' => $hospital->id,
        ]);
        
        // Ensure no audit log for others
        $this->assertDatabaseMissing('audit_logs', [
            'record_id' => $activeUser->id,
            'action' => 'auto_deactivate_inactive_patient',
        ]);
    }
}
