<?php

namespace App\Observers;

use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user)
    {
        // Only log if a logged-in user creates a 'patient'
        if (Auth::check() && $user->type === 'patient') {
            AuditLog::create([
                'user_id'    => Auth::id(), // The Data Entry Clerk
                'action'     => 'إضافة',    // The Action Name (Matches Image)
                'table_name' => 'users',
                'record_id'  => $user->id,  // File Number
                'new_values' => json_encode($user->only(['full_name', 'national_id'])),
                'ip_address' => request()->ip(),
            ]);
        }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user)
    {
        // Only log if a logged-in user updates a 'patient'
        if (Auth::check() && $user->type === 'patient') {
            AuditLog::create([
                'user_id'    => Auth::id(),
                'action'     => 'تعديل',    // The Action Name (Matches Image)
                'table_name' => 'users',
                'record_id'  => $user->id,
                'old_values' => json_encode($user->getOriginal()),
                'new_values' => json_encode($user->getChanges()),
                'ip_address' => request()->ip(),
            ]);
        }
    }
}
