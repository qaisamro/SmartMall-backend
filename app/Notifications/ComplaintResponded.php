<?php

namespace App\Notifications;

use App\Channels\WebPushChannel;
use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class ComplaintResponded extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(
        protected Complaint $complaint
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
            'message' => 'تم الرد على شكواك: ' . $this->complaint->title,
            'type' => 'complaint_responded',
            'action_url' => '/complaints',
        ];
    }

    public function toWebPush($notifiable): array
    {
        return [
            'title' => 'رد على شكواك',
            'body' => 'تم الرد على شكواك: ' . $this->complaint->title,
            'url' => '/complaints',
        ];
    }
}
