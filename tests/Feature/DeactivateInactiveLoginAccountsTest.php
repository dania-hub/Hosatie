<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\AuditLog;
use Carbon\Carbon;

class DeactivateInactiveLoginAccountsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_deactivates_patients_inactive_for_3_years_and_logs_action()
    {
        // 1. Arrange
        $cutoff = Carbon::now()->subYears(3);

        // Case A: Target User (Has last_login_at < 3 years)
        $targetUser = User::factory()->create([
            'type' => 'patient',
            'status' => 'active',
            'last_login_at' => $cutoff->copy()->subDay(),
            'updated_at' => $cutoff->copy()->subDay(),
        ]);

        // Case B: Target User (Null last_login_at, updated_at < 3 years)
        $targetUserNullLogin = User::factory()->create([
            'type' => 'patient',
            'status' => 'active',
            'last_login_at' => null,
            'updated_at' => $cutoff->copy()->subDay(),
        ]);

        // Case C: Active User (Recent login)
        $activeUser = User::factory()->create([
            'type' => 'patient',
            'status' => 'active',
            'last_login_at' => Carbon::now()->subDay(),
        ]);

        // Case D: Active User (Null login, recent updated_at) - e.g. just registered
        $activeUserNullLogin = User::factory()->create([
            'type' => 'patient',
            'status' => 'active',
            'last_login_at' => null,
            'updated_at' => Carbon::now()->subDay(),
        ]);

        // Case E: Already Inactive
        $inactiveUser = User::factory()->create([
            'type' => 'patient',
            'status' => 'inactive',
            'last_login_at' => $cutoff->copy()->subDay(),
        ]);

        // 2. Act
        $this->artisan('users:deactivate-inactive-logins')
             ->assertExitCode(0);

        // 3. Assert
        $this->assertEquals('inactive', $targetUser->fresh()->status);
        $this->assertEquals('inactive', $targetUserNullLogin->fresh()->status);
        
        $this->assertEquals('active', $activeUser->fresh()->status);
        $this->assertEquals('active', $activeUserNullLogin->fresh()->status);
        $this->assertEquals('inactive', $inactiveUser->fresh()->status);

        // Check Audit Logs
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auto_deactivate_inactive_login',
            'record_id' => $targetUser->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auto_deactivate_inactive_login',
            'record_id' => $targetUserNullLogin->id,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'record_id' => $activeUser->id,
            'action' => 'auto_deactivate_inactive_login',
        ]);
        
        // Ensure no duplicate log for already inactive
        $this->assertDatabaseMissing('audit_logs', [
            'record_id' => $inactiveUser->id,
            'action' => 'auto_deactivate_inactive_login',
        ]);
    }
}
