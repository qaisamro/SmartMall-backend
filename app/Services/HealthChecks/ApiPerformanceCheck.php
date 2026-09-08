<?php

namespace App\Services\HealthChecks;

use Illuminate\Support\Facades\DB;

class ApiPerformanceCheck implements HealthCheckInterface
{
    public function key(): string { return 'api.performance'; }
    public function category(): string { return 'performance'; }
    public function severity(): string { return 'warning'; }
    public function title(): string { return 'أداء الاستجابة'; }
    public function run(): HealthResult
    {
        try {
            $times = [];
            $start = microtime(true);
            DB::table('products')->limit(20)->get();
            $times['products'] = (int) ((microtime(true) - $start) * 1000);
            $start = microtime(true);
            DB::table('orders')->limit(20)->get();
            $times['orders'] = (int) ((microtime(true) - $start) * 1000);
            $start = microtime(true);
            DB::table('activity_logs')->orderByDesc('created_at')->limit(20)->get();
            $times['activity_logs'] = (int) ((microtime(true) - $start) * 1000);
            $max = max($times);
            $details = array_merge($times, ['max_ms' => $max]);
            if ($max > 1000) return HealthResult::failed("استعلام بطيء: max {$max}ms", $details, 'high', 'error');
            if ($max > 300) return HealthResult::warning("أداء متوسط: max {$max}ms", $details);
            return HealthResult::pass("الأداء ممتاز (max {$max}ms)", $details);
        } catch (\Throwable $e) {
            return HealthResult::failed('Performance check فشل: ' . substr($e->getMessage(), 0, 200));
        }
    }
}
