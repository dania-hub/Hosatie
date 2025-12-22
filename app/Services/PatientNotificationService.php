<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\Notification;
use App\Models\PatientTransferRequest;
use App\Models\Prescription;
use App\Models\Drug;
use App\Models\User;

class PatientNotificationService
{
    public function notifyComplaintReplied(User $patient, Complaint $complaint): Notification
    {
        return $this->createNotification(
            $patient,
            'عادي',
            'تم الرد على شكواك',
            'تمت مراجعة الشكوى والرد عليها.'
        );
    }

    public function notifyTransferApproved(User $patient, PatientTransferRequest $request): Notification
    {
        return $this->createNotification(
            $patient,
            'عادي',
            'تمت الموافقة على طلب النقل',
            'تمت الموافقة على طلب نقلك إلى المستشفى الجديد.'
        );
    }

    public function notifyTransferRejected(User $patient, PatientTransferRequest $request): Notification
    {
        return $this->createNotification(
            $patient,
            'عادي',
            'تم رفض طلب النقل',
            'تم رفض طلب نقلك. يمكنك مراجعة الإدارة لمزيد من التفاصيل.'
        );
    }

    public function notifyDrugAssigned(User $patient, Prescription $prescription, Drug $drug): Notification
    {
        return $this->createNotification(
            $patient,
            'عادي',
            'تم إضافة دواء جديد',
            'قام الطبيب بإضافة دواء جديد إلى حصتك العلاجية.'
        );
    }

    public function notifyDrugDeleted(User $patient, Prescription $prescription, Drug $drug): Notification
    {
        return $this->createNotification(
            $patient,
            'عادي',
            'تم حذف دواء من الحصة العلاجية',
            'قام الطبيب بحذف دواء من حصتك العلاجية.'
        );
    }

    public function notifyDrugUpdated(User $patient, Prescription $prescription, Drug $drug): Notification
    {
        return $this->createNotification(
            $patient,
            'عادي',
            'تم تعديل دواء في الحصة العلاجية',
            'قام الطبيب بتعديل جرعة أو تفاصيل دواء في حصتك العلاجية.'
        );
    }

    public function notifySystem(User $patient, string $title, string $message, string $type = 'عادي'): Notification
    {
        return $this->createNotification($patient, $type, $title, $message);
    }

    private function createNotification(User $patient, string $type, string $title, string $message): Notification
    {
        $notification = Notification::create([
            'user_id' => $patient->id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'is_read' => false,
        ]);

        $this->sendPushIfPossible($patient, $title, $message);

        return $notification;
    }

    private function sendPushIfPossible(User $patient, string $title, string $message): void
    {
        if (empty($patient->fcm_token)) {
            return;
        }

        // Place to dispatch a job or call external push gateway.
    }
}
