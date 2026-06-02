<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'mall_id', 'category_id', 'name_ar', 'name_en', 'description_ar',
        'description_en', 'price', 'discount_price', 'sku', 'barcode', 'qr_code',
        'stock_quantity', 'min_stock_alert', 'brand', 'image', 'is_active',
        'hide_stock_from_customer', 'shelf_location'
    ];

    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function shelves()
    {
        return $this->belongsToMany(Shelf::class, 'product_locations', 'product_id', 'shelf_id')
                    ->withPivot('level');
    }

    public function getCurrentPriceAttribute()
    {
        return $this->discount_price ?? $this->price;
    }
}
