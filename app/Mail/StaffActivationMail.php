<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StaffActivationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $url;
    public $name;

    public function __construct($token, $email, $name)
    {
        $this->name = $name;
        
        // 👇 استخدام رابط Frontend الصحيح
        $frontendUrl = config('app.frontend_url', 'http://localhost:8000');

        $this->url = $frontendUrl . "/set-password?token=" . urlencode($token) . "&email=" . urlencode($email);
    }

    public function build()
    {
        return $this->subject('تفعيل حسابك في نظام حُصتي')
                    ->view('emails.staff_activation');
    }
}
