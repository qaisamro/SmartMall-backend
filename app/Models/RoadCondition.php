<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoadCondition extends Model
{
    protected $fillable = ['city', 'road_name', 'status', 'notes', 'is_active', 'order'];
    protected $casts = ['is_active' => 'boolean'];
}
