<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PasswordOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $code,      // plaintext code to show in email
        public string $context,   // 'register' or 'password_reset'
        public int $ttlMinutes = 10
    ) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $appName = config('app.name');

        $subject = $this->context === 'register'
            ? __('🎉 :app — Set your password (OTP)', ['app' => $appName])
            : __('🔒 Password reset code (OTP)');

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.password-otp', [
                'user'       => $notifiable,
                'code'       => $this->code,
                'ttlMinutes' => $this->ttlMinutes,
                'context'    => $this->context,
                'appName'    => $appName,
            ]);
    }
}
