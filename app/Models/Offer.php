<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    protected $fillable = [
        'mall_id', 'product_id', 'offer_price', 'offer_quantity', 'tiers', 'title_ar', 'title_en', 'description_ar', 'description_en',
        'image', 'type', 'starts_at', 'ends_at', 'is_active'
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'offer_price' => 'decimal:2',
            'offer_quantity' => 'integer',
            'tiers' => 'array',
        ];
    }

    public function mall()
    {
        return $this->belongsTo(Mall::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
