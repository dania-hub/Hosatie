<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\Dispensing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DeactivateInactivePatients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:deactivate-inactive-patients';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate patient accounts that have not received any dispensing in the last 12 months';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cutoffDate = Carbon::now()->subMonths(12);
        
        $this->info("Checking for patients inactive since {$cutoffDate->format('Y-m-d')}...");

        // We want patients ({'type' => 'patient'}) who are Active ({'status' != 'inactive'})
        // And who have NO dispensing record where dispense_month >= $cutoffDate
        
        // Also ensure we don't deactivate brand new users who just registered less than 12 months ago 
        // and haven't had their first dispensing yet.
        // Rule: If created_at is older than 12 months AND no dispensing in last 12 months.
        
        $usersToDeactivate = User::where('type', 'patient')
            ->where('status', '!=', 'inactive')
            ->where('created_at', '<=', $cutoffDate) // Safety check: Account must be at least 12 months old
            ->whereNotExists(function ($query) use ($cutoffDate) {
                $query->select(DB::raw(1))
                      ->from('dispensings')
                      ->whereColumn('dispensings.patient_id', 'users.id')
                      ->where('dispensings.dispense_month', '>=', $cutoffDate);
            })
            ->get();

        $count = $usersToDeactivate->count();
        $this->info("Found {$count} patients to deactivate.");

        foreach ($usersToDeactivate as $user) {
            DB::transaction(function () use ($user) {
                $oldStatus = $user->status;
                
                // Update User Status
                $user->status = 'inactive';
                $user->save();

                // Log to AuditLog
                AuditLog::create([
                    'user_id' => null, // System action, or typically 0 or null
                    // If user_id is required foreign key to users, we might need a system user or use the user's own id? 
                    // Usually audit log user_id is "who performed action". Here it is system.
                    // If nullable, use null.
                    
                    'hospital_id' => $user->hospital_id, // Contextual
                    'action' => 'auto_deactivate_inactive_patient',
                    'table_name' => 'users',
                    'record_id' => $user->id,
                    'old_values' => json_encode(['status' => $oldStatus]),
                    'new_values' => json_encode(['status' => 'inactive']),
                    'ip_address' => '127.0.0.1', // System
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
            
            $this->info("Deactivated user ID: {$user->id}");
        }

        $this->info("Operation completed.");
    }
}
