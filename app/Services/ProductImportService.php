<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ProductImportService
{
    public function preview(string $filePath, int $mallId, array $options): array
    {
        $rows = $this->loadRows($filePath);
        $validated = $this->validateRows($rows, $mallId, $options);

        return [
            'rows' => array_slice($validated['rows'], 0, 30),
            'summary' => [
                'total_rows' => count($validated['rows']),
                'valid_rows' => $validated['valid_rows'],
                'invalid_rows' => $validated['invalid_rows'],
                'duplicate_rows' => $validated['duplicate_rows'],
                'errors' => $validated['errors'],
            ],
        ];
    }

    public function processImport(ProductImport $import): void
    {
        try {
            $filePath = Storage::disk('local')->path($import->file_path);
        } catch (\Exception $e) {
            // Fallback: try using the storage path directly
            $filePath = storage_path('app/' . $import->file_path);
        }
        $rows = $this->loadRows($filePath);
        $validated = $this->validateRows($rows, $import->mall_id, $import->options ?: []);

        $total = count($validated['rows']);
        $imported = 0;
        $failed = 0;
        $duplicateRows = $validated['duplicate_rows'];
        $errors = [];

        $import->update([
            'status' => 'processing',
            'started_at' => now(),
            'total_rows' => $total,
            'valid_rows' => $validated['valid_rows'],
            'duplicate_rows' => $duplicateRows,
            'errors' => [],
            'summary' => $validated['summary'],
        ]);

        // Pre-fetch all relevant data to avoid N+1 queries
        $duplicateBy = $import->options['duplicate_by'] ?? 'sku';
        $existingProducts = $this->prefetchExistingProducts($import->mall_id, $validated['rows'], $duplicateBy);
        $cachedCategories = $this->prefetchCategories($import->mall_id);

        foreach (array_chunk($validated['rows'], 200) as $chunkIndex => $chunk) {
            foreach ($chunk as $row) {
                if (!empty($row['issues'])) {
                    $failed++;
                    $errors[] = [
                        'row' => $row['row_number'],
                        'message' => implode('; ', $row['issues']),
                        'data' => $row['preview'],
                    ];
                    continue;
                }

                try {
                    $this->importRowOptimized($row, $import->mall_id, $import->options ?: [], $existingProducts, $cachedCategories);
                    $imported++;
                } catch (QueryException $exception) {
                    $failed++;
                    $errors[] = [
                        'row' => $row['row_number'],
                        'message' => 'Database error: ' . $exception->getMessage(),
                        'data' => $row['preview'],
                    ];
                } catch (\Exception $exception) {
                    $failed++;
                    $errors[] = [
                        'row' => $row['row_number'],
                        'message' => $exception->getMessage(),
                        'data' => $row['preview'],
                    ];
                }
            }

            $import->update([
                'imported_rows' => $imported,
                'failed_rows' => $failed,
                'errors' => $errors,
            ]);
        }

        $import->update([
            'status' => 'completed',
            'completed_at' => now(),
            'imported_rows' => $imported,
            'failed_rows' => $failed,
            'errors' => array_slice($errors, 0, 100), // Limit errors to prevent max_allowed_packet issues
        ]);
    }

    public function storeUploadedFile(UploadedFile $file): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        return $file->storeAs('private/imports', $filename, 'local');
    }

    public function generateTemplate(): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            [
                'Product Name',
                'SKU',
                'Category',
                'Description',
                'Price',
                'Discount Price',
                'Quantity',
                'Brand',
                'Image URL',
                'Status',
            ],
            [
                'منتج تجريبي',
                'SKU-1001',
                'إلكترونيات',
                'وصف مختصر للمنتج',
                '125.50',
                '99.99',
                '100',
                'SmartMall',
                'https://example.com/images/sample.jpg',
                'active',
            ],
            [
                'منتج أخر',
                'SKU-1002',
                'مستحضرات تجميل',
                'وصف المنتج الثاني',
                '45.00',
                '39.00',
                '30',
                'BeautyBrand',
                '',
                'inactive',
            ],
        ], null, 'A1');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'product-import-template.xlsx';
        $tempPath = sys_get_temp_dir() . '/' . $filename;
        $writer->save($tempPath);

        return $tempPath;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function loadRows(string $filePath): array
    {
        $reader = $this->resolveReader($filePath);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rawRows = $worksheet->toArray(null, true, true, true);

        if (empty($rawRows)) {
            return [];
        }

        $headers = array_shift($rawRows);
        $normalizedHeaders = $this->normalizeHeaders($headers);
        $rows = [];

        foreach ($rawRows as $index => $rawRow) {
            $rowNumber = $index + 2;
            $row = [];
            $empty = true;

            foreach ($normalizedHeaders as $column => $field) {
                $value = trim((string) ($rawRow[$column] ?? ''));
                if ($value !== '') {
                    $empty = false;
                }

                // If multiple columns map to the same field prefer the first non-empty value
                if (! array_key_exists($field, $row) || $row[$field] === '') {
                    $row[$field] = $value;
                }
            }

            if ($empty) {
                continue;
            }

            $rows[] = $this->normalizeRow($row, $rowNumber);
        }

        return $rows;
    }

    protected function resolveReader(string $filePath)
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => new Csv(),
            'xlsx' => new Xlsx(),
            'xls' => new Xls(),
            default => throw new \InvalidArgumentException('Unsupported file type.'),
        };
    }

    /**
     * @param array<string,string> $headers
     * @return array<string,string>
     */
    protected function normalizeHeaders(array $headers): array
    {
        $map = [];

        foreach ($headers as $column => $header) {
            $guessed = $this->guessFieldForHeader($header);
            if ($guessed !== null) {
                $map[$column] = $guessed;
                continue;
            }

            $normalized = $this->normalizeHeaderKey($header);

            if ($normalized === 'product_name') {
                $map[$column] = 'name';
            } elseif ($normalized === 'product_name_ar') {
                $map[$column] = 'name_ar';
            } elseif ($normalized === 'product_name_en') {
                $map[$column] = 'name_en';
            } elseif (in_array($normalized, ['name', 'name_en', 'name_ar', 'sku', 'barcode', 'category', 'description', 'description_ar', 'description_en', 'price', 'discount_price', 'quantity', 'qty', 'stock_quantity', 'brand', 'image_url', 'image', 'status', 'is_active'], true)) {
                $fieldMap = [
                    'qty' => 'stock_quantity',
                    'quantity' => 'stock_quantity',
                    'image' => 'image_url',
                    'is_active' => 'status',
                ];
                $map[$column] = $fieldMap[$normalized] ?? $normalized;
            } else {
                $map[$column] = strtolower(str_replace(' ', '_', $normalized));
            }
        }

        // If we couldn't detect any core fields, fall back to positional mapping
        $coreDetected = false;
        foreach ($map as $m) {
            if (in_array($m, ['name', 'name_ar', 'name_en', 'sku', 'category', 'price'], true)) {
                $coreDetected = true;
                break;
            }
        }

        if (! $coreDetected) {
            $fallback = ['name', 'sku', 'category', 'description', 'price', 'discount_price', 'stock_quantity', 'brand', 'image_url', 'status'];
            $i = 0;
            foreach (array_keys($headers) as $col) {
                if (isset($fallback[$i])) {
                    $map[$col] = $fallback[$i];
                } else {
                    $map[$col] = 'col_' . $i;
                }
                $i++;
            }
        }

        return $map;
    }

    protected function guessFieldForHeader(string $header): ?string
    {
        $clean = mb_strtolower(trim($header));
        $cleanAlnum = $this->normalizeHeaderKey($header);

        $candidates = [
            'name' => ['name', 'productname', 'product_name', 'product', 'senfname', 'nom', 'اسم', 'اسم_المنتج', 'اسم المنتج', 'ترك', 'snf', 'senf', 'productnamej', 'itemname', 'item_name', 'المنتج'],
            'sku' => ['sku', 'sku_code', 'saidnumber', 'said_number', 'رقم_الصنف', 'رقم الصنف', 'id'],
            'barcode' => ['barcode', 'barcodenumber', 'barcod', 'barcode_number', 'رقم_الباركود', 'باركود'],
            'category' => ['category', 'type', 'typesenf', 'type_senf', 'اقسام', 'قسم', 'cat', 'category_name', 'نوع', 'التصنيف'],
            'description' => ['description', 'desc', 'وصف', 'description_en', 'description_ar', 'الوصف'],
            'price' => ['price', 'pricall', 'pric_all', 'pric', 'سعر', 'price_all', 'السعر', 'سعر_البيع', 'namejozz', 'name_jozz'],
            'discount_price' => ['discount_price', 'special_price', 'offer_price', 'discount', 'pricejoz', 'pric_ejoz', 'jomlaprice', 'jomla_price', 'سعر_الجملة', 'سعر الجملة'],
            'stock_quantity' => ['quantity', 'qty', 'qount', 'qountetsel', 'qountitesel', 'count', 'عدد', 'stock', 'الكمية', 'كمية', 'المخزون'],
            'brand' => ['brand', 'ماركة', 'marka', 'العلامة'],
            'image_url' => ['image', 'image_url', 'picture', 'img', 'photo', 'صورة'],
            'status' => ['status', 'is_active', 'active', 'available', 'avalabile', 'حالة', 'الحالة'],
        ];

        foreach ($candidates as $field => $keywords) {
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && (str_contains($cleanAlnum, $keyword) || str_contains($clean, $keyword))) {
                    // For 'name' field, only match exact known column names to avoid false positives
                    if ($field === 'name' && in_array($cleanAlnum, ['name', 'productname', 'product_name', 'senfname', 'snf', 'senf', 'itemname'], true)) {
                        return 'name';
                    }
                    return $field;
                }
            }
        }

        return null;
    }

    protected function normalizeHeaderKey(string $value): string
    {
        return strtolower(preg_replace('/[^\\p{L}0-9_ ]+/u', '', str_replace(['-', '.', '/'], '_', trim($value))));
    }

    protected function normalizeRow(array $row, int $rowNumber): array
    {
        $nameAr = trim($row['name_ar'] ?? $row['name'] ?? '');
        $nameEn = trim($row['name_en'] ?? $row['name'] ?? '');
        $description = trim($row['description'] ?? '');
        $descriptionAr = trim($row['description_ar'] ?? $description);
        $descriptionEn = trim($row['description_en'] ?? $description);

        return [
            'row_number' => $rowNumber,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'sku' => trim($row['sku'] ?? ''),
            'barcode' => trim($row['barcode'] ?? ''),
            'category' => trim($row['category'] ?? ''),
            'description_ar' => $descriptionAr,
            'description_en' => $descriptionEn,
            'price' => trim($row['price'] ?? ''),
            'discount_price' => trim($row['discount_price'] ?? ''),
            'stock_quantity' => trim($row['stock_quantity'] ?? $row['quantity'] ?? ''),
            'brand' => trim($row['brand'] ?? ''),
            'image_url' => trim($row['image_url'] ?? ''),
            'status' => trim($row['status'] ?? ''),
            'preview' => [
                'Product Name' => $nameAr ?: $nameEn,
                'SKU' => $row['sku'] ?? '',
                'Category' => $row['category'] ?? '',
                'Description' => $description ?: '',
                'Price' => $row['price'] ?? '',
                'Discount Price' => $row['discount_price'] ?? '',
                'Quantity' => $row['stock_quantity'] ?? '',
                'Brand' => $row['brand'] ?? '',
                'Image URL' => $row['image_url'] ?? '',
                'Status' => $row['status'] ?? '',
            ],
        ];
    }

    protected function validateRows(array $rows, int $mallId, array $options): array
    {
        $duplicateBy = $options['duplicate_by'] ?? 'sku';
        $duplicateStrategy = $options['duplicate_strategy'] ?? 'skip';
        $autoCreateCategories = filter_var($options['auto_create_categories'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $errors = [];
        $validRows = 0;
        $invalidRows = 0;
        $duplicateRows = 0;
        $seen = [];
        $output = [];

        // Pre-fetch duplicates for the entire set to use in validation loop
        $prefetchedDuplicates = $this->prefetchExistingProducts($mallId, $rows, $duplicateBy);

        foreach ($rows as $row) {
            $issues = [];
            $preview = $row['preview'];
            $duplicateFound = false;

            if ($row['name_ar'] === '' && $row['name_en'] === '') {
                $issues[] = 'Missing product name';
            }
            if ($row['category'] === '') {
                if (! $autoCreateCategories) {
                    $issues[] = 'Missing category';
                } else {
                    // Will be auto-created during import; mark as note but not a blocking issue
                }
            }

            if ($row['price'] === '' || !is_numeric(str_replace([',', '₪', '$'], '', $row['price']))) {
                $issues[] = 'Price must be a valid number';
            }

            if ($row['stock_quantity'] === '' || !ctype_digit(strval($row['stock_quantity']))) {
                $issues[] = 'Quantity must be an integer';
            }

            if ($row['discount_price'] !== '' && !is_numeric(str_replace([',', '₪', '$'], '', $row['discount_price']))) {
                $issues[] = 'Discount price must be a valid number';
            }

            $duplicateKey = $this->detectDuplicateKey($row, $duplicateBy);

            // Check in-memory pre-fetched data
            if ($duplicateKey !== null) {
                if (isset($seen[$duplicateKey]) || isset($prefetchedDuplicates[$duplicateKey])) {
                    $duplicateFound = true;
                }
            }

            if ($duplicateFound) {
                $duplicateRows++;
            }

            if ($issues === []) {
                $validRows++;
            } else {
                $invalidRows++;
                $errors[] = [
                    'row' => $row['row_number'],
                    'issues' => $issues,
                    'preview' => $preview,
                ];
            }

            if ($duplicateKey !== null) {
                $seen[$duplicateKey] = true;
            }

            $output[] = array_merge($row, [
                'issues' => $issues,
                'duplicate' => $duplicateFound,
                'duplicate_by' => $duplicateBy,
                'auto_create_categories' => $autoCreateCategories,
                'preview' => $preview,
            ]);
        }

        return [
            'rows' => $output,
            'errors' => $errors,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'duplicate_rows' => $duplicateRows,
            'summary' => [
                'total_rows' => count($output),
                'valid_rows' => $validRows,
                'invalid_rows' => $invalidRows,
                'duplicate_rows' => $duplicateRows,
                'auto_create_categories' => $autoCreateCategories,
                'duplicate_strategy' => $duplicateStrategy,
                'duplicate_by' => $duplicateBy,
            ],
        ];
    }

    protected function detectDuplicateKey(array $row, string $duplicateBy): ?string
    {
        return match ($duplicateBy) {
            'barcode' => $row['barcode'] !== '' ? 'barcode:' . $row['barcode'] : null,
            'sku' => $row['sku'] !== '' ? 'sku:' . $row['sku'] : null,
            'name' => $row['name_ar'] !== '' ? 'name:' . mb_strtolower($row['name_ar']) : ($row['name_en'] !== '' ? 'name:' . mb_strtolower($row['name_en']) : null),
            default => null,
        };
    }

    protected function findExistingDuplicate(int $mallId, array $row, string $duplicateBy): ?Product
    {
        if ($duplicateBy === 'barcode' && $row['barcode'] !== '') {
            // Check globally because the database constraint is unique on barcode
            return Product::where('barcode', $row['barcode'])->first();
        }

        if ($duplicateBy === 'sku' && $row['sku'] !== '') {
            return Product::where('mall_id', $mallId)->where('sku', $row['sku'])->first();
        }

        if ($duplicateBy === 'name' && $row['name_ar'] !== '') {
            return Product::where('mall_id', $mallId)
                ->where(function ($query) use ($row) {
                    $query->where('name_ar', $row['name_ar'])
                          ->orWhere('name_en', $row['name_en']);
                })->first();
        }

        return null;
    }

    protected function importRow(array $row, int $mallId, array $options): void
    {
        $duplicateBy = $options['duplicate_by'] ?? 'sku';
        $strategy = $options['duplicate_strategy'] ?? 'skip';
        $autoCreateCategories = filter_var($options['auto_create_categories'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $category = $this->resolveCategory($mallId, $row['category'], $autoCreateCategories);
        if ($category === null) {
            throw new \RuntimeException('Category not found and auto-create is disabled.');
        }

        $productData = [
            'mall_id' => $mallId,
            'category_id' => $category->id,
            'name_ar' => $row['name_ar'],
            'name_en' => $row['name_en'],
            'description_ar' => $row['description_ar'],
            'description_en' => $row['description_en'],
            'price' => $this->parseNumeric($row['price']),
            'discount_price' => $row['discount_price'] !== '' ? $this->parseNumeric($row['discount_price']) : null,
            'stock_quantity' => (int) $row['stock_quantity'],
            'brand' => $row['brand'] ?: null,
            'sku' => $row['sku'] ?: null,
            'barcode' => $row['barcode'] ?: Str::upper(Str::random(12)),
            'is_active' => $this->parseStatus($row['status']),
        ];

        $existing = $this->findExistingDuplicate($mallId, $row, $duplicateBy);
        if ($existing !== null) {
            if ($strategy === 'skip') {
                return;
            }
            if ($strategy === 'update') {
                if ($row['image_url'] !== '') {
                    $productData['image'] = $this->downloadImage($row['image_url']);
                }
                $existing->update($productData);
                return;
            }
        }

        if ($row['image_url'] !== '') {
            $productData['image'] = $this->downloadImage($row['image_url']);
        }

        Product::create($productData);
    }

    protected function resolveCategory(int $mallId, string $categoryName, bool $autoCreate): ?Category
    {
        // If incoming category name is empty and auto-create is enabled, use a default name
        if ($categoryName === '' && $autoCreate) {
            $categoryName = 'Uncategorized';
        }

        $category = Category::where('mall_id', $mallId)
            ->where(function ($query) use ($categoryName) {
                $query->where('name_ar', $categoryName)
                      ->orWhere('name_en', $categoryName);
            })->first();

        if ($category !== null) {
            return $category;
        }

        if (! $autoCreate) {
            return null;
        }

        return Category::create([
            'mall_id' => $mallId,
            'name_ar' => $categoryName,
            'name_en' => $categoryName,
        ]);
    }

    protected function parseNumeric(string $value): float
    {
        return (float) str_replace([',', '₪', '$'], '', trim($value));
    }

    protected function parseStatus(string $value): bool
    {
        if ($value === '') {
            return true;
        }

        $lower = strtolower(trim($value));

        // If it's a number, 0 is inactive, others active
        if (is_numeric($lower)) {
            return (int)$lower !== 0;
        }

        // Common negative indicators
        if (in_array($lower, ['inactive', 'no', 'false', 'n', '0', 'مغلق', 'غير نشط', 'غير متوفر'], true)) {
            return false;
        }

        return true;
    }

    protected function prefetchExistingProducts(int $mallId, array $rows, string $duplicateBy): array
    {
        $keys = [];
        foreach ($rows as $row) {
            if ($duplicateBy === 'barcode' && !empty($row['barcode'])) {
                $keys[] = $row['barcode'];
            } elseif ($duplicateBy === 'sku' && !empty($row['sku'])) {
                $keys[] = $row['sku'];
            } elseif ($duplicateBy === 'name') {
                if (!empty($row['name_ar'])) $keys[] = $row['name_ar'];
                if (!empty($row['name_en'])) $keys[] = $row['name_en'];
            }
        }

        if (empty($keys)) return [];

        $query = Product::query();
        if ($duplicateBy === 'barcode') {
            $products = $query->whereIn('barcode', $keys)->get();
            $map = [];
            foreach ($products as $p) {
                $map[$p->barcode] = $p;
            }
            return $map;
        } elseif ($duplicateBy === 'sku') {
            $products = $query->where('mall_id', $mallId)->whereIn('sku', $keys)->get();
            $map = [];
            foreach ($products as $p) {
                $map[$p->sku] = $p;
            }
            return $map;
        } elseif ($duplicateBy === 'name') {
            $products = $query->where('mall_id', $mallId)
                ->where(function($q) use ($keys) {
                    $q->whereIn('name_ar', $keys)->orWhereIn('name_en', $keys);
                })->get();

            $map = [];
            foreach ($products as $p) {
                $map[mb_strtolower($p->name_ar)] = $p;
                $map[mb_strtolower($p->name_en)] = $p;
            }
            return $map;
        }

        return [];
    }

    protected function prefetchCategories(int $mallId): array
    {
        return Category::where('mall_id', $mallId)->get()->mapWithKeys(function ($item) {
            return [mb_strtolower($item->name_ar) => $item, mb_strtolower($item->name_en) => $item];
        })->all();
    }

    protected function importRowOptimized(array $row, int $mallId, array $options, array &$existingProducts, array &$cachedCategories): void
    {
        $duplicateBy = $options['duplicate_by'] ?? 'sku';
        $strategy = $options['duplicate_strategy'] ?? 'skip';
        $autoCreateCategories = filter_var($options['auto_create_categories'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $catName = mb_strtolower(trim($row['category']));
        if ($catName === '') $catName = 'uncategorized';

        if (!isset($cachedCategories[$catName])) {
            if (!$autoCreateCategories) {
                throw new \RuntimeException('Category not found: ' . $row['category']);
            }
            $newCat = Category::create([
                'mall_id' => $mallId,
                'name_ar' => $row['category'] ?: 'عام',
                'name_en' => $row['category'] ?: 'General',
            ]);
            $cachedCategories[mb_strtolower($newCat->name_ar)] = $newCat;
            $cachedCategories[mb_strtolower($newCat->name_en)] = $newCat;
            $catId = $newCat->id;
        } else {
            $catId = $cachedCategories[$catName]->id;
        }

        $productData = [
            'mall_id' => $mallId,
            'category_id' => $catId,
            'name_ar' => $row['name_ar'],
            'name_en' => $row['name_en'],
            'description_ar' => $row['description_ar'],
            'description_en' => $row['description_en'],
            'price' => $this->parseNumeric($row['price']),
            'discount_price' => $row['discount_price'] !== '' ? $this->parseNumeric($row['discount_price']) : null,
            'stock_quantity' => (int) $row['stock_quantity'],
            'brand' => $row['brand'] ?: null,
            'sku' => $row['sku'] ?: null,
            'barcode' => $row['barcode'] ?: Str::upper(Str::random(12)),
            'is_active' => $this->parseStatus($row['status']),
        ];

        // Find existing using pre-fetched map
        $existing = null;
        if ($duplicateBy === 'barcode' && !empty($row['barcode'])) {
            $existing = $existingProducts[$row['barcode']] ?? null;
        } elseif ($duplicateBy === 'sku' && !empty($row['sku'])) {
            $existing = $existingProducts[$row['sku']] ?? null;
        } elseif ($duplicateBy === 'name') {
            $existing = $existingProducts[mb_strtolower($row['name_ar'])] ?? $existingProducts[mb_strtolower($row['name_en'])] ?? null;
        }

        if ($existing !== null) {
            if ($strategy === 'skip') return;
            if ($strategy === 'update') {
                if ($row['image_url'] !== '') {
                    $productData['image'] = $this->downloadImage($row['image_url']);
                }
                $existing->update($productData);
                return;
            }
        }

        if ($row['image_url'] !== '') {
            $productData['image'] = $this->downloadImage($row['image_url']);
        }

        Product::create($productData);
    }
}
