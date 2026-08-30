<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== MIGRATIONS TABLE (last 10) ===\n";
$rows = DB::table('migrations')->orderBy('id', 'desc')->limit(10)->get();
foreach ($rows as $r) {
    echo "{$r->id} | {$r->migration} | batch={$r->batch}\n";
}
echo "\n=== COUNT ===\n";
echo DB::table('migrations')->count() . " total\n";