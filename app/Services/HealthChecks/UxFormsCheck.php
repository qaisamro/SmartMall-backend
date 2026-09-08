<?php

namespace App\Services\HealthChecks;

class UxFormsCheck implements HealthCheckInterface
{
    public function key(): string { return 'ux.forms'; }
    public function category(): string { return 'ux'; }
    public function severity(): string { return 'warning'; }
    public function title(): string { return 'النماذج والتحقق'; }
    public function run(): HealthResult
    {
        try {
            $forms = 0;
            $pages = glob(base_path('../frontend/src/pages/**/*.jsx')) ?: [];
            foreach ($pages as $f) {
                $c = file_get_contents($f);
                if (str_contains($c, '<form') || str_contains($c, 'useState') && str_contains($c, 'onSubmit')) $forms++;
            }
            $details = ['forms_detected' => $forms];
            if ($forms === 0) return HealthResult::warning('لم يتم اكتشاف نماذج', $details);
            return HealthResult::pass("تم اكتشاف $forms نموذج", $details);
        } catch (\Throwable $e) {
            return HealthResult::failed('UX Forms فشل: ' . substr($e->getMessage(), 0, 200));
        }
    }
}
