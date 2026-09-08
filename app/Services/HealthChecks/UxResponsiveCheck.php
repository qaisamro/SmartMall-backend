<?php

namespace App\Services\HealthChecks;

class UxResponsiveCheck implements HealthCheckInterface
{
    public function key(): string { return 'ux.responsive'; }
    public function category(): string { return 'ux'; }
    public function severity(): string { return 'info'; }
    public function title(): string { return 'التجاوب مع الأجهزة'; }
    public function run(): HealthResult
    {
        try {
            $css = @file_get_contents(base_path('../frontend/src/index.css'));
            $hasMedia = $css && str_contains($css, '@media');
            $hasGrid = $css && (str_contains($css, 'grid') || str_contains($css, 'flex'));
            $details = ['media_queries' => $hasMedia, 'grid_flex' => $hasGrid];
            if (!$hasMedia) return HealthResult::warning('لا يوجد استعلامات وسائط (media queries)', $details);
            return HealthResult::pass('التجاوب مدعوم (media queries + grid/flex)', $details);
        } catch (\Throwable $e) {
            return HealthResult::failed('UX Responsive فشل: ' . substr($e->getMessage(), 0, 200));
        }
    }
}
