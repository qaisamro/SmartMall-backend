<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class PasswordResetNotification extends Notification
{
    use Queueable;

    public string $token;
    public string $email;

    public function __construct(string $token, string $email)
    {
        $this->token = $token;
        $this->email = $email;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $resetUrl = config('app.frontend_url') . '/reset-password?token=' . urlencode($this->token) . '&email=' . urlencode($this->email);

        return (new MailMessage)
            ->from('info@samrtmall.cloud', 'SmartMall')
            ->subject('🔐 إعادة تعيين كلمة المرور - SmartMall')
            ->view('emails.password-reset', [
                'user'     => $notifiable,
                'resetUrl' => $resetUrl,
                'logoUrl'  => asset('images/logo.png'),
            ]);
    }
}
