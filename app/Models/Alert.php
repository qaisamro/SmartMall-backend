<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = ['type', 'title', 'body', 'icon', 'is_active', 'expires_at', 'order'];
    protected $casts = ['is_active' => 'boolean', 'expires_at' => 'datetime'];
}
