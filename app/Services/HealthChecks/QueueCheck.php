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
            // تفاصيل آخر فشل
            if ($failed > 0) {
                try {
                    $last = DB::table('failed_jobs')->orderByDesc('failed_at')->first();
                    if ($last) {
                        $payload = json_decode($last->payload ?? '{}', true);
                        $details['last_failed_at'] = $last->failed_at;
                        $details['last_job'] = $payload['displayName'] ?? substr($last->payload, 0, 150);
                        $details['last_exception'] = substr($last->exception ?? '', 0, 300);
                        $details['last_uuid'] = $last->uuid ?? null;
                    }
                } catch (\Throwable $e) {
                    $details['last_failed_error'] = substr($e->getMessage(), 0, 100);
                }
            }
            if ($failed > 20) return HealthResult::failed("Failed jobs مرتفع: $failed (آخر فشل: " . ($details['last_failed_at'] ?? '—') . ")", $details, 'high', 'error');
            if ($failed > 0) return HealthResult::warning("يوجد $failed مهام فاشلة" . (isset($details['last_job']) ? " — آخر: " . substr($details['last_job'], 0, 40) : ""), $details, 'medium', 'warning');
            if ($size > 100) return HealthResult::warning("Queue متراكم: $size", $details);
            return HealthResult::pass("Queue سليم (size: $size, failed: $failed)", $details);
        } catch (\Throwable $e) {
            return HealthResult::failed('فحص Queue فشل: ' . substr($e->getMessage(), 0, 200));
        }
    }
}
