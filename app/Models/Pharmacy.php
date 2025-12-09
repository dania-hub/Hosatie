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
        'inventory_id', // قد لا تحتاج هذا العمود مستقبلاً
        'name',
        'status',
    ];

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }

    // العلاقة الجديدة: الصيدلية لديها مخزون متعدد (أدوية كثيرة)
    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    // (اختياري) إذا كنت تريد الوصول لمخزون الصيدلية كعلاقة واحدة قديمة، يفضل استخدام inventories أعلاه
    // public function inventory() { ... }

    public function internalSupplyRequests()
    {
        return $this->hasMany(InternalSupplyRequest::class);
    }

    public function dispensings()
    {
        return $this->hasMany(Dispensing::class);
    }
}
