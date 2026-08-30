<?php

namespace App\Console\Commands;

use App\Models\Mall;
use App\Services\GoogleDriveService;
use App\Services\ProductExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BackupProductsToGoogleDrive extends Command
{
    protected $signature = 'products:backup-to-google-drive';
    protected $description = 'Backup all mall products to Google Drive (one Excel per mall, keep latest only)';

    public function handle(ProductExportService $exportService, GoogleDriveService $driveService): int
    {
        if (!config('services.google_drive.enabled')) {
            $this->warn('Google Drive backup is disabled (GOOGLE_DRIVE_BACKUP_ENABLED=false).');
            Log::warning('[Backup] disabled via config');
            return self::SUCCESS;
        }

        $lock = Cache::lock('products-backup-google-drive', 3600);
        if (!$lock->get()) {
            $this->warn('Backup already running — skipping.');
            Log::warning('[Backup] already running, lock not acquired');
            return self::SUCCESS;
        }

        $this->info('Google Drive product backup started');
        Log::info('[Backup] Google Drive product backup started');

        // OAuth check - يجب ربط الحساب أولاً عبر /google-drive/connect
        if (!$driveService->isConnected()) {
            $this->error('Google Drive OAuth not connected. Please visit https://samrtmall.cloud/google-drive/connect and authorize as smartmallps2026@gmail.com');
            Log::error('[Backup] OAuth not connected - aborting');
            $lock->release();
            return self::FAILURE;
        }
        $this->info('OAuth authentication: OK');
        Log::info('[Backup] OAuth authentication: OK');

        $malls = Mall::orderBy('id')->get();
        $total = $malls->count();
        $successful = 0;
        $failed = 0;

        if ($total === 0) {
            $this->warn('No malls found.');
        }

        foreach ($malls as $mall) {
            $this->info("Mall #{$mall->id} ({$mall->name_ar}) backup started");
            Log::info("[Backup] Mall {$mall->id} ({$mall->name_ar}) backup started");

            $tempPath = null;
            try {
                // 1) Generate Excel
                $this->line('  Generating Excel...');
                $result = $exportService->saveToTempFile($mall);
                $tempPath = $result['path'];
                $filename = $result['filename'];

                if (!file_exists($tempPath) || filesize($tempPath) === 0) {
                    throw new \RuntimeException("Excel generation failed or empty file: {$tempPath}");
                }
                $this->line("  Excel generated: {$filename} (" . filesize($tempPath) . " bytes)");
                Log::info("[Backup] Mall {$mall->id} Excel generated", ['filename' => $filename, 'path' => $tempPath]);

                // 2) Upload to Google Drive
                $this->line('  Uploading to Google Drive...');
                $newFileId = $driveService->uploadFile($tempPath, $filename);
                $this->line("  Upload successful (File ID: {$newFileId})");
                Log::info("[Backup] Mall {$mall->id} upload successful", ['file_id' => $newFileId, 'filename' => $filename]);

                // 3) Delete old backup AFTER successful upload (by stored file_id, safe)
                $oldFileId = $mall->google_drive_backup_file_id;
                if ($oldFileId && $oldFileId !== $newFileId) {
                    try {
                        $driveService->deleteFile($oldFileId);
                        $this->line("  Old backup deleted (File ID: {$oldFileId})");
                        Log::info("[Backup] Mall {$mall->id} old backup deleted", ['old_file_id' => $oldFileId]);
                    } catch (\Throwable $e) {
                        // Don't fail the backup if old deletion fails — just log
                        $this->warn("  Old backup delete failed: " . $e->getMessage());
                        Log::warning("[Backup] Mall {$mall->id} old backup delete failed", ['old_file_id' => $oldFileId, 'error' => $e->getMessage()]);
                    }
                }

                // 4) Persist new file reference (mall_id binding — not filename)
                $mall->update([
                    'google_drive_backup_file_id' => $newFileId,
                    'google_drive_backup_filename' => $filename,
                    'google_drive_backup_at' => now(),
                ]);

                $this->info("  Mall #{$mall->id} backup completed");
                Log::info("[Backup] Mall {$mall->id} backup completed", ['file_id' => $newFileId]);
                $successful++;

            } catch (\Throwable $e) {
                $failed++;
                $msg = $e->getMessage();
                $this->error("  Mall #{$mall->id} backup failed: {$msg}");
                Log::error("[Backup] Mall {$mall->id} backup failed", ['error' => $msg, 'trace' => $e->getTraceAsString()]);
                // Do NOT delete old backup on failure — per requirement
            } finally {
                // Clean temp file safely
                if ($tempPath && file_exists($tempPath)) {
                    @unlink($tempPath);
                    Log::info("[Backup] temp file cleaned", ['path' => $tempPath]);
                }
            }
        }

        $this->newLine();
        $this->info("Google Drive product backup completed — Total: {$total}, Successful: {$successful}, Failed: {$failed}");
        Log::info('[Backup] Google Drive product backup completed', ['total' => $total, 'successful' => $successful, 'failed' => $failed]);

        $lock->release();

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
