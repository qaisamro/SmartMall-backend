<?php

namespace App\Notifications;

use App\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class OrderDeliveryRequested extends Notification implements ShouldBroadcast
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
        $items = $this->order->items->map(function($item) {
            return $item->product ? $item->product->name_ar : 'منتج غير معروف';
        })->implode(', ');

        return [
            'order_id' => $this->order->id,
            'mall_name' => $this->order->mall->name_ar ?? $this->order->mall->name_en,
            'customer_name' => $this->order->user?->name ?? 'زبون',
            'customer_phone' => $this->order->user?->phone ?? 'غير متاح',
            'items_summary' => $items,
            'total' => $this->order->total_amount,
            'message' => 'طلب توصيل جديد بقيمة ' . $this->order->total_amount . ' ₪ للمول: ' . ($this->order->mall?->name_ar ?? ''),
            'type' => 'delivery_requested',
            'action_url' => '/delivery?tab=pending&order_id=' . $this->order->id,
        ];
    }

    public function toWebPush($notifiable): array
    {
        return [
            'title' => 'طلب توصيل جديد',
            'body' => 'طلب توصيل بقيمة ' . $this->order->total_amount . ' ₪ للمول: ' . ($this->order->mall?->name_ar ?? ''),
            'url' => '/delivery?tab=pending&order_id=' . $this->order->id,
        ];
    }
}
