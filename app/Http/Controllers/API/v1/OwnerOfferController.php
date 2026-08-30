<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\User;
use App\Notifications\OfferCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class OwnerOfferController extends Controller
{
    public function index(Request $request)
    {
        $mall = $request->user()->mall;
        if (!$mall) {
            return response()->json([], 200);
        }

        $offers = Offer::where('mall_id', $mall->id)->latest()->get();

        return response()->json([
            'offers' => $offers,
            'limit' => $mall->offer_limit,
            'used' => $mall->total_offers_used,
            'remaining' => max(0, $mall->offer_limit - $mall->total_offers_used),
        ]);
    }

    public function store(Request $request)
    {
        $mall = $request->user()->mall;
        if (!$mall) {
            return response()->json(['message' => 'لا يوجد مول مرتبط'], 400);
        }

        if ($mall->offer_limit > 0 && $mall->total_offers_used >= $mall->offer_limit) {
            return response()->json(['message' => 'لقد استنفدت الحد المسموح به من العروض. تواصل مع الإدارة لزيادة الحد.'], 403);
        }

        $request->validate([
            'title_ar' => 'required|string|max:255',
            'title_en' => 'required|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'ends_at' => 'nullable|date',
            'product_id' => 'nullable|exists:products,id',
            'offer_price' => 'nullable|numeric|min:0',
            'offer_quantity' => 'nullable|integer|min:1|max:10000',
            'tiers' => 'nullable|string',
        ]);

        // Parse tiers (JSON array of {quantity, price}) — supports multiple quantity/price pairs per offer
        $tiers = null;
        if ($request->filled('tiers')) {
            $raw = $request->input('tiers');
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            if (is_array($decoded)) {
                $tiers = collect($decoded)->filter(function($t){
                    return isset($t['quantity']) && isset($t['price']) && (int)$t['quantity'] > 0 && is_numeric($t['price']);
                })->map(function($t){
                    return ['quantity' => (int)$t['quantity'], 'price' => (float)$t['price']];
                })->sortBy('quantity')->values()->all();
                if (empty($tiers)) $tiers = null;
            }
        }
        // Fallback: if tiers not provided but single offer_price/quantity provided, create single-tier
        if (!$tiers && $request->filled('offer_price')) {
            $tiers = [['quantity' => (int)($request->input('offer_quantity', 1) ?: 1), 'price' => (float)$request->offer_price]];
        }

        $data = [
            'mall_id' => $mall->id,
            'title_ar' => $request->title_ar,
            'title_en' => $request->title_en,
            'description_ar' => $request->description_ar,
            'description_en' => $request->description_en,
            'product_id' => $request->product_id,
            'offer_price' => $request->offer_price,
            'offer_quantity' => $request->input('offer_quantity', 1) ?: 1,
            'tiers' => $tiers,
            'ends_at' => $request->ends_at,
            'is_active' => true,
        ];
        // Keep first tier in legacy columns for backward compat
        if ($tiers && count($tiers) > 0) {
            $data['offer_price'] = $tiers[0]['price'];
            $data['offer_quantity'] = $tiers[0]['quantity'];
        }

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('offers', 'public');
            $data['image'] = Storage::url($path);
        }

        $offer = Offer::create($data);

        $mall->increment('total_offers_used');

        // Notify all customers about the new offer
        try {
            $customers = User::role('customer')->where('is_active', true)->get();
            if ($customers->isNotEmpty()) {
                Notification::send($customers, new OfferCreated($offer));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Offer notification failed', [
                'offer_id' => $offer->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json($offer->load('mall:id,name_ar'), 201);
    }

    public function update(Request $request, $id)
    {
        $mall = $request->user()->mall;
        $offer = Offer::where('id', $id)->where('mall_id', $mall?->id)->firstOrFail();

        $request->validate([
            'title_ar' => 'sometimes|string|max:255',
            'title_en' => 'sometimes|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'ends_at' => 'nullable|date',
            'product_id' => 'nullable|exists:products,id',
            'offer_price' => 'nullable|numeric|min:0',
            'offer_quantity' => 'nullable|integer|min:1|max:10000',
            'tiers' => 'nullable|string',
        ]);

        $data = $request->only([
            'title_ar', 'title_en', 'description_ar', 'description_en',
            'ends_at', 'product_id', 'offer_price', 'offer_quantity'
        ]);

        // Handle tiers JSON if provided (multiple quantity/price pairs)
        if ($request->filled('tiers')) {
            $raw = $request->input('tiers');
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            if (is_array($decoded)) {
                $tiers = collect($decoded)->filter(function($t){
                    return isset($t['quantity']) && isset($t['price']) && (int)$t['quantity'] > 0 && is_numeric($t['price']);
                })->map(function($t){
                    return ['quantity' => (int)$t['quantity'], 'price' => (float)$t['price']];
                })->sortBy('quantity')->values()->all();
                if (!empty($tiers)) {
                    $data['tiers'] = $tiers;
                    // sync first tier to legacy columns
                    $data['offer_price'] = $tiers[0]['price'];
                    $data['offer_quantity'] = $tiers[0]['quantity'];
                }
            }
        } elseif ($request->filled('offer_price') && $request->filled('offer_quantity')) {
            $data['tiers'] = [['quantity' => (int)$request->offer_quantity, 'price' => (float)$request->offer_price]];
        }

        // Accept "true"/"false"/1/0 forms of is_active (multipart sends them as strings)
        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        if ($request->hasFile('image')) {
            // Delete old image
            if ($offer->image) {
                $oldPath = str_replace(Storage::url(''), '', $offer->image);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('image')->store('offers', 'public');
            $data['image'] = Storage::url($path);
        }

        $offer->update($data);

        return response()->json($offer);
    }

    public function destroy(Request $request, $id)
    {
        $mall = $request->user()->mall;
        $offer = Offer::where('id', $id)->where('mall_id', $mall?->id)->firstOrFail();

        if ($offer->image) {
            $oldPath = str_replace(Storage::url(''), '', $offer->image);
            Storage::disk('public')->delete($oldPath);
        }

        $offer->delete();

        return response()->json(['message' => 'تم حذف العرض']);
    }
}
