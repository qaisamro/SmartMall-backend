<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Notifications\DeliveryStatusUpdated;
use App\Notifications\OrderAcceptedByDriver;
use App\Notifications\OrderReadyForDelivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class DeliveryController extends Controller
{
    public function index()
    {
        $orders = Order::with(['mall', 'items.product', 'user:id,name,phone,whatsapp', 'deliveryZone'])
            ->where('delivery_method', 'delivery')
            ->where('delivery_status', 'preparing')
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function decline($id)
    {
        $order = Order::where('id', $id)
            ->where('delivery_method', 'delivery')
            ->whereIn('delivery_status', ['accepted', 'delivering'])
            ->where('delivery_user_id', auth()->id())
            ->firstOrFail();

        // Return the order to the available pool (its natural state in "new orders")
        $order->update([
            'delivery_status'      => 'preparing',
            'delivery_user_id'     => null,
            'delivery_accepted_at' => null,
        ]);

        // Notify the delivery team and trackers like the first time the order became available
        $order->loadMissing('mall', 'user', 'items.product');
        try {
            $drivers = User::role('delivery-person')->where('is_active', true)->where('id', '!=', auth()->id())->get();
            if ($drivers->isNotEmpty()) {
                Notification::send($drivers, new OrderReadyForDelivery($order));
            }

            $trackers = User::role('order-tracker')->get();
            if ($trackers->isNotEmpty()) {
                Notification::send($trackers, new OrderReadyForDelivery($order));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Re-dispatch notification failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'تم إرجاع الطلب إلى قائمة الطلبات الجديدة']);
    }

    public function accept($id)
    {
        $order = Order::findOrFail($id);

        if ($order->delivery_status !== 'preparing') {
            return response()->json(['message' => 'Order already taken or not available'], 422);
        }

        $driver = auth()->user();

        $order->update([
            'delivery_user_id' => $driver->id,
            'delivery_status' => 'accepted',
            'delivery_accepted_at' => now(),
        ]);

        // Notify customer that their order is accepted
        try {
            if ($order->user) {
                $order->user->notify(new \App\Notifications\OrderAcceptedByDriver($order));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Customer notification failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Order accepted successfully', 'order' => $order]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:delivering,delivered,failed'
        ]);

        $order = Order::where('id', $id)
            ->where('delivery_user_id', auth()->id())
            ->firstOrFail();

        $updateData = ['delivery_status' => $request->status];
        if ($request->status === 'delivered') {
            $updateData['delivered_at'] = now();
            $updateData['status'] = 'completed';
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

        return response()->json(['message' => 'Status updated successfully', 'order' => $order]);
    }

    public function myDeliveries()
    {
        $orders = Order::with(['mall', 'items.product', 'user:id,name,phone,whatsapp', 'deliveryZone'])
            ->where('delivery_user_id', auth()->id())
            ->whereIn('delivery_status', ['accepted', 'delivering'])
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function accepted(Request $request)
    {
        $query = Order::with(['mall', 'items.product', 'user:id,name,phone,whatsapp', 'deliveryZone'])
            ->where('delivery_user_id', auth()->id())
            ->whereNotNull('delivery_accepted_at');

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $orders = $query->latest()->get();
        return response()->json($orders);
    }

    public function history(Request $request)
    {
        $query = Order::with(['mall', 'items.product', 'user:id,name,phone,whatsapp', 'deliveryZone'])
            ->where('delivery_user_id', auth()->id())
            ->where('delivery_status', 'delivered');

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $orders = $query->latest()->get();
        return response()->json($orders);
    }
}
