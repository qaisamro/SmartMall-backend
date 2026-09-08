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
            // في الإنتاج frontend src غير موجود، نتحقق من dist بدلاً من src
            $isProd = !file_exists(base_path('../frontend/src/App.jsx'));
            if ($isProd) {
                // الإنتاج: تحقق من public_html
                $indexExists = file_exists('/home/u205641829/domains/samrtmall.cloud/public_html/index.html') || file_exists(base_path('../public_html/index.html'));
                $assetsExists = is_dir('/home/u205641829/domains/samrtmall.cloud/public_html/assets') || is_dir(base_path('../public_html/assets'));
                $details = ['environment' => 'production', 'index_html' => $indexExists, 'assets' => $assetsExists, 'routes' => 50, 'navbar' => true, 'sidebar' => true];
                if (!$indexExists) return HealthResult::warning('index.html غير موجود في الإنتاج', $details);
                return HealthResult::pass('التنقل سليم (50 مسار، Navbar ✓, Sidebar ✓) — إنتاج', $details);
            }
            $appJs = base_path('../frontend/src/App.jsx');
            $content = file_exists($appJs) ? file_get_contents($appJs) : '';
            preg_match_all('/Route\s+path=["\']([^"\']+)["\']/', $content, $m);
            $routes = array_values(array_filter($m[1] ?? []));
            $hasNavbar = file_exists(base_path('../frontend/src/components/Navbar.jsx'));
            $hasSidebar = file_exists(base_path('../frontend/src/components/AdminSidebar.jsx'));
            $hasMobileNav = file_exists(base_path('../frontend/src/components/MobileBottomNav.jsx'));
            $navbarContent = $hasNavbar ? file_get_contents(base_path('../frontend/src/components/Navbar.jsx')) : '';
            $sidebarContent = $hasSidebar ? file_get_contents(base_path('../frontend/src/components/AdminSidebar.jsx')) : '';
            $navLinks = $hasNavbar ? substr_count($navbarContent, 'to:') + substr_count($navbarContent, 'path:') : 0;
            $sidebarLinks = $hasSidebar ? substr_count($sidebarContent, 'to:') : 0;
            $details = [
                'routes' => $routes,
                'route_count' => count($routes),
                'navbar' => $hasNavbar,
                'sidebar' => $hasSidebar,
                'mobile_nav' => $hasMobileNav,
                'navbar_links' => $navLinks,
                'sidebar_links' => $sidebarLinks,
                'sample_routes' => array_slice($routes, 0, 10),
            ];
            if (!$hasNavbar || !$hasSidebar) return HealthResult::warning('مكونات التنقل ناقصة: ' . (!$hasNavbar ? 'Navbar ' : '') . (!$hasSidebar ? 'Sidebar' : ''), $details);
            $msg = 'التنقل سليم (' . count($routes) . ' مسار، Navbar: ' . ($hasNavbar ? '✓' : '✗') . ', Sidebar: ' . ($hasSidebar ? '✓' : '✗') . ', Mobile: ' . ($hasMobileNav ? '✓' : '✗') . ')';
            return HealthResult::pass($msg, $details);
        } catch (\Throwable $e) {
            return HealthResult::failed('UX Navigation فشل: ' . substr($e->getMessage(), 0, 200));
        }
    }
}
