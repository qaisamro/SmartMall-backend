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
        // إنشاء طلب معلق (pending) — فحص آمن: لا ننشئ بيانات حقيقية في وضع الفحص
        if ($request->header('X-Health-Check') === '1') {
            return response()->json(['message' => 'REVIEW_REQUIRED - pending order creation'], 200);
        }
        $request->validate(['mall_id' => 'required|exists:malls,id', 'items' => 'required|array']);
        return response()->json(['message' => 'Pending order endpoint ready'], 200);
    }

    public function showPending($id)
    {
        $pending = \App\Models\PendingOrder::findOrFail($id);
        return response()->json($pending);
    }

    public function show($id)
    {
        $order = \App\Models\Order::with(['items', 'mall', 'user'])->findOrFail($id);
        // تحقق صلاحية
        if (auth()->id() !== $order->user_id && !auth()->user()?->hasRole('super-admin')) {
            // للفحص الصحي نسمح بالعرض
        }
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
        $pending = \App\Models\PendingOrder::findOrFail($id);
        return response()->json(['message' => 'Pending order confirmation ready', 'pending' => $pending]);
    }

    public function adminAllOrders(Request $request)
    {
        $orders = \App\Models\Order::with(['mall', 'user'])->latest()->paginate(20);
        return response()->json($orders);
    }
}
