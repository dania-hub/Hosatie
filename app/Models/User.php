<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'national_id',
        'type',
        'email',
        'phone',
        'full_name',
        'password',
        'fcm_token',
        'warehouse_id',
        'hospital_id',
        'supplier_id',
        'department_id',
        'pharmacy_id',
        'status',
        'created_by',
        'birth_date',   // 👈 جديد
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'birth_date' => 'date', // اختياري لكنه مفيد
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function prescriptionsAsPatient()
    {
        return $this->hasMany(Prescription::class, 'patient_id');
    }

    public function prescriptionsAsDoctor()
    {
        return $this->hasMany(Prescription::class, 'doctor_id');
    }
}
