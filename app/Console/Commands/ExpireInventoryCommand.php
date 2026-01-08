<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Inventory;

class ExpireInventoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set current_quantity to 0 for all inventories with expired drugs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('جارٍ التحقق من الأدوية المنتهية الصلاحية...');

        try {
            $count = Inventory::expireInventories();
            
            if ($count > 0) {
                $this->info("تم تصفير الكمية لـ {$count} مخزون من الأدوية المنتهية الصلاحية.");
            } else {
                $this->info('لا توجد مخزونات تحتاج إلى تحديث.');
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error('حدث خطأ أثناء تحديث المخزونات: ' . $e->getMessage());
            return 1;
        }
    }
}

