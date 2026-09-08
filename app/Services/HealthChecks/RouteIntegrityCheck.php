<?php

namespace App\Services\HealthChecks;

use Illuminate\Support\Facades\Route;

class RouteIntegrityCheck implements HealthCheckInterface
{
    public function key(): string { return 'route.integrity'; }
    public function category(): string { return 'technical'; }
    public function severity(): string { return 'warning'; }
    public function title(): string { return 'سلامة المسارات (Routes)'; }
    public function run(): HealthResult
    {
        try {
            $routes = Route::getRoutes();
            $total = count($routes);
            $broken = [];
            foreach ($routes as $r) {
                $action = $r->getActionName();
                if ($action === 'Closure' || str_starts_with($action, 'Illuminate')) continue;
                if (!str_contains($action, '@')) continue;
                [$ctrl, $method] = explode('@', $action);
                if (!class_exists($ctrl)) $broken[] = "$action (controller missing)";
                elseif (!method_exists($ctrl, $method)) $broken[] = "$action (method missing)";
                if (count($broken) >= 5) break;
            }
            if (empty($broken)) return HealthResult::pass("جميع المسارات سليمة ($total route)", ['total_routes' => $total]);
            return HealthResult::warning('مسارات مكسورة: ' . implode(', ', $broken), ['broken' => $broken, 'total' => $total]);
        } catch (\Throwable $e) {
            return HealthResult::failed('Route check فشل: ' . substr($e->getMessage(), 0, 200));
        }
    }
}
