<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemScanResult extends Model
{
    protected $fillable = [
        'scan_id', 'check_key', 'category', 'severity', 'status',
        'title', 'message', 'details', 'user_impact',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(SystemScan::class, 'scan_id');
    }
}
