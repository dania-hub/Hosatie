<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Drug extends Model
{
    use HasFactory;

    // Drug Statuses
    public const STATUS_AVAILABLE = 'متوفر';
    public const STATUS_UNAVAILABLE = 'غير متوفر';
    public const STATUS_PHASING_OUT = 'قيد الإيقاف التدريجي';
    public const STATUS_ARCHIVED = 'مؤرشف';

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

    /**
     * التحقق من إجمالي المخزون وأرشفة الدواء إذا كانت الكمية صفراً وهو في مرحلة الإيقاف التدريجي.
     * 
     * @param int|null $hospitalId معرف المستشفى للتحقق من مخزونها المحلي (اختياري)
     */
    public function checkAndArchiveIfNoStock($hospitalId = null)
    {
        if ($this->status !== self::STATUS_PHASING_OUT) {
            return;
        }

        $staffNotificationService = app(\App\Services\StaffNotificationService::class);

        // إذا تم تحديد hospital_id، نتحقق من مخزون هذه المستشفى فقط
        if ($hospitalId) {
            $hospitalStock = \App\Models\Inventory::where('drug_id', $this->id)
                ->where(function($query) use ($hospitalId) {
                    // مخزون المستودعات التابعة لهذا المستشفى
                    $query->whereHas('warehouse', function($q) use ($hospitalId) {
                        $q->where('hospital_id', $hospitalId);
                    })
                    // أو مخزون الصيدليات التابعة لهذا المستشفى
                    ->orWhereHas('pharmacy', function($q) use ($hospitalId) {
                        $q->where('hospital_id', $hospitalId);
                    });
                })
                ->sum('current_quantity');

            // إذا وصل مخزون هذه المستشفى إلى صفر، نرسل إشعار لمديرها
            if ($hospitalStock <= 0) {
                try {
                    $staffNotificationService->notifyHospitalStockZero($this, $hospitalId);
                } catch (\Exception $e) {
                    \Log::error('Hospital stock zero notification failed', [
                        'drug_id' => $this->id,
                        'hospital_id' => $hospitalId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // دائماً نتحقق من المخزون الكلي عبر جميع المواقع
        $totalStock = \App\Models\Inventory::where('drug_id', $this->id)->sum('current_quantity');

        // إذا وصل المخزون الكلي (جميع المستشفيات والموردين) إلى صفر
        if ($totalStock <= 0) {
            $this->update(['status' => self::STATUS_ARCHIVED]);
            
            // إرسال إشعار نهائي بالأرشفة للمدير الأعلى فقط
            try {
                $staffNotificationService->notifyDrugArchived($this);
            } catch (\Exception $e) {
                \Log::error('Archived notification failed during auto-archiving', ['error' => $e->getMessage()]);
            }
        }
    }

    public function prescriptions()
    {
        return $this->belongsToMany(Prescription::class, 'prescription_drugs', 'drug_id', 'prescription_id')
                    ->withPivot('id', 'monthly_quantity', 'daily_quantity')
                    ->withTimestamps();
    }
}
