<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hospital extends Model
{
    use HasFactory;

    protected $table = 'hospital';

    protected $fillable = [
        'supplier_id',
        'name',
        'code',
        'type',
        'city',
        'address',
        'phone',
        'status',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->hasOne(Warehouse::class);
    }

    public function pharmacies()
    {
        return $this->hasMany(Pharmacy::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function patientTransferRequestsFrom()
    {
        return $this->hasMany(PatientTransferRequest::class, 'from_hospital_id');
    }

    public function patientTransferRequestsTo()
    {
        return $this->hasMany(PatientTransferRequest::class, 'to_hospital_id');
    }

    public function externalSupplyRequests()
    {
        return $this->hasMany(ExternalSupplyRequest::class);
    }
}
