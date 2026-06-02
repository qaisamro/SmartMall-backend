<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MallBranch extends Model
{
    use HasFactory;

    protected $fillable = [
        'mall_id', 'name_ar', 'name_en', 'address_ar', 'address_en', 
        'latitude', 'longitude', 'phone', 'is_main'
    ];

    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class);
    }

    public function shelves(): HasMany
    {
        return $this->hasMany(Shelf::class, 'branch_id');
    }
}
