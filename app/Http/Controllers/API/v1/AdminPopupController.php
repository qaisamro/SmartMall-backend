<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\SitePopup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminPopupController extends Controller
{
    public function index()
    {
        return response()->json(SitePopup::orderBy('id', 'desc')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'btn_text' => 'nullable|string|max:255',
            'btn_url' => 'nullable|string|max:255',
            'target_audience' => 'required|in:all,logged_in,guest,customer,mall-owner,supermarket-owner,delivery-person,order-tracker',
            'target_page' => 'required|string|max:255',
            'is_active' => 'nullable|in:true,false,1,0,on,off,yes,no',
            'auto_close_seconds' => 'nullable|integer|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $data['is_active'] = in_array($data['is_active'] ?? true, ['true', '1', 'on', 'yes', true, 1], true) ? true : false;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('popups', 'public');
        }

        $popup = SitePopup::create($data);

        return response()->json($popup, 201);
    }

    public function show($id)
    {
        return response()->json(SitePopup::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $popup = SitePopup::findOrFail($id);

        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'btn_text' => 'nullable|string|max:255',
            'btn_url' => 'nullable|string|max:255',
            'target_audience' => 'sometimes|required|in:all,logged_in,guest,customer,mall-owner,supermarket-owner,delivery-person,order-tracker',
            'target_page' => 'sometimes|required|string|max:255',
            'is_active' => 'nullable|in:true,false,1,0,on,off,yes,no',
            'auto_close_seconds' => 'nullable|integer|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = in_array($data['is_active'], ['true', '1', 'on', 'yes', true, 1], true) ? true : false;
        }

        if ($request->hasFile('image')) {
            if ($popup->image) Storage::disk('public')->delete($popup->image);
            $data['image'] = $request->file('image')->store('popups', 'public');
        }

        $popup->update($data);

        return response()->json($popup);
    }

    public function destroy($id)
    {
        $popup = SitePopup::findOrFail($id);
        if ($popup->image) Storage::disk('public')->delete($popup->image);
        $popup->delete();

        return response()->json(['message' => 'تم حذف الإعلان']);
    }

    // Public: get active popups for the current user
    public function active(Request $request)
    {
        $user = $request->user();
        $now = now();
        $path = trim($request->query('page', '/'), '/');
        if ($path === '') $path = 'home';

        $query = SitePopup::where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });

        // Filter by page
        $query->where(function ($q) use ($path) {
            $q->where('target_page', 'all')->orWhere('target_page', $path);
        });

        $popups = $query->get();

        // Filter by audience
        $popups = $popups->filter(function ($popup) use ($user) {
            $audience = $popup->target_audience;
            if ($audience === 'all') return true;
            if ($audience === 'logged_in' && $user) return true;
            if ($audience === 'guest' && !$user) return true;
            if ($audience === 'customer' && $user && $user->hasRole('customer')) return true;
            if ($audience === 'mall-owner' && $user && $user->hasRole('mall-owner')) return true;
            if ($audience === 'supermarket-owner' && $user && $user->hasRole('supermarket-owner')) return true;
            if ($audience === 'delivery-person' && $user && $user->hasRole('delivery-person')) return true;
            if ($audience === 'order-tracker' && $user && $user->hasRole('order-tracker')) return true;
            return false;
        })->values();

        return response()->json($popups);
    }
}
