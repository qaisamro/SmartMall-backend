<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Mall;
use Illuminate\Support\Facades\Cache;

class CacheService
{
    private const TTL_DAY = 86400;
    private const TTL_HOUR = 3600;

    // ---- Keys ----
    public static function keyActiveMalls(): string { return 'malls:active'; }
    public static function keyMallCategories(int $mallId): string { return "categories:mall:{$mallId}"; }
    public static function keyMallProducts(int $mallId, int $page = 1): string { return "products:mall:{$mallId}:page:{$page}"; }
    public static function keyProduct(int $id): string { return "product:{$id}"; }
    public static function keySettings(): string { return 'settings:all'; }
    public static function keyHomeWidgets(): string { return 'home:widgets'; }
    public static function keyActiveOffers(): string { return 'offers:active'; }

    // ---- Active Malls ----
    public static function getActiveMalls(): mixed
    {
        return Cache::remember(self::keyActiveMalls(), self::TTL_HOUR, function () {
            return Mall::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name_ar', 'name_en', 'slug', 'logo', 'type', 'description_ar']);
        });
    }

    // ---- Mall Categories ----
    public static function getMallCategories(int $mallId): mixed
    {
        return Cache::remember(self::keyMallCategories($mallId), self::TTL_HOUR, function () use ($mallId) {
            return Category::where('mall_id', $mallId)
                ->whereNull('parent_id')
                ->with('children:id,name_ar,name_en,parent_id')
                ->orderBy('order')
                ->get(['id', 'name_ar', 'name_en', 'icon', 'order', 'parent_id']);
        });
    }

    // ---- Flush helpers ----
    public static function flushMall(int $mallId): void
    {
        Cache::forget(self::keyActiveMalls());
        Cache::forget(self::keyMallCategories($mallId));
    }

    public static function flushProduct(int $id): void
    {
        Cache::forget(self::keyProduct($id));
    }

    public static function flushCategories(int $mallId): void
    {
        Cache::forget(self::keyMallCategories($mallId));
    }

    public static function flushSettings(): void
    {
        Cache::forget(self::keySettings());
    }
}
