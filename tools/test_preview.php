<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Services\ProductImportService;

$filename = $argv[1] ?? null;
if (! $filename) {
    echo "Usage: php tools/test_preview.php <file.xlsx>\n";
    exit(1);
}

$path = __DIR__ . '/../storage/app/private/private/imports/' . $filename;
if (!file_exists($path)) {
    echo "File not found: $path\n";
    exit(1);
}

$svc = new ProductImportService();
$rows = $svc->loadRows($path);

$out = array_slice($rows, 0, 5);
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
