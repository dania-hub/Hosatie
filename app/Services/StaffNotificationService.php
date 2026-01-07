<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\InternalSupplyRequest;
use App\Models\ExternalSupplyRequest;
use App\Models\Drug;
use App\Models\PatientTransferRequest;
use App\Models\Complaint;
use Illuminate\Support\Facades\Log;

class StaffNotificationService
{
    /**
     * 1. Pharmacist Notifications
     */

    // Notify Requester (Pharmacist/Department Head) about Shipment Approval
    public function notifyRequesterShipmentApproved(User $user, InternalSupplyRequest $request)
    {
        $this->createNotification(
            $user,
            'تم تجهيز شحنة أدوية',
            "تم تجهيز شحنة أدوية جديدة (رقم #{$request->id}) وهي في طريقها إليكم. يرجى الاستعداد للاستلام.",
            'عادي'
        );
    }

    // Notify Requester about Shipment Rejection
    public function notifyRequesterShipmentRejected(User $user, InternalSupplyRequest $request, ?string $reason = null)
    {
        $this->createNotification(
            $user,
            'تم رفض الشحنة',
            "تم رفض طلب تجهيز الأدوية (رقم #{$request->id}) بسبب: " . ($reason ?? 'غير محدد'),
            'مستعجل'
        );
    }

    // Check and notify relevant staff if stock is low
    public function checkAndNotifyLowStock(\App\Models\Inventory $inventory)
    {
        if ($inventory->minimum_level === null || $inventory->current_quantity > $inventory->minimum_level) {
            return;
        }

        $drug = $inventory->drug;
        if (!$drug) {
            $drug = \App\Models\Drug::find($inventory->drug_id);
        }
        if (!$drug) return;

        $locationName = '';
        $recipients = collect();

        // 1. Pharmacy Low Stock
        if ($inventory->pharmacy_id) {
            if (!$inventory->relationLoaded('pharmacy')) {
                $inventory->load('pharmacy');
            }
            $locationName = $inventory->pharmacy->name ?? 'الصيدلية';
            $recipients = User::where('type', 'pharmacist')
                ->where('pharmacy_id', $inventory->pharmacy_id)
                ->get();
        }
        // 2. Warehouse Low Stock
        elseif ($inventory->warehouse_id) {
            if (!$inventory->relationLoaded('warehouse')) {
                $inventory->load('warehouse');
            }
            $locationName = $inventory->warehouse->name ?? 'المستودع الرئيسي';
            $hospitalId = $inventory->warehouse->hospital_id ?? null;
            if ($hospitalId) {
                $recipients = User::whereIn('type', ['warehouse_manager', 'hospital_admin'])
                    ->where('hospital_id', $hospitalId)
                    ->get();
            }
        }
        // 3. Supplier Low Stock
        elseif ($inventory->supplier_id) {
            if (!$inventory->relationLoaded('supplier')) {
                $inventory->load('supplier');
            }
            $locationName = $inventory->supplier->name ?? 'المورد';
            $recipients = User::where('type', 'supplier_admin')
                ->where('supplier_id', $inventory->supplier_id)
                ->get();
        }

        foreach ($recipients as $user) {
            $this->notifyStockAlert($user, $drug, 'low_stock', $inventory->current_quantity, $locationName);
        }
    }

    // Notify about Stock Levels (Low Stock / Expiry)
    public function notifyStockAlert(User $user, Drug $drug, string $alertType, $value, ?string $locationName = null)
    {
        $title = '';
        $message = '';
        $type = 'عادي';
        $location = $locationName ? " في [{$locationName}]" : "";

        if ($alertType === 'low_stock') {
            $title = 'تنبيه: رصيد دواء منخفض';
            $message = "تنبيه: رصيد دواء '{$drug->name}'{$location} وصل إلى الحد الأدنى. الكمية الحالية: {$value}. يرجى اتخاذ اللازم لتأمين النقص.";
        } elseif ($alertType === 'expiry') {
            $title = 'تنبيه: دواء على وشك الانتهاء';
            $message = "تنبيه: توجد كمية من دواء '{$drug->name}'{$location} ستنتهي صلاحيتها بتاريخ {$value}. يرجى مراجعة المخزون.";
        }

        if ($title) {
            $this->createNotification($user, $title, $message, $type);
        }
    }

    /**
     * 2. Warehouse Manager Notifications
     */

    // Notify about New Internal Supply Request
    public function notifyWarehouseNewInternalRequest(InternalSupplyRequest $request)
    {
        if (!$request->relationLoaded('pharmacy')) {
            $request->load('pharmacy');
        }

        $hospitalId = $request->pharmacy->hospital_id ?? null;

        if (!$hospitalId && $request->requested_by) {
            $requester = User::find($request->requested_by);
            $hospitalId = $requester->hospital_id ?? null;
        }

        if (!$hospitalId) return;

        $managers = User::where('type', 'warehouse_manager')->where('hospital_id', $hospitalId)->get();

        foreach ($managers as $manager) {
            $this->createNotification(
                $manager,
                'طلب توريد داخلي جديد',
                "تم استلام طلب توريد داخلي جديد (رقم #{$request->id}). يرجى المراجعة والمعالجة.",
                'عادي'
            );
        }
    }

    // Notify about External Request Approval/Rejection (from Admin)
    public function notifyWarehouseExternalUpdate(ExternalSupplyRequest $request, string $status, ?string $reason = null)
    {
        $requester = User::find($request->requested_by);
        if (!$requester) return;

        if ($status === 'approved') {
            $this->createNotification(
                $requester,
                'تمت الموافقة على طلب التوريد الخارجي',
                "تمت الموافقة على طلب التوريد الخارجي (رقم #{$request->id}) من قبل مدير المستشفى، وتم إرساله إلى المورد.",
                'عادي'
            );
        } elseif ($status === 'rejected') {
            $this->createNotification(
                $requester,
                'تم رفض طلب التوريد الخارجي',
                "تم رفض طلب التوريد الخارجي (رقم #{$request->id}) من قبل مدير المستشفى. السبب: " . ($reason ?? 'غير محدد'),
                'مستعجل'
            );
        }
    }

    // Notify about Supplier Acceptance
    public function notifyWarehouseSupplierAccepted(ExternalSupplyRequest $request)
    {
        $requester = User::find($request->requested_by);
        if (!$requester) return;

        if (!$request->relationLoaded('supplier')) {
            $request->load('supplier');
        }

        $supplierName = $request->supplier->name ?? 'المورد';
        $this->createNotification(
            $requester,
            'تم قبول الطلب من المورد',
            "قام المورد {$supplierName} بقبول طلب التوريد (رقم #{$request->id}). حالة الطلب الآن 'قيد التجهيز'.",
            'عادي'
        );
    }

    // Notify about Supplier Rejection
    public function notifyWarehouseSupplierRejected(ExternalSupplyRequest $request, ?string $reason = null)
    {
        $requester = User::find($request->requested_by);
        if (!$requester) return;

        if (!$request->relationLoaded('supplier')) {
            $request->load('supplier');
        }

        $supplierName = $request->supplier->name ?? 'المورد';
        $this->createNotification(
            $requester,
            'تم رفض الطلب من المورد',
            "قام المورد {$supplierName} برفض طلب التوريد (رقم #{$request->id}). السبب: " . ($reason ?? 'غير محدد'),
            'مستعجل'
        );
    }

    /**
     * 3. Supplier Notifications
     */

    // Notify Supplier about New Request
    public function notifySupplierNewRequest(ExternalSupplyRequest $request)
    {
        if (!$request->supplier_id) return;

        $supplierAdmins = User::where('type', 'supplier_admin')
            ->where('supplier_id', $request->supplier_id) // Assuming supplier_id exists on User
            ->get();

        foreach ($supplierAdmins as $admin) {
            $this->createNotification(
                $admin,
                'طلب توريد جديد',
                "تم استلام طلب توريد جديد (رقم #{$request->id}) من " . ($request->hospital->name ?? 'مستشفى') . ". يرجى المراجعة والرد.",
                'عادي'
            );
        }
    }

    // Notify Supplier Reminder
    public function notifySupplierReminder(User $supplierAdmin, ExternalSupplyRequest $request)
    {
        $this->createNotification(
            $supplierAdmin,
            'تذكير بطلب توريد',
            "تذكير: طلب التوريد (رقم #{$request->id}) من " . ($request->hospital->name ?? 'مستشفى') . " لا يزال بانتظار ردكم.",
            'مستعجل'
        );
    }

    /**
     * 4. System Manager (Admin) Notifications
     */

    // Notify about New External Request needing approval
    public function notifyAdminNewExternalRequest(ExternalSupplyRequest $request)
    {
        $admins = User::where('type', 'hospital_admin')->where('hospital_id', $request->hospital_id)->get();

        foreach ($admins as $admin) {
            $this->createNotification(
                $admin,
                'طلب توريد خارجي يحتاج موافقة',
                "يوجد طلب توريد خارجي جديد (رقم #{$request->id}) بانتظار موافقتكم. يرجى المراجعة واتخاذ القرار.",
                'عادي'
            );
        }
    }

    // Notify about Patient Transfer (To the source hospital admin)
    public function notifyAdminTransferRequest(PatientTransferRequest $request)
    {
        if (!$request->relationLoaded('patient')) {
            $request->load('patient');
        }

        $hospitalId = $request->from_hospital_id;
        if (!$hospitalId) {
            $hospitalId = $request->patient->hospital_id ?? null;
        }

        if (!$hospitalId) return;

        $admins = User::where('type', 'hospital_admin')->where('hospital_id', $hospitalId)->get();
        $patientName = $request->patient->full_name ?? 'مريض';

        foreach ($admins as $admin) {
            $this->createNotification(
                $admin,
                'طلب نقل ملف طبي جديد',
                "يوجد طلب نقل ملف طبي جديد للمريض [{$patientName}] بانتظار معالجتكم.",
                'عادي'
            );
        }
    }

    // Notify Destination Hospital Admin about Pre-approval
    public function notifyDestinationAdminPreApproved(PatientTransferRequest $request)
    {
        if (!$request->relationLoaded('patient')) {
            $request->load('patient');
        }
        if (!$request->relationLoaded('fromHospital')) {
            $request->load('fromHospital');
        }

        $hospitalId = $request->to_hospital_id;
        if (!$hospitalId) return;

        $admins = User::where('type', 'hospital_admin')->where('hospital_id', $hospitalId)->get();
        $patientName = $request->patient->full_name ?? 'مريض';
        $fromHospitalName = $request->fromHospital->name ?? 'مستشفى آخر';

        foreach ($admins as $admin) {
            $this->createNotification(
                $admin,
                'طلب نقل مريض بانتظار الموافقة النهائية',
                "تنبيه: تمت الموافقة المبدئية على نقل المريض [{$patientName}] من [{$fromHospitalName}]. يرجى مراجعة الطلب لتكملة الإجراءات.",
                'عادي'
            );
        }
    }

    // Notify about Patient Complaint
    public function notifyAdminComplaint(Complaint $complaint)
    {
        if (!$complaint->relationLoaded('patient')) {
            $complaint->load('patient');
        }

        $hospitalId = $complaint->patient->hospital_id ?? null;
        if (!$hospitalId) return;

        $admins = User::where('type', 'hospital_admin')->where('hospital_id', $hospitalId)->get();
        $patientName = $complaint->patient->full_name ?? 'مريض';

        foreach ($admins as $admin) {
            $this->createNotification(
                $admin,
                'شكوى جديدة من مريض',
                "تم استلام شكوى جديدة من المريض [{$patientName}]. يرجى الاطلاع عليها والرد.",
                'مستعجل'
            );
        }
    }

    // Notify about Shipment Damage/Shortage
    public function notifyAdminShipmentDamage(InternalSupplyRequest $request, string $entityName)
    {
        if (!$request->relationLoaded('pharmacy')) {
            $request->load('pharmacy');
        }

        $hospitalId = $request->pharmacy->hospital_id ?? null;

        if (!$hospitalId && $request->requested_by) {
            $requester = User::find($request->requested_by);
            $hospitalId = $requester->hospital_id ?? null;
        }

        if (!$hospitalId) {
             \Log::warning("Could not find hospital_id for request #{$request->id} in notifyAdminShipmentDamage");
             return;
        }

        // Notify both Hospital Admins and Warehouse Managers
        $recipients = User::whereIn('type', ['hospital_admin', 'warehouse_manager'])
            ->where('hospital_id', $hospitalId)
            ->get();

        foreach ($recipients as $user) {
            $this->createNotification(
                $user,
                'تنبيه عاجل: نقص/تلف في شحنة',
                "تنبيه عاجل: تم تسجيل نقص/تلف في الشحنة (رقم #{$request->id}) المستلمة من قبل [{$entityName}]. يرجى المتابعة.",
                'مستعجل'
            );
        }
    }

    // Notify about Delayed Request Processing
    public function notifyAdminDelayedProcessing(User $admin, InternalSupplyRequest $request)
    {
        $this->createNotification(
            $admin,
            'تنبيه: تأخر معالجة طلب',
            "تنبيه: طلب التوريد الداخلي (رقم #{$request->id}) لم يتم معالجته منذ أكثر من 24 ساعة.",
            'عادي'
        );
    }

    // Notify about External Shipment Shortage
    public function notifyExternalShipmentShortage(ExternalSupplyRequest $request, array $shortageItems)
    {
        if (!$request->relationLoaded('hospital')) {
            $request->load('hospital');
        }
        if (!$request->relationLoaded('supplier')) {
            $request->load('supplier');
        }

        $hospitalName = $request->hospital->name ?? 'المستشفى';
        $supplierName = $request->supplier->name ?? 'المورد';
        
        $itemNames = collect($shortageItems)->map(function($item) {
            return $item['name'];
        })->join(', ');

        $title = 'تنبيه عاجل: نقص في شحنة خارجية';
        $message = "تم تسجيل نقص في الشحنة رقم (EXT-#{$request->id}) المستلمة من [{$supplierName}] إلى [{$hospitalName}]. الأدوية المتأثرة: [{$itemNames}]. يرجى المتابعة.";

        // 1. Notify Hospital Admins
        $hospitalAdmins = User::where('type', 'hospital_admin')
            ->where('hospital_id', $request->hospital_id)
            ->get();

        foreach ($hospitalAdmins as $admin) {
            $this->createNotification($admin, $title, $message, 'مستعجل');
        }

        // 2. Notify Supplier Admins
        $supplierAdmins = User::where('type', 'supplier_admin')
            ->where('supplier_id', $request->supplier_id)
            ->get();

        foreach ($supplierAdmins as $admin) {
            $this->createNotification($admin, $title, $message, 'مستعجل');
        }

        // 3. Notify Super Admins
        $superAdmins = User::where('type', 'super_admin')->get();

        foreach ($superAdmins as $admin) {
            $this->createNotification($admin, $title, $message, 'مستعجل');
        }
    }

    /**
     * Internal helper to create notification
     */
    private function createNotification(User $user, string $title, string $message, string $type = 'عادي')
    {
        try {
            Notification::create([
                'user_id' => $user->id,
                'title'   => $title,
                'message' => $message,
                'type'    => $type, // Use the Arabic type directly
                'is_read' => false,
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to create notification for user {$user->id}: " . $e->getMessage());
        }
    }
}
