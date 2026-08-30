<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\Request;

class PushNotificationController extends Controller
{
    public function subscribe(Request $request)
    {
        try {
            $request->validate([
                'endpoint' => 'required|string',
                'keys.auth' => 'required|string',
                'keys.p256dh' => 'required|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Push subscription validation failed', $e->errors());
            \Log::error('Payload:', $request->all());
            throw $e;
        }

        $sub = PushSubscription::updateOrCreate(
            ['endpoint' => $request->endpoint],
            [
                'user_id' => $request->user()?->id,
                'auth_key' => $request->input('keys.auth'),
                'p256dh_key' => $request->input('keys.p256dh'),
                'user_agent' => substr($request->userAgent(), 0, 250),
            ]
        );

        return response()->json(['message' => 'Subscribed', 'id' => $sub->id]);
    }

    public function unsubscribe(Request $request)
    {
        $request->validate(['endpoint' => 'required|string']);
        PushSubscription::where('endpoint', $request->endpoint)->delete();
        return response()->json(['message' => 'Unsubscribed']);
    }

    public function test(Request $request)
    {
        $user = $request->user();
        $subscriptions = PushSubscription::where('user_id', $user->id)->get();

        if ($subscriptions->isEmpty()) {
            return response()->json(['message' => 'لا يوجد اشتراك push لهذا المستخدم'], 422);
        }

        $push = app(\App\Services\PushNotificationService::class);
        $reports = $push->sendToUser($user->id, 'إشعار تجريبي', 'هذا إشعار تجريبي من SmartMall', '/');

        return response()->json([
            'message' => 'تم الإرسال التجريبي',
            'reports' => $reports
        ]);
    }

    public function testDebug()
    {
        $lastSub = PushSubscription::latest()->first();
        if (!$lastSub) {
            return response()->json(['message' => 'لا يوجد أي اشتراكات في قاعدة البيانات، مما يعني أن الآيفون يفشل في إرسال طلب الاشتراك للسيرفر!'], 404);
        }

        $push = app(\App\Services\PushNotificationService::class);
        $reports = $push->sendToUser($lastSub->user_id, 'إشعار فحص', 'هذا الإشعار لتجربة وصول الإشعارات في الخلفية', '/');

        return response()->json([
            'message' => 'تفاصيل إرسال آخر اشتراك',
            'last_subscription' => $lastSub,
            'reports' => $reports
        ]);
    }
}
