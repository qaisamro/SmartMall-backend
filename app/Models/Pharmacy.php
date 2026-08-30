<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pharmacy extends Model
{
    protected $fillable = ['city', 'name', 'address', 'phone', 'is_on_duty', 'duty_date', 'lat', 'lng', 'is_active', 'order'];
    protected $casts = ['is_on_duty' => 'boolean', 'is_active' => 'boolean', 'duty_date' => 'date', 'lat' => 'float', 'lng' => 'float'];
}
