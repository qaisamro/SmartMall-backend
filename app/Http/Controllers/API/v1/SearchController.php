<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Search globally within a specific mall.
     * Accessible by public without auth.
     */
    public function search(Request $request)
    {
        $request->validate([
            'mall_id' => 'required|exists:malls,id',
            'query'   => 'required|string|min:2'
        ]);

        $mallId = $request->mall_id;
        $q = $request->input('query');

        $products = Product::where('mall_id', $mallId)
            ->where('is_active', true)
            ->where(function ($query) use ($q) {
                $query->where('name_ar', 'LIKE', "%{$q}%")
                      ->orWhere('name_en', 'LIKE', "%{$q}%")
                      ->orWhere('barcode', 'LIKE', "%{$q}%")
                      ->orWhere('shelf_location', 'LIKE', "%{$q}%");
            })
            ->with('category:id,name_ar,name_en')
            ->get();

        // Hide stock quantity since this is public search
        $products->transform(function($product) {
             if($product->hide_stock_from_customer) {
                 unset($product->stock_quantity);
             }
             return $product;
        });

        return response()->json($products);
    }

    /**
     * Global search across all malls (used by Navbar).
     */
    public function globalSearch(Request $request)
    {
        $request->validate(['query' => 'required|string|min:1']);
        $q = $request->input('query');

        $products = Product::where('is_active', true)
            ->where(function ($query) use ($q) {
                $query->where('name_ar', 'LIKE', "%{$q}%")
                    ->orWhere('name_en', 'LIKE', "%{$q}%")
                    ->orWhere('barcode', 'LIKE', "%{$q}%");
            })
            ->with(['mall:id,name_ar,slug', 'category:id,name_ar'])
            ->limit(30)
            ->get();

        $grouped = $products->groupBy('mall_id')->map(function ($items, $mallId) {
            $mall = $items->first()->mall;
            return [
                'mall_id' => $mallId,
                'mall_slug' => $mall->slug ?? $mallId,
                'mall_name' => $mall->name_ar ?? $mall->name_en ?? 'مول',
                'products' => $items->map(function ($p) {
                    if ($p->hide_stock_from_customer) unset($p->stock_quantity);
                    return $p;
                })->values(),
            ];
        })->values();

        return response()->json([
            'total' => $products->count(),
            'grouped' => $grouped,
            'query' => $q,
        ]);
    }
}
