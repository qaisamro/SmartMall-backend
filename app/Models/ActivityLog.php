<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'user_type', 'action', 'description',
        'model_type', 'model_id', 'ip_address', 'user_agent',
        'metadata', 'mall_id',
    ];

    public $timestamps = false;

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function mall()
    {
        return $this->belongsTo(Mall::class);
    }

    public function scopeOfAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeInMall($query, $mallId)
    {
        return $query->where('mall_id', $mallId);
    }
}
