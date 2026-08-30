<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoldEntry extends Model
{
    protected $fillable = ['city', 'type', 'price', 'change', 'is_active', 'fetched_at'];
    protected $casts = ['is_active' => 'boolean', 'fetched_at' => 'datetime', 'price' => 'float', 'change' => 'float'];
}
