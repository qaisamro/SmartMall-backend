<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'mall_id', 'status', 'total_amount', 'tax_amount', 'discount_amount', 'qr_code_path',
        'delivery_method', 'delivery_status', 'delivery_user_id', 'delivery_zone_id', 'delivery_fee', 'delivery_address', 'phone', 'general_notes', 'preparation_time', 'pending_order_id', 'approved_at', 'delivery_accepted_at', 'delivered_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mall(): BelongsTo
    {
        return $this->belongsTo(Mall::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function deliveryPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_user_id');
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }
}
