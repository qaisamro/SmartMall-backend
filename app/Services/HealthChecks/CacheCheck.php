<?php

namespace App\Services\HealthChecks;

use Illuminate\Support\Facades\Cache;

class CacheCheck implements HealthCheckInterface
{
    public function key(): string { return 'cache.ping'; }
    public function category(): string { return 'technical'; }
    public function severity(): string { return 'warning'; }
    public function title(): string { return 'نظام التخزين المؤقت (Cache)'; }
    public function run(): HealthResult
    {
        try {
            $k = 'health:ping:' . uniqid();
            Cache::put($k, 'ok', 10);
            $v = Cache::get($k);
            Cache::forget($k);
            if ($v === 'ok') return HealthResult::pass('Cache يعمل (' . config('cache.default') . ')', ['driver' => config('cache.default')]);
            return HealthResult::warning('Cache لا يعيد القيمة', ['driver' => config('cache.default')]);
        } catch (\Throwable $e) {
            return HealthResult::failed('Cache فشل: ' . substr($e->getMessage(), 0, 200), null, 'medium', 'warning');
        }
    }
}
