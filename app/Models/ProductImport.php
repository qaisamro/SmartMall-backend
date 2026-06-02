<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mall_id',
        'file_name',
        'file_path',
        'status',
        'total_rows',
        'valid_rows',
        'imported_rows',
        'failed_rows',
        'duplicate_rows',
        'options',
        'errors',
        'summary',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'options' => 'array',
        'errors' => 'array',
        'summary' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class);
    }
}
