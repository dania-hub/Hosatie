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
        
        $notification = $this->createNotification(
            $patient,
            'عادي',
            'تمت الموافقة على طلب النقل',
            'تمت الموافقة على طلب نقلك إلى المستشفى الجديد.'
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
        Log::info('🚨 === createNotification START ===', [
            'user_id' => $patient->id,
            'title' => $title,
            'type' => $type,
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