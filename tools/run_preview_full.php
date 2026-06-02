<?php
require __DIR__ . '/../vendor/autoload.php';
use App\Services\ProductImportService;

$filename = $argv[1] ?? null;
$mallId = isset($argv[2]) ? intval($argv[2]) : 1;
if (! $filename) { echo "usage: php tools/run_preview_full.php <file> [mallId]\n"; exit(1); }
$path = __DIR__ . '/../storage/app/private/private/imports/' . $filename;
$svc = new ProductImportService();
$preview = $svc->preview($path, $mallId, ['auto_create_categories' => true, 'duplicate_strategy' => 'skip', 'duplicate_by' => 'sku']);
echo json_encode($preview['summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
