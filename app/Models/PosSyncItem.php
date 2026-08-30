<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSyncItem extends Model
{
    protected $fillable = ['pos_sync_session_id', 'product_id', 'quantity', 'price_at_scan'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSyncSession::class, 'pos_sync_session_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
