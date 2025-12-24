<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogObserver
{
    public function creating(AuditLog $auditLog)
    {
        // 1) تعبئة user_id تلقائياً من المستخدم الحالي إن لم يُمرَّر
        if (! $auditLog->user_id && Auth::check()) {
            $auditLog->user_id = Auth::id();
        }

        // 2) ملء hospital_id تلقائياً من المستخدم (سواء مرّرت user_id أو جاء من Auth)
        if ($auditLog->user_id && ! $auditLog->hospital_id) {
            $user = \App\Models\User::find($auditLog->user_id);

            if ($user && $user->hospital_id) {
                $auditLog->hospital_id = $user->hospital_id;
            }
        }
    }
}
