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
}
