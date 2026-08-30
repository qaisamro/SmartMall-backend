<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Mall;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function index(Request $request)
    {
        $query = Offer::with('mall:id,name_ar,slug', 'product:id,name_ar,name_en,price,discount_price,image,description_ar')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });

        if ($request->mall_id) {
            $query->where('mall_id', $request->mall_id);
        }

        return response()->json($query->latest()->get());
    }

    public function adminIndex()
    {
        return response()->json(Offer::with('mall:id,name_ar')->latest()->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'title_ar' => 'required|string|max:255',
            'title_en' => 'required|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'image' => 'nullable|string',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'mall_id' => 'required|exists:malls,id',
        ]);

        $offer = Offer::create($request->all());

        return response()->json($offer->load('mall:id,name_ar'), 201);
    }

    public function update(Request $request, $id)
    {
        $offer = Offer::findOrFail($id);

        $request->validate([
            'title_ar' => 'sometimes|string|max:255',
            'title_en' => 'sometimes|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'image' => 'nullable|string',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'is_active' => 'sometimes|boolean',
        ]);

        $offer->update($request->all());

        return response()->json($offer->load('mall:id,name_ar'));
    }

    public function destroy($id)
    {
        $offer = Offer::findOrFail($id);
        $offer->delete();

        return response()->json(['message' => 'تم حذف العرض']);
    }
}
