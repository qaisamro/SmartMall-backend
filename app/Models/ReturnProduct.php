<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnProduct extends Model
{
    protected $table = 'returns';

    protected $fillable = [
        'order_id',
        'product_id',
        'mall_id',
        'user_id',
        'quantity',
        'amount',
        'reason',
        'status',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function mall()
    {
        return $this->belongsTo(Mall::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
