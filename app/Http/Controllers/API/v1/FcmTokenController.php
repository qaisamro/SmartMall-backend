<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\FcmToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FcmTokenController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|max:512',
            'platform' => 'sometimes|string|in:android,ios,web',
            'device_id' => 'sometimes|string|max:255',
            'app_version' => 'sometimes|string|max:32',
        ]);
        if ($validator->fails()) return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);
        $platform = $request->input('platform', 'android');
        FcmToken::updateOrCreate(['token' => $request->token], ['user_id' => $user->id, 'platform' => $platform, 'device_id' => $request->input('device_id'), 'app_version' => $request->input('app_version'), 'last_used_at' => now()]);
        return response()->json(['message' => 'FCM token registered']);
    }

    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), ['token' => 'required|string|max:512']);
        if ($validator->fails()) return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        $user = $request->user();
        $deleted = FcmToken::where('token', $request->token)->where('user_id', optional($user)->id)->delete();
        return response()->json(['message' => $deleted ? 'FCM token removed' : 'Token not found', 'deleted' => (bool) $deleted]);
    }

    public function destroyAll(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);
        $deleted = FcmToken::where('user_id', $user->id)->delete();
        return response()->json(['message' => 'All FCM tokens removed', 'deleted' => $deleted]);
    }

    public function test(Request $request)
    {
        $user = $request->user();
        $tokens = FcmToken::active()->forUser($user->id)->get();
        if ($tokens->isEmpty()) return response()->json(['message' => 'لا يوجد FCM token مسجل لهذا المستخدم'], 422);
        $fcm = app(\App\Services\FcmNotificationService::class);
        $result = $fcm->sendToUser($user->id, 'إشعارات SmartMall', 'FCM working ✅', '/notifications');
        return response()->json(['message' => 'FCM test sent', 'result' => $result]);
    }
}
