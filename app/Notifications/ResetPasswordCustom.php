<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordCustom extends Notification
{
    /**
     * Password broker token
     */
    public string $token;

    /**
     * Context for the email:
     *  - 'register' → welcome/set password email
     *  - 'reset' (default) → forgot password reset email
     */
    public string $context;

    public function __construct(string $token, string $context = 'reset')
    {
        $this->token   = $token;
        $this->context = $context;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Build the frontend (or backend fallback) reset URL with mode
     */
    protected function buildResetUrl($notifiable): string
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $email    = $notifiable->getEmailForPasswordReset();
        $mode     = $this->context === 'register' ? 'register' : 'forgot';

        $query = http_build_query([
            'token' => $this->token,
            'email' => $email,
            'mode'  => $mode, // <-- lets the UI show dynamic title/subtitle
        ]);

        if (!empty($frontend)) {
            return "{$frontend}/reset-password?{$query}";
        }

        // Backend route fallback (if you aren't using a separate SPA URL)
        return url(route('password.reset', [
            'token' => $this->token,
            'email' => $email,
            'mode'  => $mode,
        ], false));
    }

    public function toMail($notifiable)
    {
        $url     = $this->buildResetUrl($notifiable);
        $appName = config('app.name');

        if ($this->context === 'register') {
            // Registration flow: different subject + blade view
            return (new MailMessage)
                ->subject(__('🎉 ស្វាគមន៍មកកាន់ :app — កំណត់ពាក្យសម្ងាត់', ['app' => $appName]))
                ->markdown('emails.register-set-password', [
                    'url'     => $url,
                    'user'    => $notifiable,
                    'appName' => $appName,
                ]);
        }
        // Default forgot-password flow
        return (new MailMessage)
            ->subject(__('🔒 សំណើរសុំកំណត់ពាក្យសម្ងាត់ថ្មី | Password Reset Notification'))
            ->markdown('emails.password-reset', [
                'url'     => $url,
                'user'    => $notifiable,
                'appName' => $appName,
            ]);
    }

    public function toArray($notifiable)
    {
        return [];
    }
}
