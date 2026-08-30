<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ImageOptimizerService;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    public function __construct(
        protected ImageOptimizerService $imageOptimizer
    ) {}

    public function upload(Request $request, $id)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp|max:10240',
        ]);

        $product = Product::findOrFail($id);

        // Authorization: user must own the mall this product belongs to
        $userMallIds = $request->user()->malls()->pluck('id');
        if (!$userMallIds->contains($product->mall_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete old image if it exists
        if ($product->image && \Illuminate\Support\Facades\Storage::exists($product->image)) {
            \Illuminate\Support\Facades\Storage::delete($product->image);
        }

        // Optimize: resize + convert to WebP
        $filePath = $this->imageOptimizer->optimize(
            $request->file('image'),
            'products',
            1200,
            1200
        );

        $product->update(['image' => $filePath]);

        return response()->json([
            'message' => 'Image uploaded successfully',
            'image'   => $product->fresh()->image,
            'url'     => \Illuminate\Support\Facades\Storage::url($filePath),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $userMallIds = $request->user()->malls()->pluck('id');
        if (!$userMallIds->contains($product->mall_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($product->image && \Illuminate\Support\Facades\Storage::exists($product->image)) {
            \Illuminate\Support\Facades\Storage::delete($product->image);
        }

        $product->update(['image' => null]);

        return response()->json(['message' => 'Image deleted']);
    }
}
