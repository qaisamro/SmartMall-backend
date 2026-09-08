<?php

namespace App\Services\HealthChecks;

use Illuminate\Support\Facades\Storage;

class StorageCheck implements HealthCheckInterface
{
    public function key(): string { return 'storage.writable'; }
    public function category(): string { return 'technical'; }
    public function severity(): string { return 'error'; }
    public function title(): string { return 'التخزين والكتابة'; }
    public function run(): HealthResult
    {
        try {
            $ok1 = is_writable(storage_path('logs'));
            $disk = Storage::disk('public');
            $test = 'health/' . uniqid() . '.txt';
            $disk->put($test, 'ok');
            $exists = $disk->exists($test);
            $disk->delete($test);
            if ($ok1 && $exists) return HealthResult::pass('التخزين قابل للكتابة', ['storage_logs_writable' => $ok1, 'public_disk' => $exists]);
            return HealthResult::warning('التخزين غير قابل للكتابة بالكامل', ['logs_writable' => $ok1, 'public_writable' => $exists]);
        } catch (\Throwable $e) {
            return HealthResult::failed('Storage فشل: ' . substr($e->getMessage(), 0, 200));
        }
    }
}
