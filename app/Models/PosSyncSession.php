<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSyncSession extends Model
{
    protected $fillable = ['token', 'mall_id', 'user_id', 'status'];

    public function items(): HasMany
    {
        return $this->hasMany(PosSyncItem::class, 'pos_sync_session_id');
    }

    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class);
    }
}
