<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Mall;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class OrderTrackerController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['mall', 'mall.owner', 'items.product', 'user', 'deliveryPerson']);

        // Daily archiving: 'today' (default) shows only current-day orders,
        // 'archive' shows everything before today, 'all' shows everything.
        $view = $request->input('view', 'today');
        if ($view === 'today') {
            $query->whereDate('created_at', now()->toDateString());
        } elseif ($view === 'archive') {
            $query->whereDate('created_at', '<', now()->toDateString());
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }
        if ($request->filled('delivery_person_id')) {
            $query->where('delivery_user_id', $request->delivery_person_id);
        }

        $orders = $query->latest()->get();
        return response()->json($orders);
    }

    public function stats()
    {
        $stats = [
            'total_delivery_sales' => Order::where('delivery_method', 'delivery')->where('status', 'completed')->sum('total_amount'),
            'pending_count' => Order::where('delivery_status', 'pending')->count(),
            'preparing_count' => Order::where('delivery_status', 'preparing')->count(),
            'active_count' => Order::whereIn('delivery_status', ['accepted', 'delivering'])->count(),
            'delivered_count' => Order::where('delivery_status', 'delivered')->count(),
        ];

        return response()->json($stats);
    }

    public function deliveryPersonList()
    {
        $users = User::role('delivery-person')->get(['id', 'name']);
        return response()->json($users);
    }

    public function deliveryPersonDetail($id)
    {
        $user = User::withCount(['orders as completed_deliveries' => function ($q) {
            $q->where('delivery_status', 'delivered');
        }])->findOrFail($id);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'completed_deliveries' => $user->completed_deliveries,
            'created_at' => $user->created_at,
        ]);
    }

    public function malls()
    {
        $malls = Mall::orderBy('name_ar')->get(['id', 'name_ar', 'name_en', 'type', 'location_arabic']);
        return response()->json($malls);
    }

    public function search(Request $request)
    {
        $query = Order::with(['mall:id,name_ar,name_en,type,contact_phone,owner_id', 'mall.owner:id,name,phone,whatsapp', 'items.product', 'user:id,name,phone,whatsapp', 'deliveryPerson:id,name,phone,whatsapp']);

        if ($request->filled('order_id')) {
            $searchTerm = $request->order_id;
            $searchTerm = preg_replace('/^ORD-/i', '', $searchTerm);
            $query->where(function ($q) use ($searchTerm) {
                $q->where('id', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('pending_order_id', 'LIKE', "%{$searchTerm}%");
            });
        }

        if ($request->filled('mall_id')) {
            $query->where('mall_id', $request->mall_id);
        }

        if ($request->filled('delivery_method')) {
            $query->where('delivery_method', $request->delivery_method);
        }

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
