<?php

namespace App\Jobs;

use App\Services\SystemHealthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunSystemScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $triggeredBy = null, public string $triggerType = 'scheduled') {}

    public function handle(): void
    {
        SystemHealthService::runScan($this->triggeredBy, $this->triggerType);
    }
}
