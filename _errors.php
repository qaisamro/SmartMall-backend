<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$import = \App\Models\ProductImport::latest()->first();
echo "Import #{$import->id}: {$import->status}\n";
echo "Total: {$import->total_rows}, Imported: {$import->imported_rows}, Failed: {$import->failed_rows}\n\n";

$errors = $import->errors ?? [];
echo "Errors stored: " . count($errors) . "\n\n";

// Count error types
$types = [];
foreach ($errors as $e) {
    $msg = $e['message'] ?? '';
    // Get just the first line / error type
    if (str_contains($msg, 'Duplicate entry')) {
        $key = 'DUPLICATE';
    } elseif (str_contains($msg, 'اسم المنتج مطلوب')) {
        $key = 'NAME_MISSING';
    } elseif (str_contains($msg, 'السعر مطلوب')) {
        $key = 'PRICE_MISSING';
    } elseif (str_contains($msg, 'التصنيف')) {
        $key = 'CATEGORY';
    } else {
        $key = 'OTHER: ' . mb_substr($msg, 0, 80);
    }
    $types[$key] = ($types[$key] ?? 0) + 1;
}

foreach ($types as $type => $count) {
    echo "  $type: $count\n";
}

// Show first 3 errors
echo "\nFirst 3 errors:\n";
foreach (array_slice($errors, 0, 3) as $i => $e) {
    echo ($i+1) . ". row={$e['row']}: {$e['message']}\n";
}
