<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Mall extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id', 'name_ar', 'name_en', 'logo', 'description_ar', 
        'description_en', 'contact_email', 'contact_phone', 'is_active', 'status',
        'slug', 'qr_code_path', 'cover_image', 'description', 'location_arabic'
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(MallBranch::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function theme(): HasOne
    {
        return $this->hasOne(MallTheme::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
