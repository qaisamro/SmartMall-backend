<?php

namespace App\Services\HealthChecks;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSchemaCheck implements HealthCheckInterface
{
    public function key(): string { return 'db.schema'; }
    public function category(): string { return 'application'; }
    public function severity(): string { return 'error'; }
    public function title(): string { return 'سلامة مخطط قاعدة البيانات'; }
    public function run(): HealthResult
    {
        try {
            $required = ['users', 'malls', 'products', 'orders', 'categories', 'activity_logs', 'migrations'];
            $missing = [];
            foreach ($required as $t) {
                if (!Schema::hasTable($t)) $missing[] = $t;
            }
            // فحص Foreign keys عبر information_schema (MySQL)
            $fkCount = DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->count();
            $details = ['required_tables' => $required, 'missing' => $missing, 'foreign_keys' => $fkCount];
            if (!empty($missing)) return HealthResult::failed('جداول مفقودة: ' . implode(', ', $missing), $details, 'critical', 'critical');
            if ($fkCount < 10) return HealthResult::warning("عدد Foreign Keys قليل: $fkCount", $details);
            return HealthResult::pass("المخطط سليم (FK: $fkCount)", $details);
        } catch (\Throwable $e) {
            return HealthResult::failed('Schema check فشل: ' . substr($e->getMessage(), 0, 200));
        }
    }
}
