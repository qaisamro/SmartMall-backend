<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeSection extends Model
{
    protected $fillable = ['key', 'label_ar', 'label_en', 'is_visible', 'sort_order', 'icon'];
    protected $casts = ['is_visible' => 'boolean', 'sort_order' => 'integer'];
}
