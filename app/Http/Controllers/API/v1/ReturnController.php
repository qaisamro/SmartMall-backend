<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\ReturnProduct;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    public function index(Request $request)
    {
        $query = ReturnProduct::with(['order', 'product:id,name_ar,barcode,sku', 'mall:id,name_ar', 'user:id,name']);

        // Owner scope: only their mall's returns
        if ($request->user()->hasRole('mall-owner') || $request->user()->hasRole('supermarket-owner')) {
            $mallId = $request->user()->mall_id;
            if (!$mallId) {
                return response()->json(['message' => 'No mall associated'], 403);
            }
            $query->where('mall_id', $mallId);
        }

        // Admin can filter by mall_id
        if ($request->filled('mall_id')) {
            $query->where('mall_id', $request->mall_id);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $returns = $query->latest()->paginate($request->per_page ?? 50);

        return response()->json($returns);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'product_id' => 'nullable|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'amount' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:500',
        ]);

        $validated['mall_id'] = $request->user()->mall_id ?? $request->mall_id;
        $validated['user_id'] = $request->user()->id;

        $return = ReturnProduct::create($validated);

        return response()->json($return->load('order', 'product', 'mall'), 201);
    }

    public function show($id)
    {
        $return = ReturnProduct::with(['order', 'product', 'mall', 'user'])->findOrFail($id);
        return response()->json($return);
    }
}
