<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Mall;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminMonitorController extends Controller
{
    public function index()
    {
        $start = microtime(true);

        $dbOk = false;
        try {
            DB::select('SELECT 1');
            $dbOk = true;
        } catch (\Throwable) {}

        $queueSize = 0;
        try {
            $queueSize = DB::table('jobs')->count();
        } catch (\Throwable) {}

        $failedJobs = 0;
        try {
            $failedJobs = DB::table('failed_jobs')->count();
        } catch (\Throwable) {}

        $recentErrors = 0;
        try {
            $logPath = storage_path('logs/laravel.log');
            if (file_exists($logPath)) {
                $lines = file($logPath);
                $recentLines = array_slice($lines, -1000);
                foreach ($recentLines as $line) {
                    if (strpos($line, '.ERROR:') !== false) {
                        $recentErrors++;
                    }
                }
            }
        } catch (\Throwable) {}

        // Chart data: orders per day (last 14 days)
        $ordersChart = Order::selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total_amount) as revenue')
            ->where('created_at', '>=', now()->subDays(14))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Users per day (last 14 days)
        $usersChart = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(14))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $responseTime = (microtime(true) - $start) * 1000;

        return response()->json([
            'system' => [
                'response_time_ms' => round($responseTime, 1),
                'db_connected'     => $dbOk,
                'queue_size'       => $queueSize,
                'failed_jobs'      => $failedJobs,
                'recent_errors'    => $recentErrors,
                'php_version'      => PHP_VERSION,
                'laravel_version'  => app()->version(),
            ],
            'counts' => [
                'malls'         => Mall::count(),
                'products'      => Product::count(),
                'users'         => User::count(),
                'orders_today'  => Order::whereDate('created_at', today())->count(),
                'orders_month'  => Order::whereMonth('created_at', now()->month)->count(),
            ],
            'charts' => [
                'orders' => $ordersChart,
                'users'  => $usersChart,
            ],
            'cache' => [
                'driver' => config('cache.default'),
                'queue_driver' => config('queue.default'),
            ],
        ]);
    }
}
