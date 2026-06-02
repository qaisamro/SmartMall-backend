<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function ownerCategories(Request $request)
    {
        $mallIds = $request->user()->malls()->pluck('id');
        $targetMallIds = $request->filled('mall_id')
            ? $mallIds->filter(fn ($id) => (int) $id === (int) $request->mall_id)
            : $mallIds;

        foreach ($targetMallIds as $mallId) {
            Category::firstOrCreate(
                ['mall_id' => $mallId, 'name_en' => 'General'],
                ['name_ar' => 'عام']
            );
        }

        $categories = Category::whereIn('mall_id', $mallIds)
            ->when($request->filled('mall_id'), fn ($query) => $query->where('mall_id', $request->mall_id))
            ->orderBy('name_ar')
            ->get();

        return response()->json($categories);
    }
}
