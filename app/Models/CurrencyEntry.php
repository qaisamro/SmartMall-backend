<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyEntry extends Model
{
    protected $fillable = ['city', 'code', 'name', 'buy_rate', 'sell_rate', 'is_active'];
    protected $casts = ['is_active' => 'boolean', 'buy_rate' => 'float', 'sell_rate' => 'float'];
}
