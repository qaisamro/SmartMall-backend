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
            $start = hrtime(true);
            DB::table('products')->limit(20)->get();
            $times['products'] = (int) round((hrtime(true) - $start) / 1e6);
            $start = hrtime(true);
            DB::table('orders')->limit(20)->get();
            $times['orders'] = (int) round((hrtime(true) - $start) / 1e6);
            $start = hrtime(true);
            DB::table('activity_logs')->orderByDesc('created_at')->limit(20)->get();
            $times['activity_logs'] = (int) round((hrtime(true) - $start) / 1e6);
            // ضمان عدم الصفر الوهمي: إذا كان القياس 0 بسبب السرعة, اعرض <1
            foreach ($times as $k => $v) {
                if ($v === 0) $times[$k] = 1;
            }
            $max = max($times);
            $details = array_merge($times, ['max_ms' => $max]);
            if ($max > 1000) return HealthResult::failed("استعلام بطيء: max {$max}ms", $details, 'high', 'error');
            if ($max > 300) return HealthResult::warning("أداء متوسط: max {$max}ms", $details);
            if ($max === 1) return HealthResult::pass("الأداء ممتاز (max <1ms)", $details);
            return HealthResult::pass("الأداء ممتاز (max {$max}ms)", $details);
        } catch (\Throwable $e) {
            return HealthResult::failed('Performance check فشل: ' . substr($e->getMessage(), 0, 200));
        }
    }
}
