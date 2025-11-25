<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Drug extends Model
{
    use HasFactory;

    protected $table = 'drug';

    protected $fillable = [
        'name',
        'generic_name',
        'strength',
        'form',
        'category',
        'unit',
        'max_monthly_dose',
        'status',
        'manufacturer',
        'country',
        'utilization_type',
        'warnings',
        'expiry_date',
    ];

    public function inventoryItems()
    {
        return $this->hasMany(Inventory::class);
    }

    public function internalRequestItems()
    {
        return $this->hasMany(InternalSupplyRequestItem::class);
    }

    public function externalRequestItems()
    {
        return $this->hasMany(ExternalSupplyRequestItem::class);
    }

    public function prescriptionDrugs()
    {
        return $this->hasMany(PrescriptionDrug::class);
    }
}
