<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckMallStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // If the user is a mall/supermarket owner, check their mall status
        if ($user && ($user->hasRole('mall-owner') || $user->hasRole('supermarket-owner'))) {
            $user->loadMissing('mall');
            
            if ($user->mall && !$user->mall->is_active) {
                // If the mall is inactive, block access
                return response()->json([
                    'message' => 'عذراً، تم تعطيل خدمة هذا المول من قبل الإدارة. يرجى التواصل مع الدعم الفني.',
                    'status' => 'suspended',
                    'mall_id' => $user->mall->id
                ], 403);
            }
        }

        return $next($request);
    }
}
