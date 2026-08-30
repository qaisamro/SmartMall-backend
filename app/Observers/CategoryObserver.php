<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\CacheService;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        CacheService::flushCategories($category->mall_id);
        CacheService::flushMall($category->mall_id);
    }

    public function deleted(Category $category): void
    {
        CacheService::flushCategories($category->mall_id);
        CacheService::flushMall($category->mall_id);
    }
}
