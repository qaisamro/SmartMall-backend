<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageOptimizerService
{
    private const MAX_WIDTH = 1200;
    private const MAX_HEIGHT = 1200;
    private const QUALITY = 80;

    /**
     * Upload, resize, and convert an image to WebP.
     *
     * @param  UploadedFile  $file      The uploaded file
     * @param  string        $path      Storage subdirectory (e.g. 'products', 'malls')
     * @param  int|null      $width     Max width (defaults to MAX_WIDTH)
     * @param  int|null      $height    Max height (defaults to MAX_HEIGHT)
     * @return string                   The stored file path
     */
    public function optimize(UploadedFile $file, string $path = 'products', ?int $width = null, ?int $height = null): string
    {
        $width = $width ?? self::MAX_WIDTH;
        $height = $height ?? self::MAX_HEIGHT;

        // Generate a unique file name with .webp extension
        $fileName = Str::random(32) . '.webp';
        $filePath = "{$path}/{$fileName}";

        // Build the image from the uploaded file
        $imageInfo = getimagesize($file->getRealPath());
        if (!$imageInfo) {
            throw new \RuntimeException('Invalid image file');
        }

        // Create GD resource based on mime type
        $source = match ($imageInfo['mime']) {
            'image/jpeg' => imagecreatefromjpeg($file->getRealPath()),
            'image/png'  => imagecreatefrompng($file->getRealPath()),
            'image/gif'  => imagecreatefromgif($file->getRealPath()),
            'image/webp' => imagecreatefromwebp($file->getRealPath()),
            default      => throw new \RuntimeException('Unsupported image type: ' . $imageInfo['mime']),
        };

        // Calculate new dimensions while maintaining aspect ratio
        [$origWidth, $origHeight] = $imageInfo;
        $ratio = min($width / $origWidth, $height / $origHeight, 1);
        $newWidth = (int) round($origWidth * $ratio);
        $newHeight = (int) round($origHeight * $ratio);

        // Resize
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        // Save as WebP to a temporary buffer
        $tempPath = tempnam(sys_get_temp_dir(), 'img_');
        imagewebp($resized, $tempPath, self::QUALITY);

        // Store to the default filesystem disk
        Storage::put($filePath, file_get_contents($tempPath), 'public');

        // Clean up
        imagedestroy($source);
        imagedestroy($resized);
        unlink($tempPath);

        return $filePath;
    }
}
