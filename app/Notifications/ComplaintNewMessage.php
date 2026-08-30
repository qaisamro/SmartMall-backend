<?php

namespace App\Notifications;

use App\Channels\WebPushChannel;
use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class ComplaintNewMessage extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(
        protected Complaint $complaint,
        protected string $senderName = '',
        protected string $preview = '',
        protected string $url = '/complaints'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', WebPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'complaint_id' => $this->complaint->id,
            'title' => $this->complaint->title,
            'status' => $this->complaint->status,
            'sender' => $this->senderName,
            'message' => 'رسالة جديدة بخصوص الشكوى: ' . $this->complaint->title . ($this->preview ? ' — ' . $this->preview : ''),
            'type' => 'complaint_new_message',
            'action_url' => $this->url,
        ];
    }

    public function toWebPush($notifiable): array
    {
        return [
            'title' => 'الشكاوى: ' . $this->complaint->title,
            'body' => ($this->senderName ? $this->senderName . ': ' : '') . ($this->preview ?: 'رسالة جديدة'),
            'url' => $this->url,
        ];
    }
}
