<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderReadyForDelivery;
use App\Notifications\DeliveryStatusUpdated;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class OwnerDeliveryController extends Controller
{
    public function index(Request $request)
    {
        $mall = $request->user()->mall;
        if (!$mall) {
            return response()->json(['message' => 'لا يوجد مول مرتبط بحسابك'], 403);
        }

        // Pickup orders are always managed here; delivery orders only when delivery is enabled
        $methods = $mall->delivery_enabled ? ['delivery', 'pickup'] : ['pickup'];

        $query = Order::with('items.product', 'user:id,name,phone', 'mall:id,name_ar,name_en')
            ->where('mall_id', $mall->id)
            ->whereIn('delivery_method', $methods);

        if ($request->status && in_array($request->status, ['pending', 'preparing', 'accepted', 'delivering', 'delivered', 'failed'])) {
            $query->where('delivery_status', $request->status);
        }

        return response()->json($query->latest()->get());
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:accepted,ready,delivering,delivered,failed',
        ]);

        $mall = $request->user()->mall;
        if (!$mall) {
            return response()->json(['message' => 'لا يوجد مول مرتبط بحسابك'], 403);
        }

        $order = Order::where('id', $id)
            ->where('mall_id', $mall->id)
            ->whereIn('delivery_method', ['delivery', 'pickup'])
            ->firstOrFail();

        if ($order->delivery_method === 'delivery' && !$mall->delivery_enabled) {
            return response()->json(['message' => 'التوصيل غير مفعل'], 403);
        }

        $updateData = ['delivery_status' => $request->status];

        if ($request->status === 'delivered') {
            $updateData['delivered_at'] = now();
            $updateData['status'] = 'completed';
        }

        if ($request->status === 'accepted') {
            $updateData['delivery_accepted_at'] = now();
        }

        $order->update($updateData);

        // Notify customer of status update
        try {
            if ($order->user) {
                $order->user->notify(new DeliveryStatusUpdated($order));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Customer notification failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'تم تحديث الحالة', 'order' => $order->load('items.product', 'user:id,name,phone')]);
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'preparation_time' => 'required|integer|min:1|max:480',
        ]);

        $mall = $request->user()->mall;
        if (!$mall) {
            return response()->json(['message' => 'لا يوجد مول مرتبط بحسابك'], 403);
        }

        $order = Order::where('id', $id)
            ->where('mall_id', $mall->id)
            ->whereIn('delivery_method', ['delivery', 'pickup'])
            ->whereNull('approved_at')
            ->firstOrFail();

        if ($order->delivery_method === 'delivery' && !$mall->delivery_enabled) {
            return response()->json(['message' => 'التوصيل غير مفعل'], 403);
        }


        $order->update([
            'preparation_time' => $request->preparation_time,
            'approved_at'      => now(),
            'delivery_status'  => 'preparing',
        ]);

        $order->loadMissing('items.product', 'mall', 'user');

        try {
            // Notify delivery persons (not needed for personal pickup)
            if ($order->delivery_method !== 'pickup') {
                $drivers = User::role('delivery-person')->where('is_active', true)->get();
                if ($drivers->isNotEmpty()) {
                    Notification::send($drivers, new OrderReadyForDelivery($order));
                }
            }

            // Notify order trackers
            $trackers = User::role('order-tracker')->get();
            if ($trackers->isNotEmpty()) {
                Notification::send($trackers, new OrderReadyForDelivery($order));
            }

            // Notify customer that mall owner accepted the order
            if ($order->user) {
                $order->user->notify(new DeliveryStatusUpdated($order, 'preparing'));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Approve notifications failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        // WhatsApp notifications to delivery + trackers — DISABLED
        // try {
        //     app(WhatsAppService::class)->sendOrderReadyNotification($order);
        // } catch (\Throwable $e) {
        //     \Illuminate\Support\Facades\Log::warning('WhatsApp ready notification failed', ['error' => $e->getMessage()]);
        // }

        return response()->json([
            'message' => 'تم قبول الطلب، وتم إشعار فريق التوصيل',
            'order' => $order,
        ]);
    }
}
