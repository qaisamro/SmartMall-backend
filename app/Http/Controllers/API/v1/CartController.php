<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Helpers\ActivityLogger;
use App\Models\Mall;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class CartController extends Controller
{
    public function logAdd(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer',
            'product_name' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'price' => 'required|numeric',
            'mall_id' => 'nullable|integer',
        ]);

        $userId = null;
        if ($tokenStr = $request->bearerToken()) {
            $token = PersonalAccessToken::findToken($tokenStr);
            $userId = $token?->tokenable?->id;
        }

        $mallName = null;
        if ($request->mall_id) {
            $mall = Mall::find($request->mall_id);
            $mallName = $mall?->name_ar;
        }

        ActivityLogger::log(
            'added_to_cart',
            'إضافة "' . $request->product_name . '" إلى السلة' . ($mallName ? ' في ' . $mallName : ''),
            null,
            $userId,
            $request->mall_id,
            [
                'product_id' => $request->product_id,
                'product_name' => $request->product_name,
                'quantity' => $request->quantity,
                'price' => $request->price,
                'mall_id' => $request->mall_id,
            ]
        );

        return response()->json(['message' => 'Logged']);
    }
}
