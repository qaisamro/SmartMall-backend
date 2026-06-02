<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QRService
{
    /**
     * Generate a QR Code, save it, and return its path.
     *
     * @param string $data
     * @param string $prefix
     * @return string
     */
    public function generateQR(string $data, string $prefix = 'mall'): string
    {
        $result = (new Builder())->build(
            writer: new PngWriter(),
            writerOptions: [],
            validateResult: false,
            data: $data,
            size: 300,
            margin: 10
        );

        $filename = 'qrcodes/' . $prefix . '_' . Str::random(10) . '_' . time() . '.png';

        // Save to public storage
        Storage::disk('public')->put($filename, $result->getString());

        return $filename;
    }
}
