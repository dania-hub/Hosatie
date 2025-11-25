<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientTransferRequest extends Model
{
    use HasFactory;

    protected $table = 'patient_transfer_request';

    protected $fillable = [
        'patient_id',
        'from_hospital_id',
        'to_hospital_id',
        'requested_by',
        'status',
        'reason',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function fromHospital()
    {
        return $this->belongsTo(Hospital::class, 'from_hospital_id');
    }

    public function toHospital()
    {
        return $this->belongsTo(Hospital::class, 'to_hospital_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
