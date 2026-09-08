<?php

namespace App\Services\HealthChecks;

class UxNavigationCheck implements HealthCheckInterface
{
    public function key(): string { return 'ux.navigation'; }
    public function category(): string { return 'ux'; }
    public function severity(): string { return 'warning'; }
    public function title(): string { return 'التنقل والقوائم'; }
    public function run(): HealthResult
    {
        try {
            $appJs = base_path('../frontend/src/App.jsx');
            $content = file_exists($appJs) ? file_get_contents($appJs) : '';
            preg_match_all('/Route\s+path=["\']([^"\']+)["\']/', $content, $m);
            $routes = array_filter($m[1] ?? []);
            $hasNavbar = file_exists(base_path('../frontend/src/components/Navbar.jsx'));
            $hasSidebar = file_exists(base_path('../frontend/src/components/AdminSidebar.jsx'));
            $details = ['routes' => count($routes), 'navbar' => $hasNavbar, 'sidebar' => $hasSidebar];
            if (!$hasNavbar || !$hasSidebar) return HealthResult::warning('مكونات التنقل ناقصة', $details);
            return HealthResult::pass('التنقل سليم (' . count($routes) . ' مسار)', $details);
        } catch (\Throwable $e) {
            return HealthResult::failed('UX Navigation فشل: ' . substr($e->getMessage(), 0, 200));
        }
    }
}
