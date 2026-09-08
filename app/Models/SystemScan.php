<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemScan extends Model
{
    protected $fillable = [
        'status', 'trigger_type', 'triggered_by', 'started_at', 'finished_at',
        'duration_ms', 'total_tests', 'passed', 'warnings', 'failed', 'critical',
        'not_checked', 'review_required', 'overall_status', 'meta',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'meta' => 'array',
    ];

    public function triggerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(SystemScanResult::class, 'scan_id');
    }

    public function scopeLatestFirst($q)
    {
        return $q->orderByDesc('started_at');
    }
}
