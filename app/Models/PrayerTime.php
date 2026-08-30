<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrayerTime extends Model
{
    protected $fillable = ['city', 'date', 'fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha', 'is_active'];
    protected $casts = ['date' => 'date', 'is_active' => 'boolean'];
}
