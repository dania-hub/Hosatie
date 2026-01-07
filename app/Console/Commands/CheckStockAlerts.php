<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Inventory;
use App\Models\Drug;
use App\Models\User;
use App\Services\StaffNotificationService;
use Carbon\Carbon;

class CheckStockAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:check-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for low stock and expiring drugs and send notifications';

    public function __construct(
        private StaffNotificationService $notifications
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting stock check...');

        $this->checkLowStock();
        $this->checkExpiry();

        $this->info('Stock check completed.');
    }

    private function checkLowStock()
    {
        $this->info('Checking low stock...');
        
        $lowStockInventories = Inventory::whereNotNull('minimum_level')
            ->whereColumn('current_quantity', '<=', 'minimum_level')
            ->get();

        foreach ($lowStockInventories as $inventory) {
            $this->notifications->checkAndNotifyLowStock($inventory);
        }
    }

    private function checkExpiry()
    {
        $this->info('Checking expiry dates...');

        // Check drugs expiring in the next 90 days
        $expiringDrugs = Drug::where('expiry_date', '>', now())
            ->where('expiry_date', '<=', now()->addDays(90))
            ->get();

        foreach ($expiringDrugs as $drug) {
            // Find who has this drug in stock
            $inventories = Inventory::where('drug_id', $drug->id)
                ->where('current_quantity', '>', 0)
                ->with(['pharmacy', 'warehouse'])
                ->get();

            foreach ($inventories as $inventory) {
                // Notify Pharmacists
                if ($inventory->pharmacy_id) {
                    $pharmacists = User::where('type', 'pharmacist')
                        ->where('pharmacy_id', $inventory->pharmacy_id)
                        ->get();
                    
                    foreach ($pharmacists as $pharmacist) {
                        $this->notifications->notifyStockAlert(
                            $pharmacist,
                            $drug,
                            'expiry',
                            $drug->expiry_date // Or formatted date
                        );
                    }
                }

                // Notify Warehouse Managers
                if ($inventory->warehouse_id && $inventory->warehouse) {
                    $managers = User::where('type', 'warehouse_manager')
                        ->where('hospital_id', $inventory->warehouse->hospital_id)
                        ->get();

                    foreach ($managers as $manager) {
                        $this->notifications->notifyStockAlert(
                            $manager,
                            $drug,
                            'expiry',
                            $drug->expiry_date
                        );
                    }
                }
            }
        }
    }
}
