<?php

namespace App\Notifications;

use App\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class DeliveryStatusUpdated extends Notification implements ShouldBroadcast
{
    use Queueable;

    protected $order;
    protected $status;
    protected $actor;

    const STATUS_MAP = [
        'pending' => [
            'db_message' => 'طلب التوصيل #%s في انتظار قبول المول',
            'webpush_title' => 'طلب توصيل جديد',
            'webpush_body' => 'طلب التوصيل #%s في انتظار قبول المول',
        ],
        'preparing' => [
            'db_message' => 'صاحب المول وافق على طلب التوصيل #%s — جاري التجهيز',
            'webpush_title' => 'تم قبول الطلب من المول',
            'webpush_body' => 'صاحب المول وافق على طلب التوصيل #%s',
        ],
        'accepted' => [
            'db_message' => 'مندوب التوصيل قبول طلب التوصيل #%s',
            'webpush_title' => 'قبول طلب التوصيل',
            'webpush_body' => 'مندوب التوصيل قبول طلب التوصيل #%s',
        ],
        'delivering' => [
            'db_message' => 'مندوب التوصيل في الطريق بطلب التوصيل #%s',
            'webpush_title' => 'المندوب في الطريق إليك',
            'webpush_body' => 'مندوب التوصيل في الطريق إليك بطلب #%s',
        ],
        'delivered' => [
            'db_message' => 'تم توصيل طلب التوصيل #%s بنجاح',
            'webpush_title' => 'تم التوصيل بنجاح',
            'webpush_body' => 'تم توصيل طلب #%s بنجاح — شكراً لك',
        ],
        'failed' => [
            'db_message' => 'لم يتم توصيل طلب #%s — يرجى التواصل مع المول',
            'webpush_title' => 'فشل التوصيل',
            'webpush_body' => 'لم يتم توصيل طلب #%s — يرجى التواصل مع المول',
        ],
    ];

    const STATUS_MAP_PICKUP = [
        'pending' => [
            'db_message' => 'طلب الاستلام الشخصي #%s في انتظار قبول المتجر',
            'webpush_title' => 'طلب استلام شخصي جديد',
            'webpush_body' => 'طلب الاستلام الشخصي #%s في انتظار قبول المتجر',
        ],
        'preparing' => [
            'db_message' => 'المتجر وافق على طلب الاستلام الشخصي #%s — جاري التجهيز',
            'webpush_title' => 'تم قبول الطلب من المتجر',
            'webpush_body' => 'المتجر وافق على طلبك #%s — جاري التجهيز',
        ],
        'ready' => [
            'db_message' => 'طلبك جاهز للاستلام من المتجر #%s',
            'webpush_title' => 'طلبك جاهز للاستلام',
            'webpush_body' => 'طلبك #%s جاهز — يمكنك استلامه من المتجر',
        ],
        'delivered' => [
            'db_message' => 'تم استلام الطلب #%s بنجاح',
            'webpush_title' => 'تم الاستلام بنجاح',
            'webpush_body' => 'تم استلام طلب #%s بنجاح — شكراً لك',
        ],
        'failed' => [
            'db_message' => 'لم يتم تنفيذ طلب الاستلام #%s — يرجى التواصل مع المتجر',
            'webpush_title' => 'لم يتم تنفيذ الطلب',
            'webpush_body' => 'لم يتم تنفيذ طلب الاستلام #%s — يرجى التواصل مع المتجر',
        ],
    ];

    protected function statusMap(): array
    {
        $map = self::STATUS_MAP[$this->status] ?? self::STATUS_MAP['pending'];
        if ($this->order->delivery_method === 'pickup') {
            $map = self::STATUS_MAP_PICKUP[$this->status] ?? $map;
        }
        return $map;
    }

    public function __construct($order, $status = null, $actor = null)
    {
        $this->order = $order;
        $this->status = $status ?? $order->delivery_status;
        $this->actor = $actor;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', WebPushChannel::class];
    }

    protected function withPrepTime(string $message): string
    {
        if (!empty($this->order->preparation_time) && in_array($this->status, ['preparing', 'ready'], true)) {
            $message .= ' — المدة المتوقعة: ' . $this->order->preparation_time . ' دقيقة';
        }
        return $message;
    }

    public function toArray(object $notifiable): array
    {
        $map = $this->statusMap();
        $message = $this->withPrepTime(sprintf($map['db_message'], $this->order->pending_order_id ?? $this->order->id));

        return [
            'order_id' => $this->order->id,
            'message' => $message,
            'type' => 'delivery_status_updated',
            'delivery_status' => $this->status,
            'preparation_time' => $this->order->preparation_time,
            'action_url' => '/orders/' . $this->order->id,
        ];
    }

    public function toWebPush($notifiable): array
    {
        $map = $this->statusMap();
        return [
            'title' => $map['webpush_title'],
            'body' => $this->withPrepTime(sprintf($map['webpush_body'], $this->order->pending_order_id ?? $this->order->id)),
            'url' => '/orders/' . $this->order->id,
        ];
    }
}
