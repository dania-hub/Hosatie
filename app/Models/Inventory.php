<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventories';

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

    /**
     * التحقق من انتهاء صلاحية الدواء
     * 
     * @return bool
     */
    public function isExpired(): bool
    {
        if (!$this->drug || !$this->drug->expiry_date) {
            return false;
        }

        // تحويل تاريخ انتهاء الصلاحية إلى تاريخ فقط (Y-m-d) بدون وقت
        $expiryDate = Carbon::parse($this->drug->expiry_date)->format('Y-m-d');
        // استخدام التاريخ الحالي فقط (Y-m-d) بدون وقت لتجنب مشاكل المنطقة الزمنية
        $today = Carbon::now()->format('Y-m-d');
        
        // الدواء منتهي الصلاحية إذا كان تاريخ انتهاء الصلاحية اليوم أو قبل اليوم
        return $expiryDate <= $today;
    }

    /**
     * تصفير الكمية للأدوية المنتهية الصلاحية
     * يمكن استدعاء هذه الطريقة يدوياً أو من خلال الأمر المجدول
     * 
     * @return int عدد المخزونات التي تم تحديثها
     */
    public static function expireInventories(): int
    {
        // استخدام التاريخ الحالي فقط (Y-m-d) بدون وقت لتجنب مشاكل المنطقة الزمنية
        $today = Carbon::now()->format('Y-m-d');
        
        // جلب جميع الأدوية المنتهية الصلاحية (اليوم أو قبل اليوم)
        // استخدام DATE() في MySQL للمقارنة بالتاريخ فقط
        $expiredDrugIds = Drug::whereRaw("DATE(expiry_date) <= ?", [$today])->pluck('id');
        
        if ($expiredDrugIds->isEmpty()) {
            return 0;
        }

        // تصفير الكمية لجميع المخزونات المنتهية الصلاحية
        return static::whereIn('drug_id', $expiredDrugIds)
            ->where('current_quantity', '>', 0)
            ->update(['current_quantity' => 0]);
    }

    /**
     * Boot method لتسجيل الأحداث
     */
    protected static function boot()
    {
        parent::boot();

        // تصفير الكميات للأدوية المنتهية الصلاحية عند كل عملية جلب
        static::retrieved(function ($inventory) {
            if ($inventory->current_quantity > 0 && $inventory->isExpired()) {
                $inventory->current_quantity = 0;
                $inventory->saveQuietly(); // حفظ بدون إطلاق أحداث إضافية
            }
        });
    }
}
