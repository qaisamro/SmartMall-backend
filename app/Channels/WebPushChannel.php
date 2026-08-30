<?php

namespace App\Channels;

use App\Services\PushNotificationService;
use Illuminate\Notifications\Notification;

class WebPushChannel
{
    public function send($notifiable, Notification $notification): void
    {
        if (!$notifiable->id) return;

        $data = $notification->toWebPush($notifiable);

        app(PushNotificationService::class)->sendToUser(
            $notifiable->id,
            $data['title'] ?? '',
            $data['body'] ?? '',
            $data['url'] ?? null,
        );
    }
}
