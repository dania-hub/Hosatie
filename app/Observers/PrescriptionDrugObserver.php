<?php

namespace App\Observers;

use App\Models\PrescriptionDrug;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // For debugging

class PrescriptionDrugObserver
{
    /**
     * Handle the PrescriptionDrug "created" event.
     */
    public function created(PrescriptionDrug $prescriptionDrug)
    {
        // Force log for debugging (check laravel.log if still failing)
        Log::info('Observer Created Triggered', ['id' => $prescriptionDrug->id]);
        
        $this->logAction('إضافة دواء', $prescriptionDrug);
    }

    /**
     * Handle the PrescriptionDrug "updated" event.
     */
    public function updated(PrescriptionDrug $prescriptionDrug)
    {
        Log::info('Observer Updated Triggered', ['id' => $prescriptionDrug->id]);

        // Log regardless of what changed to ensure it works
        $this->logAction('تعديل دواء', $prescriptionDrug, $prescriptionDrug->getOriginal());
    }

    /**
     * Handle the PrescriptionDrug "deleted" event.
     */
    public function deleted(PrescriptionDrug $prescriptionDrug)
    {
        Log::info('Observer Deleted Triggered', ['id' => $prescriptionDrug->id]);
        
        $this->logAction('حذف دواء', $prescriptionDrug);
    }

    /**
     * Helper to save the log
     */
    protected function logAction($action, $record, $oldValues = null)
    {
        // 1. Try getting user from Auth Facade
        $userId = Auth::id();

        // 2. Fallback: Try getting user from Request (API specific)
        if (!$userId) {
            $userId = request()->user() ? request()->user()->id : null;
        }

        // 3. If User ID found, Create Log
        if ($userId) {
            AuditLog::create([
                'user_id'    => $userId,
                'action'     => $action,
                'table_name' => 'prescription_drug',
                'record_id'  => $record->id,
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => json_encode($record->toArray()),
                'ip_address' => request()->ip(),
            ]);
        } else {
            Log::warning("AuditLog Skipped: No User ID found for action {$action}");
        }
    }
}
