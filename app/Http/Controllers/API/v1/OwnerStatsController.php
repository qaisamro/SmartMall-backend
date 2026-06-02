<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\MallBranch;
use Illuminate\Http\Request;

class OwnerStatsController extends Controller
{
    public function index(Request $request)
    {
        $mallIds = $request->user()->malls()->pluck('id');

        return response()->json([
            'products_count' => Product::whereIn('mall_id', $mallIds)->count(),
            'daily_sales' => Order::whereIn('mall_id', $mallIds)
                ->whereDate('created_at', today())
                ->sum('total_amount'),
            'branches_count' => MallBranch::whereIn('mall_id', $mallIds)->count(),
            'staff_count' => 0, // Placeholder for now
        ]);
    }

    public function recentProducts(Request $request)
    {
        $mallIds = $request->user()->malls()->pluck('id');
        return response()->json(
            Product::whereIn('mall_id', $mallIds)
                ->latest()
                ->take(5)
                ->with('category')
                ->get()
        );
    }
}
