<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MallSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'mall_id', 'section_id', 'parent_id', 'name_ar', 'name_en',
        'icon', 'bg_image', 'sort_order', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MallSection::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MallSection::class, 'parent_id')
            ->orderByRaw('CASE WHEN sort_order = 0 THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderBy('name_ar');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'mall_section_id');
    }
}