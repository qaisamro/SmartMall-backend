<?php

namespace App\Notifications;

use App\Channels\WebPushChannel;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class OrderReadyForDelivery extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(
        protected Order $order
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', WebPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $prepTime = $this->order->preparation_time;
        $mallName = $this->order->mall?->name_ar ?? $this->order->mall?->name_en ?? 'المول';

        return [
            'order_id'         => $this->order->id,
            'mall_name'        => $mallName,
            'customer_name'    => $this->order->user?->name ?? 'زبون',
            'customer_phone'   => $this->order->user?->phone ?? '',
            'total_amount'     => $this->order->total_amount,
            'preparation_time' => $prepTime,
            'message'          => "الطلب #{$this->order->id} جاهز للتوصيل من {$mallName} — مدة التجهيز {$prepTime} دقيقة",
            'type'             => 'order_ready_for_delivery',
            'action_url'       => $notifiable->hasRole('delivery-person') ? '/delivery' : '/tracker',
        ];
    }

    public function toWebPush($notifiable): array
    {
        return [
            'title' => 'طلب جاهز للتوصيل',
            'body'  => 'الطلب #' . $this->order->id . ' من ' . ($this->order->mall?->name_ar ?? 'المول') . ' — جاهز للتوصيل',
            'url'   => '/delivery',
        ];
    }
}
