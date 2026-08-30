<?php

namespace App\Services;

use App\Models\GoogleDriveToken;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;

class GoogleDriveService
{
    protected ?GoogleClient $client = null;
    protected ?GoogleDrive $drive = null;

    protected string $folderId;
    protected ?string $clientId;
    protected ?string $clientSecret;
    protected ?string $redirectUri;
    // Legacy Service Account fallback
    protected string $credentialsPath;

    public function __construct()
    {
        $this->folderId = (string) config('services.google_drive.folder_id');
        $this->clientId = config('services.google_drive.client_id');
        $this->clientSecret = config('services.google_drive.client_secret');
        $this->redirectUri = config('services.google_drive.redirect_uri');
        $rawPath = config('services.google_drive.credentials');
        $this->credentialsPath = $this->resolveCredentialsPath($rawPath);
    }

    protected function resolveCredentialsPath(?string $raw): string
    {
        if (!$raw) {
            return storage_path('app/google/smartmallbackup-331ff1b8f70c.json');
        }
        if (str_starts_with($raw, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $raw)) {
            return $raw;
        }
        if (str_starts_with($raw, 'storage/')) {
            return base_path($raw);
        }
        return storage_path($raw);
    }

    public function getFolderId(): string
    {
        return $this->folderId;
    }

    public function getCredentialsPath(): string
    {
        return $this->credentialsPath;
    }

    public function clearCachedClient(): void
    {
        $this->client = null;
        $this->drive = null;
    }

    public function isConnected(): bool
    {
        try {
            $token = GoogleDriveToken::current();
            return $token && !empty($token->refresh_token);
        } catch (\Throwable $e) {
            Log::warning('[GoogleDrive] isConnected DB check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * إنشاء Google Client للمصادقة OAuth (بدون token)
     */
    protected function buildOAuthClient(): GoogleClient
    {
        if (empty($this->clientId) || empty($this->clientSecret) || empty($this->redirectUri)) {
            throw new \RuntimeException('Google Drive OAuth not configured. Set GOOGLE_DRIVE_OAUTH_CLIENT_ID / SECRET / REDIRECT_URI');
        }

        $client = new GoogleClient();
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setRedirectUri($this->redirectUri);
        $client->setScopes([GoogleDrive::DRIVE]);
        $client->setAccessType('offline');
        $client->setIncludeGrantedScopes(true);
        if (method_exists($client, 'setPrompt')) {
            $client->setPrompt('consent');
        } elseif (method_exists($client, 'setApprovalPrompt')) {
            $client->setApprovalPrompt('force');
        }
        $client->setApplicationName('SmartMall Backup');

        return $client;
    }

    /**
     * بناء Service Account Client (legacy fallback - لا يُستخدم في Backup الجديد)
     */
    protected function buildServiceAccountClient(): GoogleClient
    {
        if (!file_exists($this->credentialsPath)) {
            throw new \RuntimeException("Google Drive credentials file not found: {$this->credentialsPath}");
        }
        $client = new GoogleClient();
        $client->setAuthConfig($this->credentialsPath);
        $client->setScopes([GoogleDrive::DRIVE]);
        $client->setApplicationName('SmartMall Backup (SA)');
        return $client;
    }

    protected function buildClient(): GoogleClient
    {
        if ($this->client) {
            return $this->client;
        }

        if (empty($this->folderId)) {
            throw new \RuntimeException("Google Drive folder ID is not configured (GOOGLE_DRIVE_FOLDER_ID).");
        }

        // الأولوية لـ OAuth (حساب شخصي smartmallps2026@gmail.com)
        try {
            $token = GoogleDriveToken::current();
            if ($token && !empty($token->refresh_token)) {
                $client = $this->buildOAuthClient();
                $this->applyTokenToClient($client, $token);
                $this->client = $client;
                return $client;
            }
        } catch (\Throwable $e) {
            Log::warning('[GoogleDrive] DB error during buildClient', ['error' => $e->getMessage()]);
            // استمر لل fallback
        }

        // Fallback legacy Service Account إذا لم يكن OAuth مربوط
        if (file_exists($this->credentialsPath)) {
            Log::warning('[GoogleDrive] OAuth not connected, falling back to Service Account (deprecated)');
            $client = $this->buildServiceAccountClient();
            $this->client = $client;
            return $client;
        }

        throw new \RuntimeException('Google Drive not connected. Please visit /google-drive/connect to authorize with smartmallps2026@gmail.com');
    }

    /**
     * تطبيق التوكن على Client مع تحديث تلقائي إذا منتهي
     */
    protected function applyTokenToClient(GoogleClient $client, GoogleDriveToken $token): void
    {
        // بناء مصفوفة التوكن كما يتوقعها Google Client
        $tokenArray = [
            'access_token' => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expires_in' => $token->expires_at ? max(0, $token->expires_at->diffInSeconds(now(), false) * -1 + 0) : 3600,
            'created' => $token->updated_at ? $token->updated_at->timestamp : time(),
        ];

        // إذا كان التوكن منتهي أو سينتهي خلال 60 ثانية، حدثه
        $needsRefresh = false;
        if ($token->expires_at) {
            $needsRefresh = $token->expires_at->isPast() || $token->expires_at->diffInSeconds(now()) < 60;
        } elseif ($client->isAccessTokenExpired()) {
            $needsRefresh = true;
        }

        // نستخدم refresh_token لتجديد access_token
        if ($needsRefresh) {
            Log::info('[GoogleDrive] OAuth token expired/expiring, refreshing', ['email' => $token->email]);
            try {
                $newToken = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);
                if (isset($newToken['error'])) {
                    throw new \RuntimeException('Refresh failed: ' . ($newToken['error_description'] ?? $newToken['error']));
                }
                if (empty($newToken['access_token'])) {
                    throw new \RuntimeException('Refresh returned no access_token');
                }
                // احتفظ بالـ refresh_token الأصلي إذا لم يُرجع جديد
                $newRefresh = $newToken['refresh_token'] ?? $token->refresh_token;
                $expiresAt = isset($newToken['expires_in']) ? now()->addSeconds((int) $newToken['expires_in']) : now()->addHour();

                // تحديث DB (مشفر تلقائياً)
                $token->update([
                    'access_token' => $newToken['access_token'],
                    'refresh_token' => $newRefresh,
                    'expires_at' => $expiresAt,
                    'token_type' => $newToken['token_type'] ?? $token->token_type,
                ]);

                $client->setAccessToken($newToken);
                Log::info('[GoogleDrive] OAuth token refreshed', ['expires_at' => $expiresAt->toDateTimeString()]);
            } catch (\Throwable $e) {
                Log::error('[GoogleDrive] OAuth refresh failed', ['error' => $e->getMessage()]);
                throw new \RuntimeException('Google Drive OAuth refresh failed: ' . $e->getMessage() . ' — Please re-auth at /google-drive/connect');
            }
        } else {
            // استخدم التوكن الحالي
            $client->setAccessToken([
                'access_token' => $token->access_token,
                'refresh_token' => $token->refresh_token,
                'expires_in' => 3600,
                'created' => time(),
            ]);
            // تحقق إضافي: إذا كان Client يرى أنه منتهي، حاول Refresh
            if ($client->isAccessTokenExpired()) {
                $client->fetchAccessTokenWithRefreshToken($token->refresh_token);
                $newToken = $client->getAccessToken();
                if (!empty($newToken['access_token'])) {
                    $expiresAt = isset($newToken['expires_in']) ? now()->addSeconds((int) $newToken['expires_in']) : now()->addHour();
                    $token->update([
                        'access_token' => $newToken['access_token'],
                        'expires_at' => $expiresAt,
                    ]);
                }
            }
        }
    }

    public function getDrive(): GoogleDrive
    {
        if ($this->drive) {
            return $this->drive;
        }
        $this->drive = new GoogleDrive($this->buildClient());
        return $this->drive;
    }

    /**
     * اختبار الاتصال والصلاحيات - لا يعرض secrets
     * @return array{auth: bool, has_refresh: bool, folder_access: bool, upload_permission: bool, messages: string[]}
     */
    public function testConnection(): array
    {
        $messages = [];
        $result = ['auth' => false, 'has_refresh' => false, 'folder_access' => false, 'upload_permission' => false, 'messages' => []];

        try {
            $token = GoogleDriveToken::current();
        } catch (\Throwable $e) {
            $messages[] = 'Refresh token: DB ERROR - ' . $e->getMessage();
            Log::error('[GoogleDrive] DB error fetching token', ['error' => $e->getMessage()]);
            $result['messages'] = $messages;
            return $result;
        }
        if ($token && !empty($token->refresh_token)) {
            $result['has_refresh'] = true;
            $messages[] = 'Refresh token: OK';
            // لا تعرض القيمة
        } else {
            $messages[] = 'Refresh token: MISSING — visit /google-drive/connect';
            Log::warning('[GoogleDrive] no refresh token');
            // حاول Service Account fallback للتشخيص
            if (file_exists($this->credentialsPath)) {
                $messages[] = 'Fallback: Service Account file exists (deprecated)';
            }
            $result['messages'] = $messages;
            return $result;
        }

        try {
            $this->buildClient();
            $result['auth'] = true;
            $messages[] = 'OAuth authentication: OK';
        } catch (\Throwable $e) {
            $messages[] = 'OAuth authentication: FAILED - ' . $e->getMessage();
            Log::error('[GoogleDrive] OAuth auth failed', ['error' => $e->getMessage()]);
            $result['messages'] = $messages;
            return $result;
        }

        try {
            $drive = $this->getDrive();
            $folder = $drive->files->get($this->folderId, ['fields' => 'id, name, mimeType, owners', 'supportsAllDrives' => true]);
            $result['folder_access'] = true;
            $messages[] = 'Folder access: OK (' . ($folder->getName() ?? $this->folderId) . ')';
        } catch (\Throwable $e) {
            $messages[] = 'Folder access: FAILED - ' . $e->getMessage();
            Log::error('[GoogleDrive] folder access failed', ['folder_id' => $this->folderId, 'error' => $e->getMessage()]);
            $result['messages'] = $messages;
            return $result;
        }

        // اختبار الرفع
        try {
            $tmpFile = tempnam(sys_get_temp_dir(), 'gdrive_test_');
            file_put_contents($tmpFile, 'SmartMall Google Drive OAuth test ' . now()->toDateTimeString());

            $fileId = $this->uploadFile($tmpFile, 'gdrive_test_' . time() . '.txt', 'text/plain');
            if ($fileId) {
                try {
                    $this->deleteFile($fileId);
                } catch (\Throwable $e) {
                    // ignore
                }
                $result['upload_permission'] = true;
                $messages[] = 'Upload permission: OK';
            }
            @unlink($tmpFile);
        } catch (\Throwable $e) {
            $messages[] = 'Upload permission: FAILED - ' . $e->getMessage();
            Log::error('[GoogleDrive] upload permission failed', ['error' => $e->getMessage()]);
        }

        $result['messages'] = $messages;
        return $result;
    }

    /**
     * رفع ملف إلى الفولدر المحدد.
     * @return string File ID
     */
    public function uploadFile(string $localPath, string $filename, string $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'): string
    {
        if (!file_exists($localPath)) {
            throw new \RuntimeException("Local file not found: {$localPath}");
        }

        $drive = $this->getDrive();

        $fileMetadata = new DriveFile([
            'name' => $filename,
            'parents' => [$this->folderId],
        ]);

        $content = file_get_contents($localPath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read local file: {$localPath}");
        }

        $created = $drive->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id, name, parents',
            'supportsAllDrives' => true,
        ]);

        $fileId = $created->getId();
        if (!$fileId) {
            throw new \RuntimeException("Google Drive upload returned empty file ID for {$filename}");
        }

        Log::info('[GoogleDrive] upload successful', ['filename' => $filename, 'file_id' => $fileId]);

        return $fileId;
    }

    /**
     * حذف ملف من Google Drive.
     */
    public function deleteFile(string $fileId): bool
    {
        try {
            $this->getDrive()->files->delete($fileId, ['supportsAllDrives' => true]);
            Log::info('[GoogleDrive] file deleted', ['file_id' => $fileId]);
            return true;
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'notFound')) {
                Log::warning('[GoogleDrive] file not found on delete (considered deleted)', ['file_id' => $fileId]);
                return true;
            }
            Log::error('[GoogleDrive] delete failed', ['file_id' => $fileId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function fileExists(string $fileId): bool
    {
        try {
            $this->getDrive()->files->get($fileId, ['fields' => 'id', 'supportsAllDrives' => true]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * الحصول على رابط المصادقة (يستخدم في Controller)
     */
    public function getAuthUrl(): string
    {
        $client = $this->buildOAuthClient();
        $url = $client->createAuthUrl();
        if (!str_contains($url, 'access_type=offline')) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'access_type=offline&prompt=consent&include_granted_scopes=true';
        }
        return $url;
    }
}
