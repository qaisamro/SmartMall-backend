<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\BulkPhotoImport;
use App\Models\BulkPhotoImportRow;
use App\Models\MissingImageReport;
use App\Models\Product;
use App\Models\ProductBarcodeOverride;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AdminBulkPhotoUploadController extends Controller
{
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:30720',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        if (empty($rows) || count($rows) < 2) {
            return response()->json(['message' => 'الملف فارغ'], 422);
        }

        $header = array_map('trim', $rows[0]);
        $barcodeIdx = array_search('barcode', $header);
        $photoIdx = array_search('link_photo', $header);
        $sectionIdx = array_search('section_id', $header);

        if ($barcodeIdx === false) {
            return response()->json(['message' => 'الملف يجب أن يحتوي على عمود barcode'], 422);
        }

        $import = BulkPhotoImport::create([
            'user_id' => auth()->id(),
            'file_name' => $file->getClientOriginalName(),
            'updated_count' => 0,
            'skipped_count' => 0,
            'errors' => [],
        ]);

        $updated = 0;
        $skipped = 0;
        $overrides = 0;
        $errors = [];

        for ($i = 1; $i < count($rows); $i++) {
            $raw = $rows[$i];

            $barcodeRaw = $raw[$barcodeIdx] ?? '';
            if (is_float($barcodeRaw)) {
                $barcode = sprintf('%.0f', $barcodeRaw);
            } else {
                $barcode = trim((string) $barcodeRaw);
            }

            if ($barcode === '') {
                $skipped++;
                continue;
            }

            $barcode = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}]/u', '', $barcode);
            $barcode = trim(preg_replace('/[^\PC\s]/u', '', $barcode));

            $hasPhoto = false;
            $linkPhoto = null;
            if ($photoIdx !== false && !empty($raw[$photoIdx])) {
                $linkPhoto = trim((string) $raw[$photoIdx]);
                $hasPhoto = true;
            }

            $hasSection = false;
            $sectionId = null;
            if ($sectionIdx !== false && !empty($raw[$sectionIdx]) && is_numeric($raw[$sectionIdx])) {
                $section = Section::find((int) $raw[$sectionIdx]);
                if ($section) {
                    $sectionId = (int) $raw[$sectionIdx];
                    $hasSection = true;
                }
            }

            $products = static::matchingProducts($barcode);
            $first = $products->first();

            $status = 'skipped';
            $mallNames = '';

            if ($products->isNotEmpty()) {
                $updateData = [];
                if ($hasPhoto) $updateData['link_photo'] = $linkPhoto;
                if ($hasSection) $updateData['section_id'] = $sectionId;

                if (!empty($updateData)) {
                    Product::whereIn('id', $products->pluck('id'))->update($updateData);
                    $updated++;
                    $status = 'updated';
                }

                $mallNames = $products->load('mall:id,name_ar')
                    ->pluck('mall.name_ar')
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode('، ');
            }

            if ($hasPhoto || $hasSection) {
                static::upsertOverride($barcode, $linkPhoto, $sectionId);
                if ($status === 'skipped') {
                    $status = 'override';
                    $overrides++;
                }
            }

            if ($status === 'skipped') {
                $skipped++;
            }

            if ($status === 'override') {
                $errors[] = "الباركود {$barcode} لا يطابق منتجات حالياً وسيُطبَّق تلقائياً على أي منتج بنفس الباركود في كل المولات (صف {$i})";
            }

            BulkPhotoImportRow::create([
                'bulk_photo_import_id' => $import->id,
                'product_id' => $first?->id,
                'barcode' => $barcode,
                'link_photo' => ($status === 'updated' || $status === 'override') ? $linkPhoto : ($first?->link_photo),
                'section_id' => ($status === 'updated' || $status === 'override') ? $sectionId : ($first?->section_id),
                'section_name' => ($status === 'updated' || $status === 'override') ? ($sectionId ? Section::find($sectionId)?->name_ar : '') : ($first?->section?->name_ar ?? ''),
                'product_name' => $first?->name_ar ?? '',
                'mall_name' => $mallNames,
                'status' => $status,
            ]);
        }

        $import->update([
            'updated_count' => $updated,
            'skipped_count' => $skipped,
            'errors' => $errors,
        ]);

        return response()->json([
            'message' => "تم التحديث: {$updated} منتج في كل المولات، تم الحفظ للمستقبل: {$overrides}، تم التجاهل: {$skipped}",
            'updated' => $updated,
            'skipped' => $skipped,
            'overrides' => $overrides,
            'errors' => $errors,
            'import_id' => $import->id,
        ]);
    }

    public function results(Request $request)
    {
        $import = BulkPhotoImport::with(['rows'])->latest()->first();

        if (!$import) {
            return response()->json(['data' => [], 'file_name' => '', 'errors' => [], 'message' => '', 'total' => 0, 'per_page' => 20, 'current_page' => 1, 'last_page' => 1]);
        }

        $all = $import->rows->toArray();
        $search = $request->query('search', '');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        if ($search) {
            $all = array_values(array_filter($all, fn($r) => str_contains($r['barcode'] ?? '', $search)));
        }

        $total = count($all);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;
        $items = array_slice($all, $offset, $perPage);

        return response()->json([
            'data' => $items,
            'file_name' => $import->file_name,
            'import_id' => $import->id,
            'errors' => $import->errors ?? [],
            'message' => "تم التحديث: {$import->updated_count} منتج في كل المولات، تم التجاهل: {$import->skipped_count}",
            'updated' => $import->updated_count,
            'skipped' => $import->skipped_count,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage,
        ]);
    }

    public function updateRow(Request $request, $rowId)
    {
        $row = BulkPhotoImportRow::findOrFail($rowId);

        $request->validate([
            'link_photo' => 'nullable|string',
            'section_id' => 'nullable|integer',
            'barcode'    => 'nullable|string|max:255',
        ]);

        $oldBarcode = $row->barcode;
        $barcode = $request->filled('barcode') ? trim($request->barcode) : $oldBarcode;

        $products = static::matchingProducts($oldBarcode);

        $updateData = [];
        if ($request->has('link_photo')) $updateData['link_photo'] = $request->link_photo;
        if ($request->has('section_id')) $updateData['section_id'] = $request->section_id ? (int) $request->section_id : null;

        if (!empty($updateData) && $products->isNotEmpty()) {
            Product::whereIn('id', $products->pluck('id'))->update($updateData);
        }

        if (!empty($updateData) || $barcode !== $oldBarcode) {
            static::upsertOverride($barcode, $updateData['link_photo'] ?? null, $updateData['section_id'] ?? null);
            if ($barcode !== $oldBarcode) {
                ProductBarcodeOverride::where('barcode', $oldBarcode)->delete();
            }
        }

        $first = static::matchingProducts($barcode)->first();

        $row->update([
            'link_photo' => $request->has('link_photo') ? $request->link_photo : $row->link_photo,
            'section_id' => $request->has('section_id') ? ($request->section_id ? (int) $request->section_id : null) : $row->section_id,
            'barcode' => $barcode,
        ]);

        if ($row->section_id) {
            $row->update(['section_name' => Section::find($row->section_id)?->name_ar ?? '']);
        }

        if ($first) {
            $mallNames = static::matchingProducts($barcode)
                ->load('mall:id,name_ar')
                ->pluck('mall.name_ar')
                ->filter()
                ->unique()
                ->values()
                ->implode('، ');
            $row->update([
                'product_name' => $first->name_ar,
                'mall_name' => $mallNames,
                'product_id' => $first->id,
            ]);
        } else {
            $row->update(['product_id' => null, 'mall_name' => '']);
        }

        return response()->json($row->fresh());
    }

    public function destroyRow($rowId)
    {
        $row = BulkPhotoImportRow::findOrFail($rowId);
        ProductBarcodeOverride::where('barcode', $row->barcode)->delete();
        $row->delete();
        return response()->json(['message' => 'تم حذف الصف']);
    }

    public function destroyAll(Request $request)
    {
        BulkPhotoImport::where('user_id', auth()->id())->delete();
        return response()->json(['message' => 'تم حذف جميع السجلات']);
    }

    public function lookup(Request $request)
    {
        $barcode = trim((string) $request->query('barcode', ''));
        if ($barcode === '') {
            return response()->json(['data' => null, 'found' => false]);
        }

        $import = BulkPhotoImport::with(['rows'])->latest()->first();

        $row = null;
        if ($import) {
            $normScanned = ltrim($barcode, '0');
            $row = $import->rows->first(fn($r) => ltrim((string) $r->barcode, '0') === $normScanned && $normScanned !== '');
        }

        if (!$row) {
            return response()->json(['data' => null, 'found' => false]);
        }

        return response()->json(['data' => $row->toArray(), 'found' => true]);
    }

    public function export(Request $request)
    {
        $import = BulkPhotoImport::with(['rows'])->latest()->first();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);
        $headers = ['barcode', 'link_photo', 'section_id'];
        $sheet->fromArray([$headers], null, 'A1');
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);

        $rowIndex = 2;
        if ($import) {
            foreach ($import->rows as $r) {
                $sheet->fromArray([
                    $r->barcode,
                    $r->link_photo,
                    $r->section_id,
                ], null, 'A' . $rowIndex);
                $rowIndex++;
            }
        }

        foreach (['A', 'B', 'C'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'bulk-photo-template-' . now()->format('Y-m-d-His') . '.xlsx';
        $tempPath = sys_get_temp_dir() . '/' . $filename;
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }

    private static function matchingProducts(string $barcode)
    {
        return Product::where('barcode', $barcode)
            ->orWhere('barcode', ltrim($barcode, '0'))
            ->orWhere('barcode', '0' . $barcode)
            ->get(['id', 'mall_id', 'name_ar', 'barcode', 'link_photo', 'section_id']);
    }

    private static function upsertOverride(string $barcode, ?string $linkPhoto, ?int $sectionId): void
    {
        if (!$linkPhoto && !$sectionId) return;

        $data = [];
        if ($linkPhoto !== null) $data['link_photo'] = $linkPhoto;
        if ($sectionId !== null) $data['section_id'] = $sectionId;

        ProductBarcodeOverride::updateOrCreate(['barcode' => $barcode], $data);
    }

    /**
     * Shared report of products without images — visible to all admins.
     */
    public function missingReportIndex()
    {
        $reports = MissingImageReport::orderByDesc('id')->get()->map(function ($r) {
            return [
                'id'      => $r->id,
                'barcode' => $r->barcode,
                'name'    => $r->name,
                'type'    => $r->type,
                'created_at' => $r->created_at?->toISOString(),
            ];
        });

        return response()->json(['data' => $reports]);
    }

    public function missingReportStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string',
            'name'    => 'nullable|string|max:255',
            'type'    => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $barcode = $request->input('barcode');
        $exists = MissingImageReport::where('barcode', $barcode)->exists();
        if ($exists) {
            return response()->json(['message' => 'هذا الباركود محفوظ مسبقاً في التقرير'], 409);
        }

        $report = MissingImageReport::create([
            'user_id' => auth()->id(),
            'barcode' => $barcode,
            'name'    => $request->input('name'),
            'type'    => $request->input('type') ?: 'لا توجد صورة',
        ]);

        return response()->json(['data' => $report, 'message' => 'تم الحفظ في التقرير'], 201);
    }

    public function missingReportDestroy(Request $request, $reportId)
    {
        $report = MissingImageReport::find($reportId);
        if (!$report) {
            return response()->json(['message' => 'غير موجود'], 404);
        }
        $report->delete();
        return response()->json(['message' => 'تم الحذف']);
    }

    public function missingReportDestroyAll()
    {
        MissingImageReport::truncate();
        return response()->json(['message' => 'تم حذف جميع السجلات من التقرير']);
    }
}
