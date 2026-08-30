<?php

namespace App\Helpers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    public static function log($action, $description = null, $model = null, $userId = null, $mallId = null, $metadata = [])
    {
        $user = auth()->user();

        // If no auth user but userId provided, look up the user
        if (!$user && $userId) {
            $user = User::find($userId);
        }

        return ActivityLog::create([
            'user_id'    => $userId ?? $user?->id,
            'user_type'  => $user ? ($user->getRoleNames()?->first() ?? class_basename($user)) : 'guest',
            'action'     => $action,
            'description'=> $description,
            'model_type' => $model ? get_class($model) : null,
            'model_id'   => $model?->id,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'metadata'   => $metadata ?: null,
            'mall_id'    => $mallId ?? ($user?->mall_id ?? null),
        ]);
    }
}
