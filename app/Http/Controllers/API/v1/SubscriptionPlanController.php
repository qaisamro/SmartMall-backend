<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(SubscriptionPlan::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name_ar' => 'required|string',
            'name_en' => 'required|string',
            'slug' => 'required|string|unique:subscription_plans,slug',
            'price_monthly' => 'required|numeric|min:0',
            'price_quarterly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'max_products' => 'nullable|integer',
            'max_employees' => 'nullable|integer',
            'max_branches' => 'nullable|integer',
            'features' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $plan = SubscriptionPlan::create($validated);
        return response()->json($plan, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(SubscriptionPlan $subscriptionPlan)
    {
        return response()->json($subscriptionPlan);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $validated = $request->validate([
            'name_ar' => 'sometimes|string',
            'name_en' => 'sometimes|string',
            'price_monthly' => 'sometimes|numeric|min:0',
            'price_quarterly' => 'sometimes|numeric|min:0',
            'price_yearly' => 'sometimes|numeric|min:0',
            'max_products' => 'nullable|integer',
            'max_employees' => 'nullable|integer',
            'max_branches' => 'nullable|integer',
            'features' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $subscriptionPlan->update($validated);
        return response()->json($subscriptionPlan);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SubscriptionPlan $subscriptionPlan)
    {
        $subscriptionPlan->delete();
        return response()->json(null, 204);
    }
}
