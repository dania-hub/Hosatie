<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pharmacy extends Model
{
    use HasFactory;

    protected $table = 'pharmacy';

    protected $fillable = [
        'hospital_id',
        'inventory_id',
        'name',
        'status',
    ];

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function internalSupplyRequests()
    {
        return $this->hasMany(InternalSupplyRequest::class);
    }

    public function dispensings()
    {
        return $this->hasMany(Dispensing::class);
    }
}
