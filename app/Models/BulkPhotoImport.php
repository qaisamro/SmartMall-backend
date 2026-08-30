<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BulkPhotoImport extends Model
{
    protected $fillable = [
        'user_id',
        'file_name',
        'updated_count',
        'skipped_count',
        'errors',
    ];

    protected $casts = [
        'errors' => 'array',
    ];

    public function rows()
    {
        return $this->hasMany(BulkPhotoImportRow::class);
    }
}
