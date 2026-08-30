<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    protected $table = 'push_subscriptions';

    protected $fillable = [
        'user_id', 'endpoint', 'auth_key', 'p256dh_key', 'user_agent',
    ];
}
