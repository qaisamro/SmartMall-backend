<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "opcache_enabled: " . (function_exists('opcache_get_status') ? (opcache_get_status() ? 'YES' : 'NO') : 'function not found') . "\n";

// Clear opcache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "opcache_reset: done\n";
}

// Clear Laravel cache
Artisan::call('optimize:clear');
echo "optimize:clear output: " . Artisan::output() . "\n";
