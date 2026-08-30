<?php

namespace App\Observers;

use App\Models\Mall;
use App\Services\CacheService;

class MallObserver
{
    public function saved(Mall $mall): void
    {
        CacheService::flushMall($mall->id);
    }

    public function deleted(Mall $mall): void
    {
        CacheService::flushMall($mall->id);
    }
}
