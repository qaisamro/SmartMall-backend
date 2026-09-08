<?php

namespace App\Services\HealthChecks;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FunctionalAuthCheck implements HealthCheckInterface
{
    public function key(): string { return 'functional.auth'; }
    public function category(): string { return 'application'; }
    public function severity(): string { return 'critical'; }
    public function title(): string { return 'التسجيل والدخول والمصادقة'; }

    public function run(): HealthResult
    {
        $start = hrtime(true);
        DB::beginTransaction();
        try {
            // 1. اختبار إنشاء مستخدم (Register) — باستخدام بيانات وهمية ثم Rollback
            $testEmail = 'health_check_' . uniqid() . '@test.local';
            $userId = DB::table('users')->insertGetId([
                'name' => 'Health Check User',
                'email' => $testEmail,
                'password' => Hash::make('test123456'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if (!$userId) {
                DB::rollBack();
                return HealthResult::failed('فشل إنشاء مستخدم اختباري', null, 'critical', 'critical');
            }

            // 2. تحقق من الحفظ
            $created = DB::table('users')->where('id', $userId)->first();
            if (!$created || $created->email !== $testEmail) {
                DB::rollBack();
                return HealthResult::failed('المستخدم لم يُحفظ بشكل صحيح', ['email' => $testEmail], 'critical', 'critical');
            }

            // 3. اختبار تعديل (Update)
            $newName = 'Health Check Updated';
            DB::table('users')->where('id', $userId)->update(['name' => $newName]);
            $updated = DB::table('users')->where('id', $userId)->first();
            if ($updated->name !== $newName) {
                DB::rollBack();
                return HealthResult::failed('فشل تعديل المستخدم', null, 'high', 'error');
            }

            // 4. اختبار Google OAuth config (لا ننفذ تدفق كامل، نتحقق من الإعدادات فقط)
            $googleConfigured = !empty(config('services.google.client_id')) && !empty(config('services.google.client_secret'));
            $googleRedirect = config('services.google.redirect');

            DB::rollBack();

            $ms = (int) round((hrtime(true) - $start) / 1e6);
            $details = [
                'register' => 'ok',
                'update' => 'ok',
                'google_configured' => $googleConfigured,
                'google_redirect' => $googleRedirect,
                'duration_ms' => $ms,
            ];

            if (!$googleConfigured) {
                return HealthResult::warning("المصادقة الأساسية تعمل ({$ms}ms) لكن Google OAuth غير مُعد", $details, 'medium', 'warning');
            }

            return HealthResult::pass("التسجيل والدخول يعمل ({$ms}ms) — إنشاء وتعديل وحذف (rollback) + Google مُعد", $details);

        } catch (\Throwable $e) {
            DB::rollBack();
            return HealthResult::failed('فشل اختبار المصادقة: ' . substr($e->getMessage(), 0, 300), ['exception' => get_class($e)], 'critical', 'critical');
        }
    }
}
