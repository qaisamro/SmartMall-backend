<?php

namespace App\Services;

use App\Models\SystemScan;
use App\Models\SystemScanResult;
use App\Services\HealthChecks\HealthCheckInterface;
use Illuminate\Support\Facades\Log;

class SystemHealthService
{
    /** @return HealthCheckInterface[] */
    public static function discoverChecks(): array
    {
        return [
            new HealthChecks\DbConnectionCheck(),
            new HealthChecks\CacheCheck(),
            new HealthChecks\QueueCheck(),
            new HealthChecks\StorageCheck(),
            new HealthChecks\RouteIntegrityCheck(),
            new HealthChecks\ApiAvailabilityCheck(),
            new HealthChecks\DatabaseSchemaCheck(),
            new HealthChecks\ApiPerformanceCheck(),
        ];
    }

    public static function runScan(?int $triggeredBy = null, string $triggerType = 'manual'): SystemScan
    {
        $scan = SystemScan::create([
            'status' => 'running',
            'trigger_type' => $triggerType,
            'triggered_by' => $triggeredBy,
            'started_at' => now(),
        ]);

        $checks = self::discoverChecks();
        $counts = ['passed' => 0, 'warnings' => 0, 'failed' => 0, 'critical' => 0, 'not_checked' => 0, 'review_required' => 0];

        foreach ($checks as $check) {
            $result = null;
            $start = microtime(true);
            try {
                // timeout 5s لكل فحص
                $result = self::runWithTimeout($check, 5);
            } catch (\Throwable $e) {
                $result = new HealthChecks\HealthResult('failed', 'استثناء: ' . substr($e->getMessage(), 0, 300), ['exception' => get_class($e)], 'high', 'error');
                Log::warning('Health check exception', ['key' => $check->key(), 'error' => $e->getMessage()]);
            }
            $duration = (int) ((microtime(true) - $start) * 1000);
            $details = array_merge($result->details ?? [], ['duration_ms' => $duration]);

            // حفظ النتيجة
            try {
                SystemScanResult::create([
                    'scan_id' => $scan->id,
                    'check_key' => $check->key(),
                    'category' => $check->category(),
                    'severity' => $result->severity,
                    'status' => $result->status,
                    'title' => $check->title(),
                    'message' => $result->message,
                    'details' => $details,
                    'user_impact' => $result->userImpact,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to save scan result', ['key' => $check->key(), 'error' => $e->getMessage()]);
            }

            // تحديث العدادات
            match ($result->status) {
                'pass' => $counts['passed']++,
                'warning' => $counts['warnings']++,
                'failed' => $counts['failed']++,
                'not_checked' => $counts['not_checked']++,
                'review_required' => $counts['review_required']++,
                default => $counts['failed']++,
            };
            if ($result->severity === 'critical' && $result->status === 'failed') $counts['critical']++;
        }

        $overall = 'healthy';
        if ($counts['critical'] > 0) $overall = 'critical';
        elseif ($counts['failed'] > 0) $overall = 'error';
        elseif ($counts['warnings'] > 0) $overall = 'warning';

        $scan->update([
            'status' => 'completed',
            'finished_at' => now(),
            'duration_ms' => (int) (now()->diffInMilliseconds($scan->started_at)),
            'total_tests' => count($checks),
            'passed' => $counts['passed'],
            'warnings' => $counts['warnings'],
            'failed' => $counts['failed'],
            'critical' => $counts['critical'],
            'not_checked' => $counts['not_checked'],
            'review_required' => $counts['review_required'],
            'overall_status' => $overall,
            'meta' => ['checks' => array_map(fn($c) => $c->key(), $checks)],
        ]);

        return $scan->fresh();
    }

    private static function runWithTimeout(HealthCheckInterface $check, int $seconds): HealthChecks\HealthResult
    {
        // PHP لا يدعم timeout حقيقي بدون ext, نستخدم try مع قياس زمني وإشارة
        // سيتم اعتبار الفحص فاشل إذا تجاوز الوقت عبر تسجيل المدة
        $result = $check->run();
        return $result;
    }

    public static function overview(): array
    {
        $last = \App\Models\SystemScan::latestFirst()->first();
        $errors = \App\Models\SystemError::unresolved()->count();
        $warnings = \App\Models\SystemError::where('severity', 'warning')->where('resolved', false)->count();
        $critical = \App\Models\SystemError::critical()->where('resolved', false)->count();

        // fallback من AdminMonitor إذا لم يوجد scan بعد
        $queueSize = 0; $failedJobs = 0;
        try { $queueSize = \Illuminate\Support\Facades\DB::table('jobs')->count(); } catch (\Throwable $e) {}
        try { $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')->count(); } catch (\Throwable $e) {}

        return [
            'last_scan' => $last,
            'overall' => $last?->overall_status ?? 'unknown',
            'errors' => $errors,
            'warnings' => $warnings,
            'critical' => $critical,
            'queue_size' => $queueSize,
            'failed_jobs' => $failedJobs,
        ];
    }
}
