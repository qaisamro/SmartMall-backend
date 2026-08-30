<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    public static function logActivity($action, $description = null, $model = null, $userId = null, $mallId = null, $metadata = [])
    {
        $user = auth()->user();

        return ActivityLog::create([
            'user_id'    => $userId ?? $user?->id,
            'user_type'  => $user?->getRoleNames()?->first(),
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

    public static function log($action, $description = null, $model = null, $userId = null, $mallId = null, $metadata = [])
    {
        return self::logActivity($action, $description, $model, $userId, $mallId, $metadata);
    }
}
