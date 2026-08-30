<?php

namespace App\Services;

use App\Models\Mall;
use App\Models\Section;
use App\Models\BulkPhotoImportRow;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ProductExportService
{
    /**
     * الـ 19 عمود — نفس ترتيب وأسماء التصدير الحالي بالضبط.
     */
    public function getHeaders(): array
    {
        return [
            'ID',
            'الاسم (عربي)',
            'الاسم (إنجليزي)',
            'الباركود',
            'SKU',
            'السعر',
            'سعر الخصم',
            'الكمية',
            'القسم (قديم)',
            'القسم الرئيسي (محدث)',
            'القسم الفرعي',
            'تاريخ تحديث القسم',
            'التصنيف',
            'المنشأة',
            'الوحدة',
            'العلامة التجارية',
            'رابط الصورة',
            'فعال',
            'تاريخ الإضافة',
        ];
    }

    /**
     * تنظيف اسم المول للاستخدام في اسم الملف — نحتفظ بالعربية.
     */
    public function sanitizeMallName(Mall $mall): string
    {
        $raw = trim($mall->name_ar ?? $mall->name_en ?? '');
        if ($raw === '') {
            return 'mall-' . $mall->id;
        }
        // replace spaces with underscore, remove filesystem-illegal chars
        $safe = str_replace(' ', '_', $raw);
        $safe = preg_replace('/[\/\\\\:*?"<>|]/u', '', $safe);
        // keep only letters (Arabic+English), numbers, underscore, hyphen
        $safe = preg_replace('/[^\p{L}\p{N}_\-]/u', '', $safe);
        if (empty($safe)) {
            return 'mall-' . $mall->id;
        }
        return $safe;
    }

    /**
     * اسم الملف: {اسم_المول}_{YYYY-MM-DD}_{HH-mm-ss}.xlsx
     */
    public function buildFilename(Mall $mall): string
    {
        $safe = $this->sanitizeMallName($mall);
        return $safe . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
    }

    /**
     * إنشاء Spreadsheet جاهز لمول محدد — نفس منطق ProductController::exportExcel تماماً.
     * يستخدم cursor() لتقليل استهلاك الذاكرة مع عدد كبير من المنتجات.
     */
    public function buildSpreadsheetForMall(Mall $mall): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = $this->getHeaders();

        $sheet->setRightToLeft(true);
        $sheet->fromArray([$headers], null, 'A1');
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true);
        $sheet->getStyle('A1:' . $lastCol . '1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE8EEF6');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:' . $lastCol . '1');

        // خريطة القسم من bulk photo import للـ fallback
        $bulkSectionMap = [];
        try {
            $rows = BulkPhotoImportRow::whereNotNull('section_id')
                ->latest()->limit(5000)->get(['barcode', 'section_id']);
            foreach ($rows as $r) {
                if (!isset($bulkSectionMap[$r->barcode]) && $r->section_id) {
                    $bulkSectionMap[$r->barcode] = Section::find($r->section_id)?->name_ar;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $query = \App\Models\Product::with([
            'category:id,name_ar,name_en',
            'mall:id,name_ar',
            'section:id,name_ar,name_en',
            'mallSection:id,mall_id,section_id,parent_id,name_ar,name_en,updated_at',
            'mallSection.parent:id,name_ar,name_en,updated_at',
        ])->where('mall_id', $mall->id)->orderByDesc('created_at');

        $row = 2;
        // cursor() يقلل استهلاك الذاكرة مقارنة بـ get()
        foreach ($query->cursor() as $p) {
            $mainSection = null;
            $subSection = null;
            $sectionUpdatedAt = null;
            if ($p->mallSection) {
                if ($p->mallSection->parent) {
                    $mainSection = $p->mallSection->parent->name_ar;
                    $subSection = $p->mallSection->name_ar;
                    $sectionUpdatedAt = $p->mallSection->updated_at ?: $p->mallSection->parent->updated_at;
                } else {
                    $mainSection = $p->mallSection->name_ar;
                    $subSection = null;
                    $sectionUpdatedAt = $p->mallSection->updated_at;
                }
            } elseif ($p->section) {
                $mainSection = $p->section->name_ar;
                $subSection = null;
                $sectionUpdatedAt = null;
            } elseif (isset($bulkSectionMap[$p->barcode])) {
                $mainSection = $bulkSectionMap[$p->barcode];
            }

            $sheet->fromArray([
                $p->id,
                $p->name_ar,
                $p->name_en,
                $p->barcode,
                $p->sku,
                $p->price,
                $p->discount_price,
                $p->stock_quantity,
                $p->section?->name_ar,
                $mainSection,
                $subSection,
                $sectionUpdatedAt?->format('Y-m-d H:i'),
                $p->category?->name_ar,
                $p->mall?->name_ar,
                $p->unit,
                $p->brand,
                $p->link_photo,
                $p->is_active ? 'نعم' : 'لا',
                $p->created_at?->format('Y-m-d H:i'),
            ], null, 'A' . $row);

            if ($subSection) {
                $sheet->getStyle('K' . $row)->getFont()->setBold(true)->getColor()->setARGB('FF2563EB');
            }
            $row++;
        }

        foreach (range(1, count($headers)) as $idx) {
            $col = Coordinate::stringFromColumnIndex($idx);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * حفظ الملف مؤقتاً وإرجاع المسار واسم الملف.
     *
     * @return array{path: string, filename: string}
     */
    public function saveToTempFile(Mall $mall, ?string $dir = null): array
    {
        $dir = $dir ?: storage_path('app/temp/products-backup');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $this->buildFilename($mall);
        $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;

        $spreadsheet = $this->buildSpreadsheetForMall($mall);
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        // free memory
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return ['path' => $path, 'filename' => $filename];
    }
}
