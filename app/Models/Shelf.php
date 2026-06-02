<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Shelf extends Model
{
    use HasFactory;

    protected $fillable = [
        'mall_id', 'branch_id', 'name', 'section', 'map_coordinates'
    ];

    protected $casts = [
        'map_coordinates' => 'array',
    ];

    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class, 'mall_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(MallBranch::class, 'branch_id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_locations', 'shelf_id', 'product_id')
                    ->withPivot('level');
    }
}
