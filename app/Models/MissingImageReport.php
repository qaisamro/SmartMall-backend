<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MissingImageReport extends Model
{
    protected $table = 'missing_image_reports';

    protected $fillable = [
        'user_id',
        'barcode',
        'name',
        'type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
