<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\CacheService;

class ProductObserver
{
    public function saved(Product $product): void
    {
        CacheService::flushProduct($product->id);
    }

    public function deleted(Product $product): void
    {
        CacheService::flushProduct($product->id);
    }
}
