<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Drug extends Model
{
    use HasFactory;

    protected $table = 'drugs';

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
        'warnings','indications', 'contraindications',
        'expiry_date',
    ];

    // ============================================================
    // 1. علاقات المخزون (أين يتواجد الدواء؟)
    // ============================================================

    /**
     * العلاقة العامة مع جدول المخزون (تشمل كل الأماكن)
     */
    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * كميات الدواء الموجودة في "صيدليات" المستشفيات فقط
     */
    public function pharmacyInventories()
    {
        return $this->hasMany(Inventory::class)->whereNotNull('pharmacy_id');
    }

    /**
     * كميات الدواء الموجودة في "المستودعات الرئيسية" للمستشفيات فقط
     */
    public function warehouseInventories()
    {
        return $this->hasMany(Inventory::class)->whereNotNull('warehouse_id');
    }

    /**
     * كميات الدواء الموجودة عند "الموردين" (إذا كنت تخزن مخزون المورد)
     */
    public function supplierInventories()
    {
        return $this->hasMany(Inventory::class)->whereNotNull('supplier_id');
    }

    // ============================================================
    // 2. علاقات الطلبات (حركة الدواء)
    // ============================================================

    /**
     * بنود الطلبات الداخلية (من الصيدلية للمستودع)
     */
    public function internalRequestItems()
    {
        return $this->hasMany(InternalSupplyRequestItem::class);
    }

    /**
     * بنود الطلبات الخارجية (من المستودع للمورد/الجهات الخارجية)
     */
    public function externalRequestItems()
    {
        return $this->hasMany(ExternalSupplyRequestItem::class);
    }

    // ============================================================
    // 3. علاقات الصرف والوصفات (استهلاك الدواء)
    // ============================================================

    /**
     * تواجده في الوصفات الطبية
     */
    public function prescriptionDrugs()
    {
        return $this->hasMany(PrescriptionDrug::class);
    }

    /**
     * سجلات الصرف الفعلي لهذا الدواء (Dispensing History)
     */
    public function dispensings()
    {
        return $this->hasMany(Dispensing::class);
    }

    public function prescriptions()
    {
        return $this->belongsToMany(Prescription::class, 'prescription_drugs', 'drug_id', 'prescription_id')
                    ->withPivot('id', 'monthly_quantity', 'daily_quantity')
                    ->withTimestamps();
    }
}
