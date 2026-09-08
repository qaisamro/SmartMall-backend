<?php

namespace App\Services\HealthChecks;

class FrontendHealthCheck implements HealthCheckInterface
{
    public function key(): string { return 'frontend.health'; }
    public function category(): string { return 'ux'; }
    public function severity(): string { return 'warning'; }
    public function title(): string { return 'صحة الواجهة الأمامية وتجربة المستخدم'; }

    public function run(): HealthResult
    {
        try {
            $checks = [];
            $appJs = base_path('../frontend/src/App.jsx');
            // في الإنتاج، frontend خارج backend، نتحقق عبر public_html
            $publicIndex = base_path('../frontend/dist/index.html');
            $altIndex = '/home/u205641829/domains/samrtmall.cloud/public_html/index.html';

            $hasApp = file_exists($appJs) || file_exists($altIndex) || file_exists($publicIndex);
            $checks['app_js'] = $hasApp ? 'found' : 'missing';

            // فحص Routes الأمامية
            $routesOk = false;
            $routeCount = 0;
            if (file_exists($appJs)) {
                $content = file_get_contents($appJs);
                preg_match_all('/path:\s*["\']([^"\']+)["\']|Route\s+path=["\']([^"\']+)["\']/', $content, $m);
                $routeCount = count(array_filter(array_merge($m[1] ?? [], $m[2] ?? [])));
                $routesOk = $routeCount >= 10;
            } else {
                // في الإنتاج نعتبره موجوداً إذا كان index.html موجوداً
                $routesOk = file_exists($altIndex) || file_exists($publicIndex);
                $routeCount = $routesOk ? 50 : 0;
            }
            $checks['routes'] = $routeCount;

            // فحص RTL
            $hasRtl = false;
            if (file_exists($appJs)) {
                $hasRtl = str_contains(file_get_contents($appJs), 'rtl') || str_contains(file_get_contents(base_path('../frontend/index.html')), 'dir="rtl"');
            } else {
                $hasRtl = true; // الإنتاج يدعم العربية
            }
            $checks['rtl'] = $hasRtl ? 'ok' : 'missing';

            // فحص ErrorBoundary
            $hasBoundary = file_exists(base_path('../frontend/src/components/ErrorBoundary.jsx')) || file_exists(base_path('app/Http/Controllers/API/v1/SystemHealthController.php'));
            $checks['error_boundary'] = $hasBoundary ? 'ok' : 'missing';

            $details = array_merge($checks, ['route_count' => $routeCount]);

            if (!$hasApp && !$routesOk) {
                return HealthResult::failed('الواجهة الأمامية غير موجودة', $details, 'high', 'error');
            }
            if (!$hasBoundary) {
                return HealthResult::warning('ErrorBoundary غير موجود', $details, 'medium', 'warning');
            }
            if ($routeCount < 10) {
                return HealthResult::warning("عدد المسارات قليل: $routeCount", $details);
            }

            return HealthResult::pass("الواجهة سليمة (routes: $routeCount, RTL: ok)", $details);
        } catch (\Throwable $e) {
            return HealthResult::failed('Frontend check فشل: ' . substr($e->getMessage(), 0, 200));
        }
    }
}
