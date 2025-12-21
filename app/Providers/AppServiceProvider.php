<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\User;
use App\Observers\UserObserver;
use App\Models\PrescriptionDrug;

use App\Models\Dispensing;
use App\Observers\DispensingObserver;

use App\Models\PatientTransferRequest;
use App\Observers\PatientTransferRequestObserver;

use App\Models\Prescription;
use App\Observers\PrescriptionObserver;

use App\Models\AuditLog;
use App\Observers\AuditLogObserver;

use App\Models\ExternalSupplyRequest;
use App\Observers\ExternalSupplyRequestObserver;

use App\Models\InternalSupplyRequest;
use App\Observers\InternalSupplyRequestObserver;

use App\Observers\PrescriptionDrugObserver;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers
        User::observe(UserObserver::class);
   InternalSupplyRequest::observe(InternalSupplyRequestObserver::class);
     Prescription::observe(PrescriptionObserver::class);
    Dispensing::observe(DispensingObserver::class);
     AuditLog::observe(AuditLogObserver::class);
     PatientTransferRequest::observe(PatientTransferRequestObserver::class);
        PrescriptionDrug::observe(PrescriptionDrugObserver::class);
  ExternalSupplyRequest::observe(ExternalSupplyRequestObserver::class);

    }
}
