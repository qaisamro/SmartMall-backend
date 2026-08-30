<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $instanceId;
    protected string $token;

    public function __construct()
    {
        $this->instanceId = config('services.ultramsg.instance_id', '');
        $this->token = config('services.ultramsg.token', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->instanceId) && !empty($this->token);
    }

    public function sendMessage(string $to, string $body): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('WhatsAppService: not configured, skipping message', ['to' => $to]);
            return false;
        }

        $to = $this->normalizeNumber($to);
        if (!$to) return false;

        try {
            $response = Http::timeout(15)->post('https://api.ultramsg.com/' . $this->instanceId . '/messages/chat', [
                'token' => $this->token,
                'to' => $to,
                'body' => $body,
            ]);

            if ($response->successful()) {
                Log::info('WhatsApp sent successfully', ['to' => $to]);
                return true;
            }

            Log::error('WhatsApp send failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('WhatsApp exception', ['error' => $e->getMessage(), 'to' => $to]);
            return false;
        }
    }

    public function sendOrderNotification(Order $order): void
    {
        $customer = $order->user;
        $mall = $order->mall;
        $items = $order->items;

        $itemsList = '';
        foreach ($items as $item) {
            $name = $item->product?->name_ar ?: $item->product?->name_en ?: 'منتج #' . $item->product_id;
            $total = $item->price_at_sale * $item->quantity;
            $itemsList .= "• {$name} × {$item->quantity} = {$total} ₪\n";
        }

        $mallName = $mall?->name_ar ?: $mall?->name_en ?: 'المول';
        $deliveryMethod = $order->delivery_method === 'delivery' ? 'توصيل منزلي' : 'استلام من المول';
        $total = number_format($order->total_amount, 2);

        $message = "🛒 *طلب جديد #{$order->id}*\n"
            . "─────────────────\n"
            . "*العميل:* {$customer?->name}\n"
            . "*الهاتف:* {$customer?->phone}\n"
            . "*المول:* {$mallName}\n"
            . "*طريقة التوصيل:* {$deliveryMethod}\n"
            . ($order->delivery_address ? "*العنوان:* {$order->delivery_address}\n" : '')
            . "*الإجمالي:* {$total} ₪\n"
            . "─────────────────\n"
            . "*المنتجات:*\n{$itemsList}\n"
            . "📅 " . now()->format('Y-m-d H:i');

        // Notify admin
        $admins = User::role('super-admin')->whereNotNull('whatsapp')->get();
        foreach ($admins as $admin) {
            $this->sendMessage($admin->whatsapp, $message);
        }

        // Notify mall owner
        $mallOwner = $mall?->owner;
        if ($mallOwner && $mallOwner->whatsapp) {
            $this->sendMessage($mallOwner->whatsapp, $message);
        }

        // Notify customer
        if ($customer && $customer->whatsapp) {
            $customerMsg = "✅ *تم تأكيد طلبك #{$order->id}*\n"
                . "─────────────────\n"
                . "*المول:* {$mallName}\n"
                . "*طريقة التوصيل:* {$deliveryMethod}\n"
                . "*الإجمالي:* {$total} ₪\n"
                . "─────────────────\n"
                . "*المنتجات:*\n{$itemsList}\n"
                . "📅 " . now()->format('Y-m-d H:i');
            $this->sendMessage($customer->whatsapp, $customerMsg);
        }
    }

    public function sendOrderReadyNotification(Order $order): void
    {
        $customer = $order->user;
        $mall = $order->mall;
        $items = $order->items;
        $prepTime = $order->preparation_time ?? 30;

        $itemsList = '';
        foreach ($items as $item) {
            $name = $item->product?->name_ar ?: $item->product?->name_en ?: 'منتج #' . $item->product_id;
            $total = $item->price_at_sale * $item->quantity;
            $itemsList .= "• {$name} × {$item->quantity} = {$total} ₪\n";
        }

        $mallName = $mall?->name_ar ?: $mall?->name_en ?: 'المول';
        $total = number_format($order->total_amount, 2);

        $message = "✅ *الطلب #{$order->id} جاهز للتوصيل*\n"
            . "─────────────────\n"
            . "*العميل:* {$customer?->name}\n"
            . "*الهاتف:* {$customer?->phone}\n"
            . "*المول:* {$mallName}\n"
            . "*وقت التجهيز:* {$prepTime} دقيقة\n"
            . "*الإجمالي:* {$total} ₪\n"
            . "─────────────────\n"
            . "*المنتجات:*\n{$itemsList}\n"
            . "📅 " . now()->format('Y-m-d H:i');

        // Notify delivery persons
        $drivers = User::role('delivery-person')->whereNotNull('whatsapp')->where('is_active', true)->get();
        foreach ($drivers as $driver) {
            $this->sendMessage($driver->whatsapp, $message);
        }

        // Notify order trackers
        $trackers = User::role('order-tracker')->whereNotNull('whatsapp')->get();
        foreach ($trackers as $tracker) {
            $this->sendMessage($tracker->whatsapp, $message);
        }
    }

    protected function normalizeNumber(string $number): ?string
    {
        $number = preg_replace('/[^0-9+]/', '', $number);
        if (strlen($number) < 8) return null;
        if (!str_starts_with($number, '+')) {
            $number = '+' . $number;
        }
        return $number;
    }
}
