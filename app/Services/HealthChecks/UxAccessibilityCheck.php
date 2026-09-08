<?php

namespace App\Services\HealthChecks;

class UxAccessibilityCheck implements HealthCheckInterface
{
    public function key(): string { return 'ux.accessibility'; }
    public function category(): string { return 'ux'; }
    public function severity(): string { return 'info'; }
    public function title(): string { return 'الوصولية الأساسية'; }
    public function run(): HealthResult
    {
        try {
            $issues = [];
            $pages = glob(base_path('../frontend/src/pages/**/*.jsx')) ?: [];
            foreach (array_slice($pages, 0, 5) as $f) {
                $c = file_get_contents($f);
                if (str_contains($c, '<img') && !str_contains($c, 'alt=')) $issues[] = basename($f) . ': img بدون alt';
            }
            $details = ['checked_pages' => 5, 'issues' => $issues];
            if (!empty($issues)) return HealthResult::warning('مشاكل وصولية: ' . implode(', ', array_slice($issues, 0, 2)), $details);
            return HealthResult::pass('الوصولية الأساسية سليمة', $details);
        } catch (\Throwable $e) {
            return HealthResult::failed('UX A11y فشل: ' . substr($e->getMessage(), 0, 200));
        }
    }
}
