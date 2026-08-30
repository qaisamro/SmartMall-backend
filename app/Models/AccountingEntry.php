<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingEntry extends Model
{
    protected $fillable = [
        'mall_id',
        'amount',
        'type',
        'description',
        'entry_date',
    ];

    public function mall()
    {
        return $this->belongsTo(Mall::class);
    }
}
