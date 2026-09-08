<?php

namespace App\Services\HealthChecks;

use Illuminate\Support\Facades\DB;

class QueueCheck implements HealthCheckInterface
{
    public function key(): string { return 'queue.health'; }
    public function category(): string { return 'technical'; }
    public function severity(): string { return 'warning'; }
    public function title(): string { return 'حالة طابور المهام (Queue)'; }
    public function run(): HealthResult
    {
        try {
            $size = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
            $details = ['queue_size' => $size, 'failed_jobs' => $failed, 'driver' => config('queue.default')];
            if ($failed > 20) return HealthResult::failed("Failed jobs مرتفع: $failed", $details, 'high', 'error');
            if ($failed > 0) return HealthResult::warning("يوجد $failed مهام فاشلة", $details, 'medium', 'warning');
            if ($size > 100) return HealthResult::warning("Queue متراكم: $size", $details);
            return HealthResult::pass("Queue سليم (size: $size, failed: $failed)", $details);
        } catch (\Throwable $e) {
            return HealthResult::failed('فحص Queue فشل: ' . substr($e->getMessage(), 0, 200));
        }
    }
}
