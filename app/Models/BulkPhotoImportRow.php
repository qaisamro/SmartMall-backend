<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BulkPhotoImportRow extends Model
{
    protected $fillable = [
        'bulk_photo_import_id',
        'product_id',
        'barcode',
        'link_photo',
        'section_id',
        'section_name',
        'product_name',
        'mall_name',
        'status',
    ];

    public function import()
    {
        return $this->belongsTo(BulkPhotoImport::class, 'bulk_photo_import_id');
    }
}
