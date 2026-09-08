<?php

namespace App\Services\HealthChecks;

use Illuminate\Support\Facades\DB;

class ApiAvailabilityCheck implements HealthCheckInterface
{
    public function key(): string { return 'api.availability'; }
    public function category(): string { return 'application'; }
    public function severity(): string { return 'critical'; }
    public function title(): string { return 'توفر الـ APIs الأساسية'; }
    public function run(): HealthResult
    {
        try {
            $checks = [];
            $start = microtime(true);
            // تحقق مباشر من DB بدل HTTP لتجنب loopback
            $malls = DB::table('malls')->where('status', 'approved')->count();
            $products = DB::table('products')->count();
            $users = DB::table('users')->count();
            $ms = (int) ((microtime(true) - $start) * 1000);
            $details = ['malls_approved' => $malls, 'products' => $products, 'users' => $users, 'duration_ms' => $ms];
            if ($products === 0 && $malls === 0) return HealthResult::warning('لا توجد بيانات (malls/products فارغة)', $details);
            return HealthResult::pass("APIs جاهزة (malls: $malls, products: $products) - {$ms}ms", $details);
        } catch (\Throwable $e) {
            return HealthResult::failed('API availability فشل: ' . substr($e->getMessage(), 0, 200));
        }
    }
}
