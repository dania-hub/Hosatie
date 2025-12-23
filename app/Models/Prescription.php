<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    use HasFactory;

    protected $table = 'prescription';

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'hospital_id',
        'status',
        'start_date',
        'end_date',
        'cancelled_at',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }

    // Relationship to Drugs (Many-to-Many via prescription_drug)
    public function drugs()
    {
        return $this->belongsToMany(Drug::class, 'prescription_drug', 'prescription_id', 'drug_id')
                    // نضيف حقل id من جدول pivot حتى يصل للواجهة ويُستخدم كمعرّف للتعديل/الحذف
                    ->withPivot('id', 'monthly_quantity', 'daily_quantity')
                    ->withTimestamps();
    }
}
