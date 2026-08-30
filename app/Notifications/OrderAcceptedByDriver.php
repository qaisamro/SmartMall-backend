<?php

namespace App\Notifications;

use App\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class OrderAcceptedByDriver extends Notification implements ShouldBroadcast
{
    use Queueable;

    protected $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', WebPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'message' => 'تم قبول طلب التوصيل #' . ($this->order->pending_order_id ?? $this->order->id) . ' من قبل مندوب التوصيل',
            'type' => 'delivery_accepted',
            'action_url' => '/orders/' . $this->order->id,
        ];
    }

    public function toWebPush($notifiable): array
    {
        return [
            'title' => 'قبول طلب توصيل',
            'body' => 'تم قبول طلب التوصيل #' . ($this->order->pending_order_id ?? $this->order->id) . ' من قبل مندوب التوصيل',
            'url' => '/orders/' . $this->order->id,
        ];
    }
}
