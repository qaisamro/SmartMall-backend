<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubBarcode extends Model
{
    protected $fillable = [
        'mall_id',
        'sub_barcode',
        'main_barcode',
        'product_id',
    ];

    public function mall()
    {
        return $this->belongsTo(Mall::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
