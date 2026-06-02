<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Services\ProductImportService;
use PhpOffice\PhpSpreadsheet\IOFactory;

$filename = $argv[1] ?? null;
if (! $filename) { echo "usage: php tools/print_header_map.php <file>\n"; exit(1); }
$path = __DIR__ . '/../storage/app/private/private/imports/' . $filename;
$reader = IOFactory::createReaderForFile($path);
$reader->setReadDataOnly(true);
$sheet = $reader->load($path)->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);
$headers = array_shift($rows);

// Use reflection to call protected method for debugging
$svc = new ProductImportService();
$ref = new ReflectionMethod(App\Services\ProductImportService::class, 'normalizeHeaders');
$ref->setAccessible(true);
$map = $ref->invoke($svc, $headers);

echo json_encode(['headers' => $headers, 'map' => $map], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
