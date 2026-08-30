<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingOrder extends Model
{
    protected $fillable = [
        'mall_id',
        'user_id',
        'items_json',
        'total',
        'notes',
        'phone',
        'is_paid',
    ];

    protected $casts = [
        'items_json' => 'array',
        'is_paid' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
