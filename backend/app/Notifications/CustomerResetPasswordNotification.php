<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('services.frontend_url'), '/')
            .'/reset-password?'
            .http_build_query([
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);

        $expiresInMinutes = (int) config('auth.passwords.customers.expire', 60);

        return (new MailMessage)
            ->subject('Đặt lại mật khẩu TienProSport')
            ->greeting("Chào {$notifiable->ten_kh}!")
            ->line('Chúng tôi đã nhận được yêu cầu đặt lại mật khẩu cho tài khoản TienProSport của bạn.')
            ->action('Đặt lại mật khẩu', $url)
            ->line("Liên kết này có hiệu lực trong {$expiresInMinutes} phút và chỉ sử dụng được một lần.")
            ->line('Nếu bạn không yêu cầu đặt lại mật khẩu, bạn có thể bỏ qua email này.');
    }
}
