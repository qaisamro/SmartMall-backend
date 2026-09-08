<?php

namespace App\Services\HealthChecks;

use Illuminate\Support\Facades\DB;

class DbConnectionCheck implements HealthCheckInterface
{
    public function key(): string { return 'db.connection'; }
    public function category(): string { return 'technical'; }
    public function severity(): string { return 'critical'; }
    public function title(): string { return 'اتصال قاعدة البيانات'; }

    public function run(): HealthResult
    {
        $start = microtime(true);
        try {
            DB::select('SELECT 1');
            $ms = (int) ((microtime(true) - $start) * 1000);
            if ($ms > 1000) return HealthResult::warning("الاتصال بطيء: {$ms}ms", ['duration_ms' => $ms], 'medium', 'warning');
            return HealthResult::pass("الاتصال سليم ({$ms}ms)", ['duration_ms' => $ms]);
        } catch (\Throwable $e) {
            return HealthResult::failed('فشل الاتصال: ' . substr($e->getMessage(), 0, 200), ['error' => $e->getMessage()], 'critical', 'critical');
        }
    }
}
