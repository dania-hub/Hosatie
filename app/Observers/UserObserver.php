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
                    ->where(function($q) {
                        $q->where('name', 'LIKE', '%رئيس%')
                          ->orWhere('name', 'LIKE', '%رئيسية%');
                    })
                    ->first();

                // إذا لم يتم العثور على صيدلية باسم "رئيسية"، نأخذ أول صيدلية للمستشفى
                if (!$mainPharmacy) {
                    $mainPharmacy = Pharmacy::where('hospital_id', $creator->hospital_id)->first();
                }

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
     * Handle the User "updating" event.
     */
    public function updating(User $user)
    {
        // تعطيل/تفعيل الحساب تلقائياً عند تغيير hospital_id أو supplier_id
        $original = $user->getOriginal();
        $changes = $user->getDirty();
        
        // إذا كان مدير مستشفى
        if ($user->type === 'hospital_admin') {
            if (isset($changes['hospital_id'])) {
                // إذا تم إزالة hospital_id (أصبح null)
                if ($original['hospital_id'] !== null && $user->hospital_id === null) {
                    // تعطيل الحساب تلقائياً
                    $user->status = 'inactive';
                }
                // إذا تم ربطه بمستشفى (أصبح له hospital_id)
                elseif ($original['hospital_id'] === null && $user->hospital_id !== null) {
                    // تفعيل الحساب تلقائياً
                    $user->status = 'active';
                }
            }
        }
        
        // إذا كان مدير مورد
        if ($user->type === 'supplier_admin') {
            if (isset($changes['supplier_id'])) {
                // إذا تم إزالة supplier_id (أصبح null)
                if ($original['supplier_id'] !== null && $user->supplier_id === null) {
                    // تعطيل الحساب تلقائياً
                    $user->status = 'inactive';
                }
                // إذا تم ربطه بمورد (أصبح له supplier_id)
                elseif ($original['supplier_id'] === null && $user->supplier_id !== null) {
                    // تفعيل الحساب تلقائياً
                    $user->status = 'active';
                }
            }
        }
    }
    
    /**
     * Handle the User "updated" event - حذف tokens بعد التحديث
     */
    public function updated(User $user)
    {
        // حذف tokens إذا تم تعطيل الحساب بسبب إزالة hospital_id أو supplier_id
        $original = $user->getOriginal();
        $changes = $user->getChanges();
        
        // إذا كان مدير مستشفى وتم إزالة hospital_id
        if ($user->type === 'hospital_admin') {
            if (isset($changes['hospital_id']) && 
                $original['hospital_id'] !== null && 
                $user->hospital_id === null) {
                // حذف جميع tokens
                $user->tokens()->delete();
            }
        }
        
        // إذا كان مدير مورد وتم إزالة supplier_id
        if ($user->type === 'supplier_admin') {
            if (isset($changes['supplier_id']) && 
                $original['supplier_id'] !== null && 
                $user->supplier_id === null) {
                // حذف جميع tokens
                $user->tokens()->delete();
            }
        }
        
        // تسجيل التحديث في AuditLog
        if (!Auth::check()) return;
        $currentUser = Auth::user();

        // 1. Log Patient Update
        if ($user->type === 'patient') {
            // التحقق من أن التحديث ليس حذفاً (تغيير status إلى 'deleted')
            // إذا كان التغيير الوحيد هو status إلى 'deleted'، لا نسجل عملية تعديل
            // لأن عملية الحذف يتم تسجيلها يدوياً في PatientDataEntryController
            $changes = $user->getChanges();
            $original = $user->getOriginal();
            
            // إذا كان التغيير الوحيد هو status إلى 'deleted'، نتجاهل هذا التحديث
            if (isset($changes['status']) && 
                $changes['status'] === 'deleted' && 
                count($changes) === 1) {
                return; // لا نسجل عملية تعديل، لأن الحذف يتم تسجيله يدوياً
            }
            
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
