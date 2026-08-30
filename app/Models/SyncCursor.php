<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncCursor extends Model
{
    protected $fillable = [
        'entity_type',
        'last_cursor',
    ];

    protected $casts = [
        'last_cursor' => 'datetime',
    ];
}
