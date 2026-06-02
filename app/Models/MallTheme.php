<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MallTheme extends Model
{
    use HasFactory;

    protected $fillable = [
        'mall_id',
        'primary_color',
        'secondary_color',
        'accent_color',
        'background_color',
        'text_color',
        'dark_mode',
        'font_family',
        'border_radius',
    ];

    protected $casts = [
        'dark_mode' => 'boolean',
    ];

    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class);
    }
}
