<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleDriveToken extends Model
{
    protected $fillable = [
        'refresh_token',
        'access_token',
        'expires_at',
        'email',
        'token_type',
        'scope',
    ];

    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'access_token' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * الحصول على السجل الوحيد (singleton). يتم استخدام صف واحد فقط لحساب smartmallps2026@gmail.com
     */
    public static function current(): ?self
    {
        return static::latest('id')->first();
    }
}
