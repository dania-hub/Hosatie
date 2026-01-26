<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\Notification;
use App\Models\PatientTransferRequest;
use App\Models\Prescription;
use App\Models\Drug;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PatientNotificationService
{
    public function __construct(
        private FcmLegacyService $fcm,
        private FcmV1Service $fcmV1
    ) {}

    public function notifyComplaintReplied(User $patient, Complaint $complaint): Notification
    {
        Log::info('🚨 === notifyComplaintReplied START ===', [
            'patient_id' => $patient->id,
            'complaint_id' => $complaint->id,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);
        
        $notification = $this->createNotification(
            $patient,
            'عادي',
            'تم الرد على شكواك',
            'تمت مراجعة الشكوى والرد عليها.'
        );
        
        Log::info('🚨 === notifyComplaintReplied END ===');
        
        return $notification;
    }

    public function notifyTransferApproved(User $patient, PatientTransferRequest $request): Notification
    {
        Log::info('🚨 === notifyTransferApproved START ===', [
            'patient_id' => $patient->id,
            'request_id' => $request->id,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);

        if (!$request->relationLoaded('toHospital')) {
            $request->load('toHospital');
        }

        $hospitalName = $request->toHospital->name ?? 'المستشفى الجديد';
        
        $notification = $this->createNotification(
            $patient,
            'عادي',
            'تمت الموافقة على طلب النقل',
            "تمت الموافقة على طلب نقلك إلى مستشفى [{$hospitalName}]."
        );
        
        Log::info('🚨 === notifyTransferApproved END ===');
        
        return $notification;
    }

    public function notifyTransferRejected(User $patient, PatientTransferRequest $request): Notification
    {
        Log::info('🚨 === notifyTransferRejected START ===', [
            'patient_id' => $patient->id,
            'request_id' => $request->id,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);
        
        $notification = $this->createNotification(
            $patient,
            'عادي',
            'تم رفض طلب النقل',
            'تم رفض طلب نقلك. يمكنك مراجعة الإدارة لمزيد من التفاصيل.'
        );
        
        Log::info('🚨 === notifyTransferRejected END ===');
        
        return $notification;
    }

    public function notifyDrugAssigned(User $patient, Prescription $prescription, Drug $drug): Notification
    {
        Log::info('🚨 === notifyDrugAssigned START ===', [
            'patient_id' => $patient->id,
            'drug_id' => $drug->id,
            'prescription_id' => $prescription->id,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);
        
        $notification = $this->createNotification(
            $patient,
            'عادي',
            'تم إضافة دواء جديد',
            "تمت إضافة دواء ({$drug->name}) إلى حصتك العلاجية."
        );
        
        Log::info('🚨 === notifyDrugAssigned END ===', [
            'notification_id' => $notification->id
        ]);
        
        return $notification;
    }

    public function notifyDrugDeleted(User $patient, Prescription $prescription, Drug $drug): Notification
    {
        Log::info('🚨 === notifyDrugDeleted START ===', [
            'patient_id' => $patient->id,
            'drug_id' => $drug->id,
            'prescription_id' => $prescription->id,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);
        
        $notification = $this->createNotification(
            $patient,
            'عادي',
            'تم حذف دواء من الحصة العلاجية',
            "تم حذف دواء ({$drug->name}) من حصتك العلاجية."
        );
        
        Log::info('🚨 === notifyDrugDeleted END ===', [
            'notification_id' => $notification->id
        ]);
        
        return $notification;
    }

    /**
     * إرسال إشعار للمريض عند صرف دواء له من الصيدلية.
     */
    public function notifyDrugDispensed(User $patient, Drug $drug, int $quantity): Notification
    {
        $title = "صرف دواء";
        $message = "تم صرف كمية ({$quantity}) من دواء ({$drug->name}) لك من الصيدلية.";
        
        return $this->createNotification($patient, 'عادي', $title, $message);
    }

    /**
     * إرسال إشعار ملخص لمجموعة من الأدوية المصروفة.
     */
    public function notifyTransactionDispensed(User $patient, array $drugsInfo): Notification
    {
        $title = "صرف أدوية";
        
        if (count($drugsInfo) === 1) {
            $item = $drugsInfo[0];
            $message = "تم صرف كمية ({$item['quantity']}) من دواء ({$item['drug_name']}) لك من صيدلية المستشفى.";
        } else {
            $drugNames = collect($drugsInfo)->pluck('drug_name')->implode('، ');
            $message = "تم صرف مجموعة من الأدوية ({$drugNames}) لك من صيدلية المستشفى.";
        }
        
        return $this->createNotification($patient, 'عادي', $title, $message);
    }

    /**
     * إرسال إشعار للمريض عند التراجع عن صرف دواء.
     */
    public function notifyDispenseReverted(User $patient, Drug $drug, int $quantity): Notification
    {
        $title = "تنبيه: التراجع عن صرف دواء";
        $message = "تم التراجع عن صرف دواء ({$drug->name}) بكمية ({$quantity})، حيث تم صرفه عن طريق الخطأ.";
        
        return $this->createNotification($patient, 'عادي', $title, $message);
    }

    /**
     * إرسال إشعار ملخص لمجموعة من الأدوية التي تم التراجع عن صرفها.
     */
    public function notifyTransactionReverted(User $patient, array $drugsInfo): Notification
    {
        $title = "تنبيه: تراجع عن صرف أدوية";
        
        if (count($drugsInfo) === 1) {
            $item = $drugsInfo[0];
            $message = "تم التراجع عن صرف دواء ({$item['drug_name']}) بكمية ({$item['quantity']})، حيث تم صرفه عن طريق الخطأ.";
        } else {
            $drugNames = collect($drugsInfo)->pluck('drug_name')->implode('، ');
            $message = "تم التراجع عن صرف مجموعة من الأدوية ({$drugNames})، حيث تم صرفهم عن طريق الخطأ.";
        }
        
        return $this->createNotification($patient, 'عادي', $title, $message);
    }

    /**
     * إرسال إشعار للمرضى عند توفر دواء كان غير متوفر في الصيدلية.
     */
    public function notifyDrugAvailability(Drug $drug, int $hospitalId): void
    {
        Log::info('🚨 === notifyDrugAvailability START ===', [
            'drug_id' => $drug->id,
            'hospital_id' => $hospitalId,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);

        // البحث عن جميع المرضى الذين لديهم هذا الدواء في وصفة نشطة في هذا المستشفى
        $patients = User::where('type', 'patient')
            ->whereHas('prescriptionsAsPatient', function ($query) use ($drug, $hospitalId) {
                $query->where('status', 'active')
                    ->where('hospital_id', $hospitalId)
                    ->whereHas('drugs', function ($q) use ($drug) {
                        $q->where('drug_id', $drug->id);
                    });
            })
            ->get();

        $title = "توفر دواء";
        $message = "نود إعلامك بأن دواء ({$drug->name}) أصبح متوفراً الآن في صيدلية المستشفى.";

        foreach ($patients as $patient) {
            $this->createNotification($patient, 'عادي', $title, $message);
        }

        Log::info('🚨 === notifyDrugAvailability END ===', [
            'notified_count' => $patients->count()
        ]);
    }

    /**
     * إرسال إشعار مستعجل للمرضى عند بدء مرحلة الإيقاف التدريجي للدواء.
     */
    public function notifyDrugPhasingOut(Drug $drug, $patients): void
    {
        Log::info('🚨 === notifyDrugPhasingOut START ===', [
            'drug_id' => $drug->id,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);

        $title = "إشعار مستعجل: إيقاف دعم دواء";
        $message = "عزيزي المريض، نحيطك علماً بأنه سيتم إيقاف دعم دواء ({$drug->name}) تدريجياً. يرجى مراجعة الطبيب المختص لمناقشة البدائل المتاحة لخطتك العلاجية.";

        foreach ($patients as $patient) {
            $this->createNotification($patient, 'مستعجل', $title, $message);
        }

        Log::info('🚨 === notifyDrugPhasingOut END ===', [
            'notified_count' => $patients->count()
        ]);
    }

    /**
     * إرسال إشعارات للمرضى عند إعادة تفعيل دواء كانوا يستخدمونه.
     */
    public function notifyDrugReactivated(Drug $drug, $patients): void
    {
        $title = "إشعار: إعادة تفعيل دواء";
        $message = "عزيزي المريض، نحيطك علماً بأنه تم إعادة تفعيل دواء ({$drug->name}) الذي تستخدمه. الدواء أصبح متاحاً مرة أخرى.";

        foreach ($patients as $patient) {
            $this->createNotification($patient, 'عادي', $title, $message);
        }

        Log::info('Patient drug reactivation notifications sent', [
            'drug_id' => $drug->id,
            'notified_count' => $patients->count()
        ]);
    }

    public function notifyDrugUpdated(User $patient, Prescription $prescription, Drug $drug): Notification
    {
        Log::info('🚨 === notifyDrugUpdated START ===', [
            'patient_id' => $patient->id,
            'drug_id' => $drug->id,
            'prescription_id' => $prescription->id,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);
        
        $notification = $this->createNotification(
            $patient,
            'عادي',
            'تم تعديل دواء في الحصة العلاجية',
            "تم تعديل جرعة أو تفاصيل دواء ({$drug->name}) في حصتك العلاجية."
        );
        
        Log::info('🚨 === notifyDrugUpdated END ===', [
            'notification_id' => $notification->id
        ]);
        
        return $notification;
    }

    public function notifySystem(User $patient, string $title, string $message, string $type = 'عادي'): Notification
    {
        Log::info('🚨 === notifySystem START ===', [
            'patient_id' => $patient->id,
            'title' => $title,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);
        
        $notification = $this->createNotification($patient, $type, $title, $message);
        
        Log::info('🚨 === notifySystem END ===');
        
        return $notification;
    }

    private function createNotification(User $patient, string $type, string $title, string $message): Notification
    {
        $backtrace = collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10))
            ->map(fn($t) => ($t['class'] ?? '') . '@' . ($t['function'] ?? ''))
            ->implode(' -> ');

        Log::info('🚨 === createNotification START ===', [
            'user_id' => $patient->id,
            'title' => $title,
            'message' => substr($message, 0, 50),
            'type' => $type,
            'caller' => $backtrace,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);
        
        $notification = Notification::create([
            'user_id' => $patient->id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'is_read' => false,
        ]);
        
        Log::info('🚨 Notification created in DB', ['notification_id' => $notification->id]);

        $this->sendPushIfPossible($patient, $title, $message);
        
        Log::info('🚨 === createNotification END ===', ['notification_id' => $notification->id]);
        
        return $notification;
    }

    private function sendPushIfPossible(User $patient, string $title, string $message): void
    {
        Log::info('🚨 === sendPushIfPossible START ===', [
            'user_id' => $patient->id,
            'title' => $title,
            'timestamp' => now()->format('Y-m-d H:i:s.u')
        ]);
        
        if (empty($patient->fcm_token)) {
            Log::info('🚨 No FCM token, skipping');
            return;
        }

        $data = [
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'collapse_key' => 'notification_' . $patient->id . '_' . time(), // ✅ لمنع التكرار في FCM
        ];

        $result = null;

        $hasV1Config = (string) config('services.fcm.project_id') !== ''
            && (string) config('services.fcm.service_account_json') !== '';

        $hasLegacyConfig = (string) config('services.fcm.server_key') !== '';

        Log::info('🚨 FCM Config check', [
            'has_v1_config' => $hasV1Config,
            'has_legacy_config' => $hasLegacyConfig
        ]);

        if ($hasV1Config) {
            Log::info('🚨 Using FCM v1');
            $result = $this->fcmV1->sendToToken($patient->fcm_token, $title, $message, $data);
            Log::info('FCM v1 send attempt', [
                'user_id' => $patient->id,
                'status' => $result['status'] ?? null,
                'name' => $result['body']['name'] ?? null,
            ]);
        } elseif ($hasLegacyConfig) {
            Log::info('🚨 Using FCM legacy');
            $result = $this->fcm->sendToToken($patient->fcm_token, $title, $message, $data);
            Log::info('FCM legacy send attempt', [
                'user_id' => $patient->id,
                'status' => $result['status'] ?? null,
                'message_id' => $result['body']['results'][0]['message_id'] ?? null,
            ]);
        } else {
            Log::warning('FCM not configured', [
                'user_id' => $patient->id,
                'has_v1_config' => $hasV1Config,
                'has_legacy_config' => $hasLegacyConfig,
            ]);

            return;
        }

        if (!($result['ok'] ?? false)) {
            Log::warning('FCM send failed', [
                'user_id' => $patient->id,
                'status' => $result['status'] ?? null,
                'body' => $result['body'] ?? null,
                'raw' => $result['raw'] ?? null,
                'error' => $result['error'] ?? null,
            ]);

            return;
        }

        Log::info('FCM send success', [
            'user_id' => $patient->id,
            'status' => $result['status'] ?? null,
            'body' => $result['body'] ?? null,
        ]);
        
        Log::info('🚨 === sendPushIfPossible END ===');
    }
}