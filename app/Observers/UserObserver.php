<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Pharmacy;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class UserObserver
{
    /**
     * Handle the User "creating" event.
     */
    public function creating(User $user): void
    {
        // لو فيه مستخدم مسجّل دخول
        if (Auth::check()) {
            $creator = Auth::user();

            // خزّن معرف من أنشأ المستخدم
            if (!$user->created_by) {
                $user->created_by = $creator->id;
            }

            // 👈 تعيين المستشفى
            if (!$user->hospital_id && $creator->hospital_id) {
                $user->hospital_id = $creator->hospital_id;
            }

            // 👈 تعيين الصيدلية الرئيسية للمرضى فقط
            if ($user->type === 'patient' && $creator->hospital_id) {
                $mainPharmacy = Pharmacy::where('hospital_id', $creator->hospital_id)
                    ->where('is_main', true)
                    ->orWhere('name', 'LIKE', '%رئيس%')
                    ->orWhere('name', 'LIKE', '%رئيسية%')
                    ->first();

                if ($mainPharmacy) {
                    $user->pharmacy_id = $mainPharmacy->id;
                }
            }
        }
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user)
    {
        if (!Auth::check()) return;

        $currentUser = Auth::user();

        // 1. Log Patient Creation (Legacy handling)
        if ($user->type === 'patient') {
            AuditLog::create([
                'user_id'    => $currentUser->id,
                'hospital_id' => $currentUser->hospital_id ?? null,
                'action'     => 'create_patient',
                'table_name' => 'users',
                'record_id'  => $user->id,
                'new_values' => json_encode($user->only(['full_name', 'national_id', 'birth_date', 'phone', 'email', 'hospital_id', 'pharmacy_id'])),
                'ip_address' => request()->ip(),
            ]);
            return;
        }

        // 2. Log Generic User Creation (Super Admin Models)
        AuditLog::create([
            'user_id'    => $currentUser->id,
            'hospital_id' => $currentUser->hospital_id ?? null,
            'action'     => 'create',
            'table_name' => 'users',
            'record_id'  => $user->id,
            'new_values' => json_encode($user->makeHidden(['password', 'remember_token'])->toArray()),
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user)
    {
        if (!Auth::check()) return;
        $currentUser = Auth::user();

        // 1. Log Patient Update
        if ($user->type === 'patient') {
            AuditLog::create([
                'user_id'    => $currentUser->id,
                'hospital_id' => $currentUser->hospital_id ?? null,
                'action'     => 'update_patient',
                'table_name' => 'users',
                'record_id'  => $user->id,
                'old_values' => json_encode($user->getOriginal()),
                'new_values' => json_encode($user->getChanges()),
                'ip_address' => request()->ip(),
            ]);
            return;
        }

        // 2. Generic User Update
        AuditLog::create([
            'user_id'    => $currentUser->id,
            'hospital_id' => $currentUser->hospital_id ?? null,
            'action'     => 'update',
            'table_name' => 'users',
            'record_id'  => $user->id,
            'old_values' => json_encode($user->getOriginal()),
            'new_values' => json_encode($user->getChanges()),
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Handle the User "deleting" event.
     */
    public function deleting(User $user)
    {
        if (!Auth::check()) return;
        $currentUser = Auth::user();

        // 1. Log Patient Delete
        if ($user->type === 'patient') {
            AuditLog::create([
                'user_id'    => $currentUser->id,
                'hospital_id' => $currentUser->hospital_id ?? null,
                'action'     => 'delete_patient',
                'table_name' => 'users',
                'record_id'  => $user->id,
                'old_values' => json_encode($user->only(['full_name', 'national_id', 'birth_date', 'phone', 'email', 'hospital_id', 'pharmacy_id'])),
                'new_values' => null,
                'ip_address' => request()->ip(),
            ]);
            return;
        }

        // 2. Generic User Delete
        AuditLog::create([
            'user_id'    => $currentUser->id,
            'hospital_id' => $currentUser->hospital_id ?? null,
            'action'     => 'delete',
            'table_name' => 'users',
            'record_id'  => $user->id,
            'old_values' => json_encode($user->makeHidden(['password', 'remember_token'])->toArray()),
            'new_values' => null,
            'ip_address' => request()->ip(),
        ]);
    }
}
