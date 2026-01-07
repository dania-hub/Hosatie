<?php
use App\Models\User;
use App\Models\InternalSupplyRequest;
use App\Models\Notification;
use App\Services\StaffNotificationService;

try {
    $user = User::first();
    $request = InternalSupplyRequest::first();
    
    if (!$user || !$request) {
        throw new Exception("Need at least one user and one request to test.");
    }

    $service = new StaffNotificationService();
    $service->notifyPharmacistShipmentApproved($user, $request);
    
    $latest = Notification::where('user_id', $user->id)->latest()->first();
    
    if ($latest && $latest->title === 'تم تجهيز شحنة أدوية' && ($latest->type === 'عادي' || $latest->type === 'مستعجل')) {
        echo "SUCCESS: Notification created and saved to DB.\n";
        echo "Type in DB: " . $latest->type . "\n";
    } else {
        echo "FAILURE: Notification not found or has incorrect data.\n";
        if ($latest) {
            echo "Latest found: " . json_encode($latest) . "\n";
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
