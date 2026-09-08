<?php

namespace App\Services\HealthChecks;

interface HealthCheckInterface
{
    public function key(): string;          // e.g. db.connection
    public function category(): string;     // technical|application|ux|performance
    public function severity(): string;     // info|warning|error|critical
    public function title(): string;
    public function run(): HealthResult;
}
