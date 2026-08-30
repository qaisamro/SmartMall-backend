<?php

namespace App\Notifications;

use App\Channels\WebPushChannel;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmation extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(
        protected Order $order
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast', WebPushChannel::class];
        if ($notifiable->email) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mallName = $this->order->mall?->name_ar ?? 'المتجر';
        return (new MailMessage)
            ->subject("تأكيد الطلب #{$this->order->id} من {$mallName}")
            ->view('emails.order-confirmation', [
                'order' => $this->order,
                'user' => $notifiable,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        $actionUrl = '/orders/' . $this->order->id;
        if (method_exists($notifiable, 'hasRole')) {
            if ($notifiable->hasRole('delivery-person')) {
                $actionUrl = '/delivery';
            } elseif ($notifiable->hasRole('super-admin') || $notifiable->hasRole('order-tracker')) {
                $actionUrl = '/tracker';
            } elseif ($notifiable->hasRole('mall-owner') || $notifiable->hasRole('supermarket-owner')) {
                $actionUrl = '/owner/delivery';
            }
        }

        return [
            'order_id' => $this->order->id,
            'total_amount' => $this->order->total_amount,
            'mall_id' => $this->order->mall_id,
            'message' => 'طلب جديد #' . $this->order->id . ' بقيمة ' . $this->order->total_amount . ' ₪',
            'type' => 'order_confirmed',
            'action_url' => $actionUrl,
        ];
    }

    public function toWebPush($notifiable): array
    {
        return [
            'title' => 'طلب جديد',
            'body' => 'طلب جديد #' . $this->order->id . ' بقيمة ' . $this->order->total_amount . ' ₪',
            'url' => $this->toArray($notifiable)['action_url'] ?? '/orders/' . $this->order->id,
        ];
    }
}
