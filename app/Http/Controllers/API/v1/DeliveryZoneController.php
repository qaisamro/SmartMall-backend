<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use Illuminate\Http\Request;

class DeliveryZoneController extends Controller
{
    public function index()
    {
        return response()->json(DeliveryZone::all());
    }

    public function active()
    {
        return response()->json(DeliveryZone::where('is_active', true)->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'fee' => 'required|numeric|min:0',
        ]);

        $zone = DeliveryZone::create($validated);
        return response()->json($zone, 201);
    }

    public function update(Request $request, $id)
    {
        $zone = DeliveryZone::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'fee' => 'sometimes|required|numeric|min:0',
            'is_active' => 'sometimes|boolean'
        ]);

        $zone->update($validated);
        return response()->json($zone);
    }

    public function destroy($id)
    {
        $zone = DeliveryZone::findOrFail($id);
        $zone->delete();
        return response()->json(['message' => 'Zone deleted']);
    }
}
