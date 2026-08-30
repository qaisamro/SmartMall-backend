<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsEntry extends Model
{
    protected $fillable = ['title', 'summary', 'source', 'source_url', 'image', 'is_active', 'order', 'published_at'];
    protected $casts = ['is_active' => 'boolean', 'published_at' => 'datetime'];
}
