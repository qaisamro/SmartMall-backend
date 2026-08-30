<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminSendNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class AdminNotificationController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'target_type' => 'required|in:all,customers,mall-owners,delivery-persons,custom',
            'user_ids' => 'required_if:target_type,custom|array',
            'user_ids.*' => 'exists:users,id',
            'action_url' => 'nullable|string',
        ]);

        $actionUrl = $request->action_url ?? '/notifications';

        $query = User::where('is_active', true);

        switch ($request->target_type) {
            case 'customers':
                $query->role('customer');
                break;
            case 'mall-owners':
                $query->role(['mall-owner', 'supermarket-owner']);
                break;
            case 'delivery-persons':
                $query->role('delivery-person');
                break;
            case 'custom':
                $query->whereIn('id', $request->user_ids);
                break;
            // 'all' — no filter
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            return response()->json(['message' => 'لا يوجد مستخدمين مستهدفين'], 422);
        }

        Notification::send($users, new AdminSendNotification(
            $request->title,
            $request->body,
            $actionUrl,
        ));

        return response()->json([
            'message' => "تم إرسال الإشعار لـ {$users->count()} مستخدم",
            'count' => $users->count(),
        ]);
    }
}
