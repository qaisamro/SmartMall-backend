<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    /**
     * List all active subscription plans for owners.
     */
    public function index()
    {
        return response()->json(SubscriptionPlan::where('is_active', true)->get());
    }

    /**
     * Get current subscription for a mall.
     */
    public function current(Request $request)
    {
        $request->validate(['mall_id' => 'required|exists:malls,id']);
        
        $subscription = Subscription::with('plan')
            ->where('mall_id', $request->mall_id)
            ->where('status', 'active')
            ->latest()
            ->first();

        return response()->json($subscription);
    }

    /**
     * Subscribe a mall to a plan.
     */
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'mall_id' => 'required|exists:malls,id',
            'plan_id' => 'required|exists:subscription_plans,id',
            'period' => 'required|in:monthly,quarterly,yearly',
        ]);

        // Check if mall owner owns the mall - DISABLED: Only Admin can subscribe malls now
        if (! $request->user()->hasRole('admin') && ! $request->user()->hasRole('super-admin')) {
            return response()->json(['message' => 'Only Admin can manage subscriptions'], 403);
        }

        $plan = SubscriptionPlan::findOrFail($validated['plan_id']);
        $months = match($validated['period']) {
            'monthly' => 1,
            'quarterly' => 3,
            'yearly' => 12,
        };

        // In a real app, process payment here. For now, we just create the subscription.
        
        // Deactivate old active subscriptions for this mall
        Subscription::where('mall_id', $validated['mall_id'])
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        $subscription = Subscription::create([
            'mall_id' => $validated['mall_id'],
            'plan_id' => $validated['plan_id'],
            'billing_period' => $validated['period'],
            'starts_at' => now(),
            'ends_at' => now()->addMonths($months),
            'status' => 'active',
        ]);

        return response()->json($subscription->load('plan'));
    }

    /**
     * List all subscriptions (Admin Only).
     */
    public function adminIndex()
    {
        return response()->json(Subscription::with(['mall', 'plan'])->latest()->paginate(20));
    }

    /**
     * Admin manually grants a subscription.
     */
    public function adminSubscribe(Request $request)
    {
        $validated = $request->validate([
            'mall_id' => 'required|exists:malls,id',
            'plan_id' => 'required|exists:subscription_plans,id',
            'period' => 'required|in:monthly,quarterly,yearly',
        ]);

        $months = match($validated['period']) {
            'monthly' => 1,
            'quarterly' => 3,
            'yearly' => 12,
        };

        // Deactivate old active subscriptions for this mall
        Subscription::where('mall_id', $validated['mall_id'])
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        $subscription = Subscription::create([
            'mall_id' => $validated['mall_id'],
            'plan_id' => $validated['plan_id'],
            'billing_period' => $validated['period'],
            'starts_at' => now(),
            'ends_at' => now()->addMonths($months),
            'status' => 'active',
        ]);

        return response()->json($subscription->load(['mall', 'plan']));
    }
}
