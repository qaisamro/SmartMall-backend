<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    protected $fillable = [
        'user_id', 'title', 'description', 'order_id', 'mall_id',
        'status', 'admin_response'
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function mall()
    {
        return $this->belongsTo(Mall::class);
    }

    public function messages()
    {
        return $this->hasMany(ComplaintMessage::class);
    }
}
