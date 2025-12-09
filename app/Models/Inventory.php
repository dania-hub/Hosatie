<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventory';

    protected $fillable = [
        'drug_id',
        'warehouse_id',
        'pharmacy_id', // <--- تمت الإضافة
        'current_quantity',
        'minimum_level',
        'supplier_id',
    ];

    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }

    // العلاقة مع المخزن الرئيسي (المستودع)
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    // العلاقة الجديدة مع الصيدلية
    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
