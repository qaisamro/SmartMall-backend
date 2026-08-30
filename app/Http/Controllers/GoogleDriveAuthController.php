<?php

namespace App\Http\Controllers;

use App\Models\GoogleDriveToken;
use App\Services\GoogleDriveService;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleDriveAuthController extends Controller
{
    protected function buildOAuthClient(): GoogleClient
    {
        $clientId = config('services.google_drive.client_id');
        $clientSecret = config('services.google_drive.client_secret');
        $redirectUri = config('services.google_drive.redirect_uri');

        if (empty($clientId) || empty($clientSecret) || empty($redirectUri)) {
            throw new \RuntimeException('Google Drive OAuth credentials are not configured. Check GOOGLE_DRIVE_OAUTH_CLIENT_ID / SECRET / REDIRECT_URI in .env');
        }

        $client = new GoogleClient();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->setScopes([GoogleDrive::DRIVE]);
        $client->setAccessType('offline');
        $client->setIncludeGrantedScopes(true);
        // prompt consent to ensure refresh_token is returned
        if (method_exists($client, 'setPrompt')) {
            $client->setPrompt('consent');
        } elseif (method_exists($client, 'setApprovalPrompt')) {
            $client->setApprovalPrompt('force');
        }
        $client->setApplicationName('SmartMall Backup');

        return $client;
    }

    /**
     * GET /google-drive/connect
     * يحول المستخدم إلى Google OAuth مع access_type=offline & prompt=consent
     */
    public function connect(Request $request)
    {
        try {
            $client = $this->buildOAuthClient();
            // force consent to get refresh_token even if already granted
            $authUrl = $client->createAuthUrl();
            // Ensure offline & consent are in URL (some versions ignore setPrompt)
            if (!str_contains($authUrl, 'access_type=offline')) {
                $authUrl .= (str_contains($authUrl, '?') ? '&' : '?') . 'access_type=offline&prompt=consent&include_granted_scopes=true';
            } elseif (!str_contains($authUrl, 'prompt=consent')) {
                $authUrl .= '&prompt=consent';
            }

            Log::info('[GoogleDrive][OAuth] redirect to Google', ['url' => strtok($authUrl, '?')]);

            return redirect()->away($authUrl);
        } catch (\Throwable $e) {
            Log::error('[GoogleDrive][OAuth] connect failed', ['error' => $e->getMessage()]);
            return response($this->errorHtml('فشل إنشاء رابط Google OAuth', $e->getMessage()), 500)
                ->header('Content-Type', 'text/html; charset=utf-8');
        }
    }

    /**
     * GET /google-drive/callback?code=...&scope=...
     */
    public function callback(Request $request)
    {
        $error = $request->query('error');
        if ($error) {
            $desc = $request->query('error_description', $error);
            Log::warning('[GoogleDrive][OAuth] user denied or error', ['error' => $error, 'desc' => $desc]);
            return response($this->errorHtml('تم رفض الإذن أو حدث خطأ', htmlspecialchars($desc)), 400)
                ->header('Content-Type', 'text/html; charset=utf-8');
        }

        $code = $request->query('code');
        if (!$code) {
            return response($this->errorHtml('رمز المصادقة مفقود', 'No code returned from Google'), 400)
                ->header('Content-Type', 'text/html; charset=utf-8');
        }

        try {
            $client = $this->buildOAuthClient();
            $tokenData = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($tokenData['error'])) {
                $msg = $tokenData['error_description'] ?? $tokenData['error'];
                throw new \RuntimeException('Google OAuth error: ' . $msg);
            }

            if (empty($tokenData['access_token'])) {
                throw new \RuntimeException('لم يتم الحصول على access_token من Google');
            }

            // احتفظ بالـ refresh_token القديم إذا لم يُرجع الجديد (يحدث عند إعادة الموافقة بدون prompt)
            $existing = GoogleDriveToken::current();
            $refreshToken = $tokenData['refresh_token'] ?? $existing?->refresh_token;

            // إذا لم يوجد refresh_token إطلاقاً، نطلب من المستخدم إعادة الموافقة مع prompt=consent
            if (empty($refreshToken)) {
                throw new \RuntimeException('لم يتم الحصول على refresh_token. حاول إلغاء وصول SmartMall من https://myaccount.google.com/permissions ثم افتح /google-drive/connect مرة أخرى مع prompt=consent.');
            }

            $expiresAt = null;
            if (isset($tokenData['expires_in'])) {
                $expiresAt = now()->addSeconds((int) $tokenData['expires_in']);
            } elseif (isset($tokenData['created']) && isset($tokenData['expires_in'])) {
                $expiresAt = now()->addSeconds((int) $tokenData['expires_in']);
            }

            // محاولة جلب البريد الإلكتروني للحساب المتصل (اختياري)
            $email = null;
            try {
                $client->setAccessToken($tokenData);
                $oauth2 = new \Google\Service\Oauth2($client);
                $userInfo = $oauth2->userinfo->get();
                $email = $userInfo->getEmail();
            } catch (\Throwable $e) {
                // غير حرج - نسجل فقط
                Log::warning('[GoogleDrive][OAuth] failed to fetch user email', ['error' => $e->getMessage()]);
            }

            // تخزين مشفر عبر cast encrypted
            $record = GoogleDriveToken::current();
            if ($record) {
                $record->update([
                    'refresh_token' => $refreshToken,
                    'access_token' => $tokenData['access_token'],
                    'expires_at' => $expiresAt,
                    'email' => $email ?? $record->email,
                    'token_type' => $tokenData['token_type'] ?? $record->token_type,
                    'scope' => $tokenData['scope'] ?? $record->scope,
                ]);
            } else {
                $record = GoogleDriveToken::create([
                    'refresh_token' => $refreshToken,
                    'access_token' => $tokenData['access_token'],
                    'expires_at' => $expiresAt,
                    'email' => $email,
                    'token_type' => $tokenData['token_type'] ?? 'Bearer',
                    'scope' => $tokenData['scope'] ?? null,
                ]);
            }

            Log::info('[GoogleDrive][OAuth] connected successfully', ['email' => $email, 'has_refresh' => !empty($refreshToken)]);

            // اختبار فوري للاتصال بالمجلد
            $testMsg = '';
            try {
                $driveService = app(GoogleDriveService::class);
                // force refresh of internal client
                $driveService->clearCachedClient();
                $test = $driveService->testConnection();
                if ($test['folder_access'] && $test['auth']) {
                    $testMsg = '<p style="color:green">✓ Folder access: OK (' . htmlspecialchars($test['messages'][1] ?? '') . ')</p>';
                }
            } catch (\Throwable $e) {
                $testMsg = '<p style="color:orange">⚠ Folder test: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }

            $html = '
            <html dir="rtl" lang="ar"><head><meta charset="utf-8"><title>Google Drive Connected</title>
            <style>body{font-family:system-ui,sans-serif;padding:40px;max-width:700px;margin:auto;background:#f8fafc} .card{background:white;padding:30px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.08)} h1{color:#16a34a} .meta{color:#64748b;font-size:13px;margin-top:15px}</style>
            </head><body>
            <div class="card">
            <h1>✓ Google Drive connected successfully.</h1>
            <p>تم ربط حساب Google Drive بنجاح.</p>
            ' . ($email ? '<p><strong>الحساب:</strong> ' . htmlspecialchars($email) . '</p>' : '') . '
            <p><strong>المجلد:</strong> ' . htmlspecialchars(config('services.google_drive.folder_id')) . '</p>
            ' . $testMsg . '
            <p>يمكنك الآن إغلاق هذه الصفحة. النسخ الاحتياطي التلقائي سيعمل يومياً الساعة 03:00 بدون تدخل.</p>
            <p><a href="/google-drive/status" style="color:#2563eb">عرض حالة الاتصال</a></p>
            <div class="meta">لا يتم عرض Refresh Token هنا لأسباب أمنية. تم حفظه مشفراً في قاعدة البيانات.</div>
            </div></body></html>';

            return response($html)->header('Content-Type', 'text/html; charset=utf-8');

        } catch (\Throwable $e) {
            Log::error('[GoogleDrive][OAuth] callback failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            // لا تعرض refresh_token أو client_secret في الخطأ
            return response($this->errorHtml('فشل ربط Google Drive', $e->getMessage()), 500)
                ->header('Content-Type', 'text/html; charset=utf-8');
        }
    }

    /**
     * GET /google-drive/status — حالة الاتصال (لا يعرض secrets)
     */
    public function status()
    {
        $token = GoogleDriveToken::current();
        $connected = $token && !empty($token->refresh_token);
        $email = $token?->email ?? '—';
        $expires = $token?->expires_at ? $token->expires_at->toDateTimeString() : '—';
        $folderId = config('services.google_drive.folder_id');

        if ($connected) {
            try {
                $driveService = app(GoogleDriveService::class);
                $test = $driveService->testConnection();
                $messages = implode('<br>', array_map(fn($m)=>htmlspecialchars($m), $test['messages']));
                $statusColor = ($test['auth'] && $test['folder_access'] && $test['upload_permission']) ? '#16a34a' : '#d97706';
            } catch (\Throwable $e) {
                $messages = htmlspecialchars($e->getMessage());
                $statusColor = '#dc2626';
            }
        } else {
            $messages = 'غير مرتبط — افتح /google-drive/connect';
            $statusColor = '#dc2626';
        }

        $html = '
        <html dir="rtl" lang="ar"><head><meta charset="utf-8"><title>Google Drive Status</title>
        <style>body{font-family:system-ui,sans-serif;padding:40px;max-width:700px;margin:auto;background:#f8fafc} .card{background:white;padding:30px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.08)}</style>
        </head><body><div class="card">
        <h2>حالة Google Drive</h2>
        <p><strong>متصل:</strong> ' . ($connected ? '<span style="color:#16a34a">نعم ✓</span>' : '<span style="color:#dc2626">لا ✘</span>') . '</p>
        <p><strong>الحساب:</strong> ' . htmlspecialchars($email) . '</p>
        <p><strong>انتهاء Access Token:</strong> ' . htmlspecialchars($expires) . '</p>
        <p><strong>المجلد:</strong> ' . htmlspecialchars($folderId) . '</p>
        <div style="background:#f1f5f9;padding:15px;border-radius:8px;margin-top:15px;color:' . $statusColor . '">' . $messages . '</div>
        <p style="margin-top:20px">
            <a href="/google-drive/connect" style="background:#2563eb;color:white;padding:10px 18px;border-radius:8px;text-decoration:none">إعادة الربط</a>
            <a href="/" style="margin-right:10px;color:#64748b">العودة</a>
        </p>
        </div></body></html>';

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    protected function errorHtml(string $title, string $details): string
    {
        return '<html dir="rtl" lang="ar"><head><meta charset="utf-8"><title>خطأ</title>
        <style>body{font-family:system-ui,sans-serif;padding:40px;max-width:700px;margin:auto;background:#fef2f2} .card{background:white;padding:30px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.08)} h1{color:#dc2626}</style>
        </head><body><div class="card"><h1>' . htmlspecialchars($title) . '</h1><p>' . htmlspecialchars($details) . '</p><p><a href="/google-drive/connect">حاول مرة أخرى</a></p></div></body></html>';
    }
}
