<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dispensing extends Model
{
    use HasFactory;

    protected $table = 'dispensing';

    public $timestamps = false;

    protected $fillable = [
        'prescription_id',
        'patient_id',
        'pharmacist_id',
        'pharmacy_id',
        'dispense_month',
        'quantity_dispensed',
        'reverted',
        'reverted_at',
        'reverted_by',
        'created_at',
    ];

    protected $casts = [
        'dispense_month' => 'date',
        'reverted_at'    => 'datetime',
        'reverted'       => 'boolean',
        'created_at'     => 'datetime',
    ];

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function pharmacist()
    {
        return $this->belongsTo(User::class, 'pharmacist_id');
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function reverter()
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }
    public function drug() {
        return $this->belongsTo(Drug::class, 'drug_id');
}
}