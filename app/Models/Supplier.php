<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $table = 'supplier';

    protected $fillable = [
        'name',
        'code',
        'phone',
        'address',
        'city',
        'status',
    ];

    public function hospitals()
    {
        return $this->hasMany(Hospital::class);
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function externalSupplyRequests()
    {
        return $this->hasMany(ExternalSupplyRequest::class);
    }
}
