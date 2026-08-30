<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Subscription;

class CheckSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && ($user->hasRole('mall-owner') || $user->hasRole('supermarket-owner'))) {
            $user->loadMissing('mall');

            if ($user->mall) {
                $activeSub = Subscription::where('mall_id', $user->mall->id)
                    ->where('status', 'active')
                    ->latest()
                    ->first();

                // If no active subscription OR subscription is past its end date
                if (!$activeSub || ($activeSub->ends_at && now()->gt($activeSub->ends_at))) {
                    // Mark the mall as suspended due to subscription expiry
                    $user->mall->update([
                        'suspended_at' => $user->mall->suspended_at ?? now(),
                        'suspended_reason' => 'subscription_expired',
                        'is_active' => false,
                    ]);

                    if ($activeSub) {
                        $activeSub->update(['status' => 'expired']);
                    }

                    return response()->json([
                        'message' => 'عذراً، تم تجميد حسابك بسبب انتهاء الاشتراك. يرجى التواصل مع الإدارة لتجديد الاشتراك.',
                        'status' => 'subscription_expired',
                        'mall_id' => $user->mall->id
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
