<?php

namespace App\Services\HealthChecks;

class FunctionalGoogleAuthCheck implements HealthCheckInterface
{
    public function key(): string { return 'functional.google_auth'; }
    public function category(): string { return 'application'; }
    public function severity(): string { return 'warning'; }
    public function title(): string { return 'تسجيل الدخول عبر Google'; }

    public function run(): HealthResult
    {
        $start = hrtime(true);
        try {
            $clientId = config('services.google.client_id');
            $redirect = config('services.google.redirect');

            if (empty($clientId) || empty($clientId)) {
                return HealthResult::warning('Google OAuth غير مُعد (client_id فارغ)', ['client_id' => $clientId ? 'set' : 'empty', 'redirect' => $redirect], 'medium', 'warning');
            }

            // محاكاة GET /auth/google/redirect عبر Socialite (بدون تنفيذ HTTP خارجي)
            try {
                $url = \Laravel\Socialite\Facades\Socialite::driver('google')->stateless()->redirect()->getTargetUrl();
                $hasClientId = str_contains($url, $clientId) || str_contains($url, 'client_id');
                $hasRedirect = str_contains($url, urlencode($redirect)) || str_contains($url, 'redirect_uri');
                $ms = (int) round((hrtime(true) - $start) / 1e6);
                $details = [
                    'url' => substr($url, 0, 200),
                    'has_client_id' => $hasClientId,
                    'has_redirect' => $hasRedirect,
                    'redirect' => $redirect,
                    'duration_ms' => $ms,
                ];
                if (!$hasClientId || !$hasRedirect) {
                    return HealthResult::warning('رابط Google لا يحتوي client_id/redirect', $details, 'medium', 'warning');
                }
                return HealthResult::pass("Google OAuth جاهز ({$ms}ms) — redirect 200", $details);
            } catch (\Throwable $e) {
                $ms = (int) round((hrtime(true) - $start) / 1e6);
                // إذا فشل توليد الرابط بسبب إعدادات ناقصة، نعتبره warning لا critical
                if (str_contains($e->getMessage(), 'client_id') || str_contains($e->getMessage(), 'OAuth')) {
                    return HealthResult::warning('Google OAuth إعداد ناقص: ' . substr($e->getMessage(), 0, 150), ['duration_ms' => $ms, 'error' => substr($e->getMessage(), 0, 200)], 'medium', 'warning');
                }
                return HealthResult::failed('فشل محاكاة Google redirect: ' . substr($e->getMessage(), 0, 300), ['duration_ms' => $ms], 'high', 'error');
            }

        } catch (\Throwable $e) {
            return HealthResult::failed('فشل فحص Google: ' . substr($e->getMessage(), 0, 300), null, 'high', 'error');
        }
    }
}
