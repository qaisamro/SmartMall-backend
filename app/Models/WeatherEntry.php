<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherEntry extends Model
{
    protected $fillable = ['city', 'temperature', 'condition', 'icon', 'humidity', 'wind_speed', 'forecast_short', 'is_active', 'fetched_at'];
    protected $casts = ['is_active' => 'boolean', 'fetched_at' => 'datetime', 'temperature' => 'float', 'humidity' => 'float', 'wind_speed' => 'float'];
}
