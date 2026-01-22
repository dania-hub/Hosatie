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
        'pharmacy_id',
        'department_id',
        'current_quantity',
        'expiry_date',
        'batch_number',
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

    // العلاقة مع القسم
    public function department()
    {
        return $this->belongsTo(Department::class);
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
        if (!$this->expiry_date) {
            return false;
        }

        // تحويل تاريخ انتهاء الصلاحية إلى تاريخ فقط (Y-m-d) بدون وقت
        $expiryDate = Carbon::parse($this->expiry_date)->format('Y-m-d');
        // استخدام التاريخ الحالي فقط (Y-m-d) بدون وقت لتجنب مشاكل المنطقة الزمنية
        $today = Carbon::now()->format('Y-m-d');
        
        // الدواء منتهي الصلاحية إذا كان تاريخ انتهاء الصلاحية قبل اليوم
        // (إذا كان يساوي اليوم فهو لا يزال صالحاً حتى نهاية اليوم)
        return $expiryDate < $today;
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
        
        // تصفير الكمية لجميع المخزونات المنتهية الصلاحية (قبل اليوم)
        return static::where('current_quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereRaw("DATE(expiry_date) < ?", [$today])
            ->update(['current_quantity' => 0]);
    }

    /**
     * Boot method لتسجيل الأحداث
     */
    protected static function boot()
    {
        parent::boot();

        // تم تعطيل التصفير التلقائي عند الجلب (retrieved)
        // لأن ذلك يخفي الأدوية التي انتهت صلاحيتها ولكن لا تزال في المخزون
        // مما يسبب إرباكاً في عرض الكميات (يظهر الكمية 0 بينما هي موجودة فعلياً)
        
        /*
        static::retrieved(function ($inventory) {
            if ($inventory->current_quantity > 0 && $inventory->isExpired()) {
                $inventory->current_quantity = 0;
                $inventory->saveQuietly(); // حفظ بدون إطلاق أحداث إضافية
            }
        });
        */
    }
}
