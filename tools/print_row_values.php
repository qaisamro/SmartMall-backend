<?php
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
$filename = $argv[1] ?? null;
$index = isset($argv[2]) ? intval($argv[2]) : 2;
if (! $filename) { echo "usage: php tools/print_row_values.php <file> [rowNumber]\n"; exit(1); }
$path = __DIR__ . '/../storage/app/private/private/imports/' . $filename;
$reader = IOFactory::createReaderForFile($path);
$reader->setReadDataOnly(true);
$sheet = $reader->load($path)->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);
$row = $rows[$index] ?? null;
if (!$row) { echo "no such row\n"; exit(1); }
echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
