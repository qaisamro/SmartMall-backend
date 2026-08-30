<?php

namespace App\Console\Commands;

use App\Services\GoogleDriveService;
use Illuminate\Console\Command;

class TestGoogleDriveConnection extends Command
{
    protected $signature = 'google-drive:test';
    protected $description = 'Test Google Drive OAuth connection, folder access and upload permission';

    public function handle(GoogleDriveService $drive): int
    {
        $this->info('Testing Google Drive OAuth connection...');
        $this->line('Folder ID  : ' . $drive->getFolderId());
        $this->line('OAuth      : ' . (config('services.google_drive.client_id') ? 'configured' : 'NOT configured'));
        $this->line('Connected  : ' . ($drive->isConnected() ? 'yes' : 'no — run /google-drive/connect'));
        $this->newLine();

        $result = $drive->testConnection();

        foreach ($result['messages'] as $msg) {
            if (str_contains($msg, 'OK')) {
                $this->info('  ✔ ' . $msg);
            } else {
                $this->error('  ✘ ' . $msg);
            }
        }

        $this->newLine();
        $hasAuth = $result['auth'] ?? false;
        $hasRefresh = $result['has_refresh'] ?? false;
        if ($hasAuth && ($result['folder_access'] ?? false) && ($result['upload_permission'] ?? false)) {
            $this->info('All checks passed — Google Drive is ready (OAuth).');
            return self::SUCCESS;
        }

        if (!$hasRefresh) {
            $this->error('OAuth not connected.');
            $this->line('  1. Open: ' . config('services.google_drive.redirect_uri', 'https://samrtmall.cloud/google-drive/connect'));
            $this->line('     actually: https://samrtmall.cloud/google-drive/connect');
            $this->line('  2. Login as smartmallps2026@gmail.com and grant Drive permission');
            $this->line('  3. You should see "Google Drive connected successfully."');
        } else {
            $this->error('Some checks failed — please review messages above.');
        }
        $this->line('Common fixes:');
        $this->line('  - Ensure GOOGLE_DRIVE_OAUTH_CLIENT_ID / SECRET / REDIRECT_URI are set correctly');
        $this->line('  - Ensure Google Drive API is enabled in Google Cloud');
        $this->line('  - Folder ID: ' . $drive->getFolderId() . ' must be accessible by smartmallps2026@gmail.com');
        $this->line('  - If refresh_token missing, revoke at https://myaccount.google.com/permissions and reconnect with prompt=consent');

        return self::FAILURE;
    }
}
