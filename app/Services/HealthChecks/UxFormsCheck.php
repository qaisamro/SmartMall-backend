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
            $forms = [];
            $pages = glob(base_path('../frontend/src/pages/**/*.jsx')) ?: [];
            foreach ($pages as $f) {
                $c = file_get_contents($f);
                $hasForm = str_contains($c, '<form') || (str_contains($c, 'useState') && str_contains($c, 'onSubmit'));
                $hasValidation = str_contains($c, 'validate') || str_contains($c, 'required') || str_contains($c, 'yup') || str_contains($c, 'zod');
                $hasError = str_contains($c, 'error') || str_contains($c, 'Error');
                if ($hasForm) {
                    $forms[] = [
                        'file' => str_replace(base_path('../frontend/'), '', $f),
                        'has_validation' => $hasValidation,
                        'has_error_display' => $hasError,
                        'lines' => substr_count($c, "\n"),
                    ];
                }
            }
            $details = [
                'forms_detected' => count($forms),
                'forms' => array_slice($forms, 0, 10),
                'total_pages' => count($pages),
                'sample' => array_slice(array_column($forms, 'file'), 0, 5),
            ];
            if (count($forms) === 0) return HealthResult::warning('لم يتم اكتشاف نماذج', $details);
            $withValidation = count(array_filter($forms, fn($f) => $f['has_validation']));
            $msg = "تم اكتشاف " . count($forms) . " نموذج (تحقق: $withValidation, بدون تحقق: " . (count($forms) - $withValidation) . ")";
            if ($withValidation < count($forms) / 2) return HealthResult::warning($msg . ' — بعض النماذج بدون تحقق', $details);
            return HealthResult::pass($msg, $details);
        } catch (\Throwable $e) {
            return HealthResult::failed('UX Forms فشل: ' . substr($e->getMessage(), 0, 200));
        }
    }
}
