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
        // This URL points to your Frontend React Dashboard
        $this->url = "http://localhost:3000/set-password?token=" . $token . "&email=" . urlencode($email);
    }

    public function build()
    {
        return $this->subject('Welcome to Hosatie - Activate Your Account')
                    ->view('emails.staff_activation'); // We will create this view next
    }
}
