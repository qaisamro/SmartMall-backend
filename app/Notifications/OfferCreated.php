<?php

namespace App\Notifications;

use App\Channels\WebPushChannel;
use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class OfferCreated extends Notification implements ShouldBroadcast
{
    use Queueable;

    protected Offer $offer;

    public function __construct(Offer $offer)
    {
        $this->offer = $offer;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', WebPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $mallName = $this->offer->mall?->name_ar ?? $this->offer->mall?->name_en ?? 'المتجر';

        return [
            'offer_id' => $this->offer->id,
            'mall_id' => $this->offer->mall_id,
            'mall_name' => $mallName,
            'offer_title' => $this->offer->title_ar ?? $this->offer->title_en,
            'offer_type' => $this->offer->type,
            'message' => "عرض جديد من {$mallName}: {$this->offer->title_ar}",
            'type' => 'offer_created',
            'action_url' => '/offers?offer_id=' . $this->offer->id,
        ];
    }

    public function toWebPush($notifiable): array
    {
        $mallName = $this->offer->mall?->name_ar ?? 'المتجر';
        return [
            'title' => "عرض جديد من {$mallName}",
            'body' => $this->offer->title_ar ?? $this->offer->title_en,
            'url' => '/offers?offer_id=' . $this->offer->id,
        ];
    }
}