<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemError extends Model
{
    protected $fillable = [
        'fingerprint', 'type', 'severity', 'source', 'message', 'file', 'line',
        'url', 'route', 'method', 'status_code', 'request_id', 'stack_trace',
        'user_id', 'user_role', 'occurrences', 'first_seen_at', 'last_seen_at',
        'resolved', 'resolved_at', 'resolved_by',
    ];

    protected $casts = [
        'resolved' => 'boolean',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeUnresolved($q)
    {
        return $q->where('resolved', false);
    }

    public function scopeCritical($q)
    {
        return $q->where('severity', 'critical');
    }
}
