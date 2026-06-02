<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$filename = $argv[1] ?? null;
if (! $filename) { echo "usage: php tools/print_headers.php <file>\n"; exit(1); }
$path = __DIR__ . '/../storage/app/private/private/imports/' . $filename;
$reader = IOFactory::createReaderForFile($path);
$reader->setReadDataOnly(true);
$sheet = $reader->load($path)->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);
if (empty($rows)) { echo "no rows\n"; exit(0); }
$headers = array_shift($rows);
foreach ($headers as $col => $val) {
    echo "$col: $val\n";
}
