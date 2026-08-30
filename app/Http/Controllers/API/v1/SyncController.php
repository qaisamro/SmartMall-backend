<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\SyncCursor;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\ReturnProduct;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function status(Request $request)
    {
        $mallId = $this->getMallId($request);

        $cursors = SyncCursor::all()->keyBy('entity_type');

        $counts = [
            'orders' => Order::when($mallId, fn($q) => $q->where('mall_id', $mallId))
                ->whereIn('status', ['completed', 'paid'])->count(),
            'products' => Product::when($mallId, fn($q) => $q->where('mall_id', $mallId))->count(),
            'customers' => User::whereHas('roles', fn($q) => $q->where('name', 'customer'))->count(),
            'returns' => ReturnProduct::when($mallId, fn($q) => $q->where('mall_id', $mallId))->count(),
        ];

        return response()->json([
            'cursors' => [
                'orders' => $cursors->get('orders')?->last_cursor?->toIso8601String(),
                'products' => $cursors->get('products')?->last_cursor?->toIso8601String(),
                'customers' => $cursors->get('customers')?->last_cursor?->toIso8601String(),
                'returns' => $cursors->get('returns')?->last_cursor?->toIso8601String(),
            ],
            'counts' => $counts,
            'has_more' => true,
        ]);
    }

    public function incremental(Request $request)
    {
        $request->validate([
            'type' => 'required|in:orders,products,customers,returns',
            'cursor' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:500',
        ]);

        $type = $request->type;
        $cursor = $request->cursor;
        $limit = $request->limit ?? 100;
        $mallId = $this->getMallId($request);

        $data = match ($type) {
            'orders' => $this->syncOrders($cursor, $limit, $mallId),
            'products' => $this->syncProducts($cursor, $limit, $mallId),
            'customers' => $this->syncCustomers($cursor, $limit),
            'returns' => $this->syncReturns($cursor, $limit, $mallId),
            default => collect(),
        };

        $nextCursor = $data->isNotEmpty()
            ? $data->last()->updated_at->toIso8601String()
            : $cursor;

        SyncCursor::updateOrCreate(
            ['entity_type' => $type],
            ['last_cursor' => $nextCursor]
        );

        return response()->json([
            'data' => $data,
            'next_cursor' => $nextCursor,
            'has_more' => $data->count() >= $limit,
        ]);
    }

    private function syncOrders($cursor, $limit, $mallId)
    {
        $query = Order::with(['mall:id,name_ar', 'user:id,name,phone', 'items.product:id,name_ar,barcode,sku'])
            ->whereIn('status', ['completed', 'paid']);

        if ($mallId) $query->where('mall_id', $mallId);
        if ($cursor) $query->where('updated_at', '>', $cursor);

        return $query->latest('updated_at')->limit($limit)->get();
    }

    private function syncProducts($cursor, $limit, $mallId)
    {
        $query = Product::with('mall:id,name_ar');

        if ($mallId) $query->where('mall_id', $mallId);
        if ($cursor) $query->where('updated_at', '>', $cursor);

        return $query->latest('updated_at')->limit($limit)->get();
    }

    private function syncCustomers($cursor, $limit)
    {
        $query = User::whereHas('roles', fn($q) => $q->where('name', 'customer'))
            ->select('id', 'name', 'email', 'phone', 'whatsapp', 'address', 'created_at', 'updated_at');

        if ($cursor) $query->where('updated_at', '>', $cursor);

        return $query->latest('updated_at')->limit($limit)->get();
    }

    private function syncReturns($cursor, $limit, $mallId)
    {
        $query = ReturnProduct::with(['order:id', 'product:id,name_ar,barcode', 'mall:id,name_ar']);

        if ($mallId) $query->where('mall_id', $mallId);
        if ($cursor) $query->where('updated_at', '>', $cursor);

        return $query->latest('updated_at')->limit($limit)->get();
    }

    private function getMallId(Request $request): ?int
    {
        if ($request->user()->hasRole('mall-owner') || $request->user()->hasRole('supermarket-owner')) {
            return $request->user()->mall_id;
        }
        return $request->filled('mall_id') ? (int) $request->mall_id : null;
    }
}
