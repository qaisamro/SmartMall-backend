<?php
require __DIR__ . '/../vendor/autoload.php';
use App\Models\ProductImport;
$id = $argv[1] ?? null;
if (! $id) { echo "usage: php tools/dump_import.php <id>\n"; exit(1); }
$imp = ProductImport::find($id);
if (! $imp) { echo "Import not found\n"; exit(0); }
echo json_encode($imp->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
