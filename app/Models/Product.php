<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'mall_id', 'category_id', 'section_id', 'mall_section_id', 'name_ar', 'name_en', 'description_ar',
        'description_en', 'price', 'discount_price', 'stock_quantity', 'min_stock_alert',
        'sku', 'barcode', 'qr_code', 'unit',
        'brand', 'image', 'link_photo', 'is_active',
        'hide_stock_from_customer', 'shelf_location'
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'discount_price'   => 'decimal:2',
        'stock_quantity'   => 'integer',
        'min_stock_alert'  => 'integer',
        'is_active'        => 'boolean',
        'hide_stock_from_customer' => 'boolean',
    ];

    protected $appends = [
        'current_price',
    ];

    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function mallSection(): BelongsTo
    {
        return $this->belongsTo(MallSection::class, 'mall_section_id');
    }

    public function barcodeOverride(): BelongsTo
    {
        return $this->belongsTo(ProductBarcodeOverride::class, 'barcode', 'barcode');
    }

    public function shelves()
    {
        return $this->belongsToMany(Shelf::class, 'product_locations', 'product_id', 'shelf_id')
                    ->withPivot('level');
    }

    public function currentPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->discount_price ?? $this->price,
        );
    }
}
