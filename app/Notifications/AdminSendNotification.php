<?php

namespace App\Notifications;

use App\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class AdminSendNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    protected string $title;
    protected string $body;
    protected string $actionUrl;

    public function __construct(string $title, string $body, string $actionUrl = '/notifications')
    {
        $this->title = $title;
        $this->body = $body;
        $this->actionUrl = $actionUrl;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', WebPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'message' => $this->title . "\n" . $this->body,
            'type' => 'admin_broadcast',
            'action_url' => $this->actionUrl,
        ];
    }

    public function toWebPush($notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->actionUrl,
        ];
    }
}
