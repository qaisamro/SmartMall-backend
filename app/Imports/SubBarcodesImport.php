<?php

namespace App\Imports;

use App\Models\SubBarcode;
use App\Models\ProductImport;
use App\Notifications\ImportCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SubBarcodesImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $importId,
        protected int $mallId,
        protected string $filePath,
        protected ?int $userId = null,
    ) {}

    public function handle(): void
    {
        $reader = IOFactory::createReaderForFile($this->filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($this->filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, true);

        if (empty($rows)) return;

        $headers = array_shift($rows);
        $colMap = $this->mapHeaders($headers);

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $batch = [];

        foreach ($rows as $index => $raw) {
            $rowNum = $index + 2;

            $subBarcode = trim($raw[$colMap['sub_barcode']] ?? '');
            $mainBarcode = trim($raw[$colMap['main_barcode']] ?? '');

            if (empty($subBarcode) && empty($mainBarcode)) continue;

            if (empty($subBarcode)) {
                $errors[] = ['row' => $rowNum, 'message' => 'sub_barcode فارغ'];
                $skipped++;
                continue;
            }

            if (empty($mainBarcode)) {
                $errors[] = ['row' => $rowNum, 'message' => 'main_barcode فارغ'];
                $skipped++;
                continue;
            }

            // Store without linking to product — matching happens at scan time
            $existingSub = SubBarcode::where('mall_id', $this->mallId)
                ->where('sub_barcode', $subBarcode)
                ->first();

            if ($existingSub) {
                $existingSub->update([
                    'main_barcode' => $mainBarcode,
                    'product_id' => null,
                ]);
                $updated++;
                continue;
            }

            $batch[] = [
                'mall_id' => $this->mallId,
                'sub_barcode' => $subBarcode,
                'main_barcode' => $mainBarcode,
                'product_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) >= 100) {
                SubBarcode::insert($batch);
                $inserted += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            SubBarcode::insert($batch);
            $inserted += count($batch);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }

        $import = ProductImport::find($this->importId);
        if ($import) {
            $import->update([
                'status' => count($errors) > 0 ? 'completed_with_errors' : 'completed',
                'total_rows' => $inserted + $updated + $skipped,
                'imported_rows' => $inserted + $updated,
                'inserted_rows' => $inserted,
                'updated_rows' => $updated,
                'skipped_rows' => $skipped,
                'failed_rows' => count($errors),
                'errors' => $errors,
                'completed_at' => now(),
            ]);

            if ($this->userId) {
                $user = \App\Models\User::find($this->userId);
                if ($user) {
                    $user->notify(new ImportCompleted($import));
                }
            }
        }
    }

    private function mapHeaders(array $headers): array
    {
        $map = ['sub_barcode' => 'A', 'main_barcode' => 'B'];
        $keywords = [
            'sub_barcode' => ['sub_barcode', 'subbarcode', 'sub barcode', 'باركود فرعي', 'الباركود_الفرعي', 'باركود فرعى'],
            'main_barcode' => ['main_barcode', 'mainbarcode', 'main barcode', 'باركود رئيسي', 'الباركود_الرئيسي', 'باركود رئيسى'],
        ];

        $result = [];
        foreach ($headers as $col => $header) {
            $clean = mb_strtolower(trim((string)$header));
            foreach ($keywords as $field => $aliases) {
                foreach ($aliases as $alias) {
                    if ($clean === mb_strtolower($alias) || str_contains($clean, mb_strtolower($alias))) {
                        $result[$field] = $col;
                        break 2;
                    }
                }
            }
        }

        return array_merge($map, $result);
    }
}
