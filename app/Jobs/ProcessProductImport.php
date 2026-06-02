<?php

namespace App\Jobs;

use App\Models\ProductImport;
use App\Services\ProductImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessProductImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ProductImport $productImport;

    public function __construct(ProductImport $productImport)
    {
        $this->productImport = $productImport;
    }

    public function handle(ProductImportService $service): void
    {
        $service->processImport($this->productImport);
    }

    public function failed(\Throwable $exception): void
    {
        $this->productImport->update([
            'status' => 'failed',
            'failed_rows' => $this->productImport->failed_rows + 1,
            'errors' => array_merge($this->productImport->errors ?? [], [
                [
                    'row' => null,
                    'message' => 'Import job failed: ' . $exception->getMessage(),
                    'data' => [],
                ],
            ]),
        ]);

        Log::error('Product import failed', [
            'import_id' => $this->productImport->id,
            'message' => $exception->getMessage(),
        ]);
    }
}
