<?php

namespace App\Observers;

use App\Models\AuditLog;

class AuditLogObserver
{
    public function creating(AuditLog $auditLog)
    {
        // ملء hospital_id تلقائياً من المستخدم
        if ($auditLog->user_id && !$auditLog->hospital_id) {
            $user = \App\Models\User::find($auditLog->user_id);
            
            if ($user && $user->hospital_id) {
                $auditLog->hospital_id = $user->hospital_id;
            }
        }
    }
}
