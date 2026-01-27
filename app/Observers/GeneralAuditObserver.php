<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class GeneralAuditObserver
{
    public function created(Model $model)
    {
        $this->logAction($model, 'create');
    }

    public function updated(Model $model)
    {
        $this->logAction($model, 'update');
    }

    public function deleted(Model $model)
    {
        $this->logAction($model, 'delete');
    }

    protected function logAction(Model $model, $action)
    {
        if (Auth::check()) {
            $oldValues = null;
            $newValues = null;

            if ($action === 'create') {
                $newValues = json_encode($model->getAttributes());
            } elseif ($action === 'update') {
                // Only log the differences
                $changes = $model->getChanges();
                if (empty($changes)) {
                    return; // Don't log if nothing changed
                }
                
                $old = [];
                $new = [];
                
                foreach ($changes as $key => $newValue) {
                    // Skip timestamps
                    if (in_array($key, ['updated_at', 'created_at'])) continue;
                    
                    $old[$key] = $model->getOriginal($key);
                    $new[$key] = $newValue;
                }
                
                if (empty($new)) return; // Only had timestamps

                $oldValues = json_encode($old);
                $newValues = json_encode($new);
            } elseif ($action === 'delete') {
                $oldValues = json_encode($model->getOriginal());
            }

            AuditLog::create([
                'user_id'     => Auth::id(),
                'hospital_id' => Auth::user()->hospital_id ?? null,
                'action'      => $action,
                'table_name'  => $model->getTable(),
                'record_id'   => $model->getKey(),
                'old_values'  => $oldValues,
                'new_values'  => $newValues,
                'ip_address'  => request()->ip(),
            ]);
        }
    }
}
