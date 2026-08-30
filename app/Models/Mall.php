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

    protected static function boot()
    {
        parent::boot();

        // Cascade delete all related data when a mall is deleted
        static::deleting(function ($mall) {
            // Delete all products belonging to this mall
            $mall->products()->delete();
            // Delete all categories belonging to this mall
            $mall->categories()->delete();
            // Delete theme if exists
            $mall->theme()->delete();
            // Delete subscription if exists
            $mall->subscription()->delete();
        });
    }

    protected $fillable = [
        'owner_id', 'name_ar', 'name_en', 'logo', 'description_ar', 
        'description_en', 'contact_email', 'contact_phone', 'is_active', 'status',
        'slug', 'qr_code_path', 'cover_image', 'description', 'location_arabic', 'type',
        'latitude', 'longitude', 'sort_order', 'delivery_enabled', 'offer_limit', 'total_offers_used',
        'enable_quantity_system', 'suspended_at', 'suspended_reason',
        'google_drive_backup_file_id', 'google_drive_backup_filename', 'google_drive_backup_at',
        'open_time', 'close_time'
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'enable_quantity_system' => 'boolean',
            'delivery_enabled' => 'boolean',
            'suspended_at' => 'datetime',
            'google_drive_backup_at' => 'datetime',
        ];
    }

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

    public function mallSections(): HasMany
    {
        return $this->hasMany(MallSection::class);
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
