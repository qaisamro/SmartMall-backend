<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SitePopup extends Model
{
    protected $fillable = [
        'title', 'content', 'image', 'btn_text', 'btn_url',
        'target_audience', 'target_page', 'is_active',
        'auto_close_seconds', 'starts_at', 'ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];
}
