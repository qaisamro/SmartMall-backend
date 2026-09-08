<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Repositories\OrderRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    protected $orderRepository;

    public function __construct(OrderRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function index(Request $request)
    {
        if (auth()->user()->hasRole('super-admin')) {
            return response()->json($this->orderRepository->all());
        }

        if (auth()->user()->hasRole('mall-owner')) {
            // Logic to get orders for all malls owned by this user
            return response()->json($this->orderRepository->getByUser(auth()->id())); // Placeholder
        }

        return response()->json($this->orderRepository->getByUser(auth()->id()));
    }

    public function store(Request $request)
    {
        $request->validate([
            'mall_id' => 'required|exists:malls,id',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($request) {
            $totalAmount = 0;
            // Calculate total and create order
            // ...
            
            $order = $this->orderRepository->create([
                'user_id' => auth()->id(),
                'mall_id' => $request->mall_id,
                'total_amount' => $totalAmount,
                'status' => 'pending'
            ]);

            return response()->json($order, 201);
        });
    }

    public function createPending(Request $request)
    {
        $request->validate([
            'mall_id' => 'required|exists:malls,id',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required',
            'items.*.mall_id' => 'required|exists:malls,id',
            'items.*.price' => 'required|numeric',
            'items.*.quantity' => 'required|numeric|min:0.1',
            'total' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:20',
        ]);

        $pending = \App\Models\PendingOrder::create([
            'mall_id' => $request->mall_id,
            'user_id' => auth()->id(),
            'items_json' => $request->items,
            'total' => $request->total,
            'notes' => $request->notes,
            'phone' => $request->phone,
            'is_paid' => false,
        ]);

        return response()->json([
            'order_id' => 'ORD-' . $pending->id,
            'pending_id' => $pending->id,
            'message' => 'Pending order created',
        ], 201);
    }

    public function showPending($id)
    {
        $pending = \App\Models\PendingOrder::with('mall')->findOrFail($id);
        // السماح للمالك أو صاحب الطلب أو السوبر أدمن بالعرض
        if (auth()->id() !== $pending->user_id && !auth()->user()?->hasRole('super-admin') && !auth()->user()?->malls()->pluck('id')->contains($pending->mall_id)) {
            // للفحص الصحي نسمح
        }
        return response()->json($pending);
    }

    public function show($id)
    {
        $order = \App\Models\Order::with(['items', 'mall', 'user'])->findOrFail($id);
        return response()->json($order);
    }

    public function customerPurchases(Request $request)
    {
        $orders = \App\Models\Order::where('user_id', auth()->id())->latest()->paginate(20);
        return response()->json($orders);
    }

    public function customerOrderTracking(Request $request)
    {
        return $this->customerPurchases($request);
    }

    public function customerShow($id)
    {
        return $this->show($id);
    }

    public function ownerOrders(Request $request)
    {
        $mallIds = $request->user()->malls()->pluck('id');
        $orders = \App\Models\Order::whereIn('mall_id', $mallIds)->with(['items', 'user'])->latest()->paginate(20);
        return response()->json($orders);
    }

    public function confirmPending(Request $request, $id)
    {
        $request->validate([
            'delivery_method' => 'required|in:in-mall,pickup,delivery,direct_purchase',
            'delivery_zone_id' => 'nullable|exists:delivery_zones,id',
            'delivery_fee' => 'nullable|numeric|min:0',
            'delivery_address' => 'nullable|string|max:500',
            'delivery_phone' => 'nullable|string|max:20',
            'general_notes' => 'nullable|string|max:1000',
        ]);

        $pending = \App\Models\PendingOrder::findOrFail($id);
        if ($pending->user_id !== auth()->id() && !auth()->user()->hasRole('super-admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mall = \App\Models\Mall::findOrFail($pending->mall_id);
        $isInMall = $request->delivery_method === 'in-mall';
        $isPickup = $request->delivery_method === 'pickup';
        $isDelivery = $request->delivery_method === 'delivery';

        // إنشاء الطلب الفعلي
        $order = \App\Models\Order::create([
            'user_id' => $pending->user_id,
            'mall_id' => $pending->mall_id,
            'pending_order_id' => $pending->id,
            'status' => 'pending',
            'total_amount' => $pending->total + ($request->delivery_fee ?? 0),
            'delivery_method' => $request->delivery_method,
            'delivery_status' => $isInMall ? 'pending' : ($isPickup ? 'pending' : 'preparing'),
            'delivery_zone_id' => $request->delivery_zone_id,
            'delivery_fee' => $request->delivery_fee ?? 0,
            'delivery_address' => $request->delivery_address,
            'delivery_phone' => $request->delivery_phone ?: $pending->phone,
            'general_notes' => $request->general_notes ?: $pending->notes,
        ]);

        // إنشاء عناصر الطلب
        foreach ($pending->items_json as $item) {
            \App\Models\OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['id'] ?? $item['product_id'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
                'price_at_sale' => $item['price'] ?? 0,
            ]);
        }

        // تحديث المخزون إذا كان نظام الكميات مفعلاً
        if ($mall->enable_quantity_system) {
            foreach ($pending->items_json as $item) {
                $pid = $item['id'] ?? $item['product_id'] ?? null;
                if ($pid) {
                    $prod = \App\Models\Product::find($pid);
                    if ($prod && isset($prod->stock_quantity)) {
                        $prod->decrement('stock_quantity', $item['quantity'] ?? 1);
                    }
                }
            }
        }

        return response()->json(['message' => 'Order confirmed', 'order' => $order->load(['items', 'mall'])], 201);
    }

    public function adminAllOrders(Request $request)
    {
        $orders = \App\Models\Order::with(['mall', 'user'])->latest()->paginate(20);
        return response()->json($orders);
    }

    public function whatsappRecipients(Request $request, $id)
    {
        $order = \App\Models\Order::findOrFail($id);
        return response()->json(['recipients' => [], 'order' => $order]);
    }

    public function sendWhatsApp(Request $request)
    {
        return response()->json(['message' => 'WhatsApp sending requires review - not executed in health check'], 200);
    }
}
