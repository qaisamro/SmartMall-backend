<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$dir = __DIR__ . '/../storage/app/private/private/imports';
$files = scandir($dir);
$result = [];
foreach ($files as $file) {
    if (in_array($file, ['.', '..'])) continue;
    $path = $dir . '/' . $file;
    try {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $first = [];
        if (!empty($rows)) {
            $first = array_shift($rows);
        }
        $result[] = ['file' => $file, 'headers' => $first];
    } catch (Exception $e) {
        $result[] = ['file' => $file, 'error' => $e->getMessage()];
    }
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
