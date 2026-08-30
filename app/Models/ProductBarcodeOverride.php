<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBarcodeOverride extends Model
{
    protected $fillable = [
        'barcode',
        'link_photo',
        'section_id',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}
