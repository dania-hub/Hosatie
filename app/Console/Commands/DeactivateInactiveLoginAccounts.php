<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DeactivateInactiveLoginAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:deactivate-inactive-logins';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate patient accounts that have not logged in for 3 years';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 3 years ago
        $cutoffDate = Carbon::now()->subYears(3);

        $this->info("Checking for patients inactive (login) since {$cutoffDate->format('Y-m-d')}...");

        // Logic:
        // Type = patient
        // Status = active
        // last_login_at < cutoff OR (last_login_at IS NULL AND updated_at < cutoff)
        
        $usersToDeactivate = User::where('type', 'patient')
            ->where('status', 'active')
            ->where(function ($query) use ($cutoffDate) {
                $query->where('last_login_at', '<', $cutoffDate)
                      ->orWhere(function ($q) use ($cutoffDate) {
                          $q->whereNull('last_login_at')
                            ->where('updated_at', '<', $cutoffDate);
                      });
            })
            ->get();

        $count = $usersToDeactivate->count();
        $this->info("Found {$count} patients to deactivate.");

        foreach ($usersToDeactivate as $user) {
            DB::transaction(function () use ($user) {
                $oldStatus = $user->status;
                
                $user->status = 'inactive';
                $user->save();

                AuditLog::create([
                    'user_id' => null, // System
                    'hospital_id' => $user->hospital_id,
                    'action' => 'auto_deactivate_inactive_login',
                    'table_name' => 'users',
                    'record_id' => $user->id,
                    'old_values' => json_encode(['status' => $oldStatus]),
                    'new_values' => json_encode(['status' => 'inactive']),
                    'ip_address' => '127.0.0.1',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
            
            $this->info("Deactivated user ID: {$user->id}");
        }
    }
}
