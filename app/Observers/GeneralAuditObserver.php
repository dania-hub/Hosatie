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
            AuditLog::create([
                'user_id'     => Auth::id(),
                'hospital_id' => Auth::user()->hospital_id ?? null,
                'action'      => $action,
                'table_name'  => $model->getTable(),
                'record_id'   => $model->getKey(),
                'old_values'  => $action === 'create' ? null : json_encode($model->getOriginal()),
                'new_values'  => $action === 'delete' ? null : json_encode($model->getAttributes()),
                'ip_address'  => request()->ip(),
            ]);
        }
    }
}
