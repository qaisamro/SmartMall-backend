<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderConfirmation;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ProcessOrderNotifications
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected Order $order
    ) {}

    public function handle(WhatsAppService $whatsapp): void
    {
        try {
            $this->order->loadMissing(['items.product', 'mall.owner', 'user']);
        } catch (\Throwable $e) {
            Log::error('Failed to load order relations', ['order_id' => $this->order->id, 'error' => $e->getMessage()]);
        }

        // 1. Email confirmation to customer
        try {
            if ($this->order->user?->email) {
                $this->order->user->notify(new OrderConfirmation($this->order));
            }
        } catch (\Throwable $e) {
            Log::warning('Customer notification failed', ['order_id' => $this->order->id, 'error' => $e->getMessage()]);
        }

        // 2. Notify mall owner (only for delivery orders; in-mall is confirmed by the owner directly)
        try {
            if ($this->order->delivery_method !== 'in-mall') {
                $mallOwner = $this->order->mall?->owner;
                if ($mallOwner) {
                    $mallOwner->notify(new OrderConfirmation($this->order));
                    Log::info('Mall owner notified', ['order_id' => $this->order->id, 'owner_id' => $mallOwner->id]);
                } else {
                    Log::warning('Mall owner not found', ['order_id' => $this->order->id, 'mall_id' => $this->order->mall_id]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Mall owner notification failed', ['order_id' => $this->order->id, 'error' => $e->getMessage()]);
        }

        // 3. Notify admins
        try {
            $admins = User::role('super-admin')->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new OrderConfirmation($this->order));
            }
        } catch (\Throwable $e) {
            Log::warning('Admin notification failed', ['order_id' => $this->order->id, 'error' => $e->getMessage()]);
        }

        // 6. WhatsApp notifications — DISABLED
        // try {
        //     $whatsapp->sendOrderNotification($this->order);
        // } catch (\Throwable $e) {
        //     Log::warning('WhatsApp notification failed for order ' . $this->order->id, [
        //         'error' => $e->getMessage(),
        //     ]);
        // }

        // 7. Log activity
        try {
            \App\Helpers\ActivityLogger::log(
                'order_confirmed',
                'تم تأكيد الطلب #' . $this->order->id . ' بقيمة ' . $this->order->total_amount . ' ₪',
                $this->order,
                null,
                $this->order->mall_id,
                [
                    'delivery_method' => $this->order->delivery_method,
                    'total' => $this->order->total_amount,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Activity log failed', ['order_id' => $this->order->id, 'error' => $e->getMessage()]);
        }
    }
}
