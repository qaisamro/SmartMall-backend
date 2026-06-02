<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'shelves'])->where('is_active', true);
        if ($request->has('mall_id')) {
            $query->where('mall_id', $request->mall_id);
        }
        return response()->json($query->latest()->paginate(20));
    }

    public function show($id)
    {
        return response()->json(Product::with(['shelves', 'category'])->findOrFail($id));
    }

    public function ownerProducts(Request $request)
    {
        $mallIds = $request->user()->malls()->pluck('id');
        $query = Product::with(['category'])->whereIn('mall_id', $mallIds);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name_ar', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->latest()->paginate(20);
        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'mall_id'     => 'required|exists:malls,id',
            'category_id' => 'required|exists:categories,id',
            'name_ar'     => 'required|string',
            'name_en'     => 'required|string',
            'price'       => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'sku'         => 'nullable|string|max:255',
            'brand'       => 'nullable|string|max:255',
            'barcode'     => 'nullable|string|max:255',
        ]);

        $mallIds = $request->user()->malls()->pluck('id');
        if (!$mallIds->contains((int) $validated['mall_id'])) {
            return response()->json(['message' => 'You do not own this mall.'], 403);
        }

        $categoryBelongsToMall = Category::where('id', $validated['category_id'])
            ->where('mall_id', $validated['mall_id'])
            ->exists();

        if (!$categoryBelongsToMall) {
            return response()->json(['message' => 'The selected category does not belong to this mall.'], 422);
        }

        $product = Product::create(array_merge($request->only([
            'mall_id','category_id','name_ar','name_en',
            'description_ar','description_en','price',
            'discount_price','stock_quantity','brand','sku','image','shelf_location'
        ]), [
            'barcode' => $request->barcode ?? strtoupper(Str::random(12)),
        ]));

        return response()->json($product->load('category'), 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $product->update($request->only([
            'name_ar','name_en','description_ar','description_en',
            'price','discount_price','stock_quantity','brand','sku','image','is_active', 'barcode', 'shelf_location'
        ]));
        return response()->json($product->load('category'));
    }

    public function destroy($id)
    {
        Product::findOrFail($id)->delete();
        return response()->json(['message' => 'Product deleted']);
    }

    public function destroyAll(Request $request)
    {
        $mallIds = $request->user()->malls()->pluck('id');
        $deletedCount = Product::whereIn('mall_id', $mallIds)->delete();
        return response()->json(['message' => "Deleted {$deletedCount} products"]);
    }

    public function getMallProducts(Request $request, $id)
    {
        $query = Product::with(['category', 'shelves'])
            ->where('mall_id', $id)
            ->where('is_active', true);
            
        if ($request->has('random')) {
            $limit = $request->get('random', 5);
            $products = $query->inRandomOrder()->limit($limit)->get();
        } else {
            $products = $query->latest()->paginate(50);
        }

        // Hide stock if required
        $collection = $products instanceof \Illuminate\Pagination\LengthAwarePaginator ? $products->getCollection() : $products;
        
        $collection->transform(function($product) {
             if($product->hide_stock_from_customer) {
                 unset($product->stock_quantity);
             }
             return $product;
        });

        return response()->json($products);
    }

    public function scanner(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'mall_id' => 'nullable|exists:malls,id'
        ]);

        $query = Product::with('shelves', 'category')
            ->where(function($q) use ($request) {
                $q->where('barcode', $request->code)->orWhere('qr_code', $request->code);
            });

        // Scoped scanning if explicitly passed from frontend context
        if ($request->has('mall_id')) {
            $query->where('mall_id', $request->mall_id);
        }

        $product = $query->firstOrFail();

        if ($product->hide_stock_from_customer) {
            unset($product->stock_quantity);
        }

        return response()->json($product);
    }
}
