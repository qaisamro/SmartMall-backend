<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\ProductImport;
use App\Models\MallSection;
use App\Models\Section;
use App\Models\Category;
use App\Notifications\ImportCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductsImport implements ShouldQueue
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
        // معالجة مسار الملف مع fallback للمسارات القديمة (private/imports vs imports)
        $path = $this->filePath;
        if (!file_exists($path)) {
            $candidates = [
                storage_path('app/private/' . ltrim($this->filePath, '/')),
                storage_path('app/' . ltrim($this->filePath, '/')),
                \Illuminate\Support\Facades\Storage::disk('local')->path($this->filePath),
                $this->filePath,
            ];
            foreach ($candidates as $cand) {
                if (file_exists($cand)) { $path = $cand; break; }
            }
        }
        if (!file_exists($path)) {
            $import = ProductImport::find($this->importId);
            if ($import) $import->update(['status' => 'failed', 'errors' => ['الملف غير موجود على السيرفر، يرجى إعادة رفع الملف']]);
            return;
        }
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, false);

        if (empty($rows)) return;

        $headers = array_shift($rows);
        $colMap = $this->mapHeadersNumeric($headers);

        $inserted = 0;
        $updated = 0;
        $batch = [];
        $seenBarcodes = [];

        foreach ($rows as $index => $raw) {
            $rowNum = $index + 2;
            $get = fn($key, $default = '') => isset($colMap[$key]) && isset($raw[$colMap[$key]]) ? $raw[$colMap[$key]] : $default;

            $barcode = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}]/u', '', trim($get('barcode', '')));
            if (empty($barcode) && isset($colMap['barcode_alt'])) {
                $barcode = trim($get('barcode_alt', ''));
            }
            $name = trim($get('name', $get('name_ar', '')));
            $nameEn = trim($get('name_en', ''));
            $price = $this->parsePrice($get('selling_price', $get('price', '')));
            $unit = trim($get('unit', ''));

            // حقول إضافية من ملف الباك اب الكامل
            $sku = trim($get('sku', ''));
            $discountPrice = $this->parsePrice($get('discount_price', ''));
            $stockQty = isset($colMap['stock_quantity']) ? (int) trim($get('stock_quantity', '0')) : 0;
            $brand = trim($get('brand', ''));
            $linkPhoto = trim($get('link_photo', ''));
            $isActiveRaw = trim($get('is_active', 'نعم'));
            $isActive = !in_array(mb_strtolower($isActiveRaw), ['لا', '0', 'false', 'no']);

            if (empty($barcode) && empty($name)) continue;

            if (empty($barcode)) {
                $barcode = 'IMP-' . strtoupper(\Illuminate\Support\Str::random(12));
            }

            // تحديد القسم (الرئيسي/الفرعي) من ملف الباك اب
            $mallSectionId = null;
            $mainSectionName = trim($get('main_section', $get('section_main', '')));
            $subSectionName = trim($get('sub_section', ''));
            $legacySection = trim($get('section_legacy', ''));

            if (!empty($mainSectionName) || !empty($subSectionName)) {
                $mallSectionId = $this->resolveMallSectionId($mainSectionName, $subSectionName);
            } elseif (!empty($legacySection)) {
                // fallback للقسم القديم (section name)
                $sec = Section::where('name_ar', $legacySection)->first();
                if ($sec) {
                    $ms = MallSection::where('mall_id', $this->mallId)->where('section_id', $sec->id)->whereNull('parent_id')->first();
                    if ($ms) $mallSectionId = $ms->id;
                }
            }

            $categoryId = null;
            $categoryName = trim($get('category', ''));
            if (!empty($categoryName)) {
                $cat = Category::where('name_ar', $categoryName)->first();
                if ($cat) $categoryId = $cat->id;
            }

            $data = [
                'mall_id' => $this->mallId,
                'barcode' => $barcode,
                'name_ar' => $name,
                'name_en' => $nameEn ?: $name,
                'price' => $price ?: 0,
                'discount_price' => $discountPrice ?: null,
                'sku' => $sku ?: null,
                'stock_quantity' => $stockQty,
                'brand' => $brand ?: null,
                'link_photo' => $linkPhoto ?: null,
                'unit' => $unit ?: null,
                'is_active' => $isActive,
                'category_id' => $categoryId,
                'mall_section_id' => $mallSectionId,
                'section_id' => null,
            ];

            // مزامنة section_id القديم للتوافق — دائماً موجود حتى لو null لضمان تطابق أعمدة الـ batch
            if ($mallSectionId) {
                $ms = MallSection::find($mallSectionId);
                if ($ms && $ms->section_id) $data['section_id'] = $ms->section_id;
                elseif ($ms && $ms->parent_id) {
                    $parent = MallSection::find($ms->parent_id);
                    if ($parent && $parent->section_id) $data['section_id'] = $parent->section_id;
                }
            }

            $existing = Product::where('mall_id', $this->mallId)
                ->where('barcode', $barcode)
                ->first();

            if ($existing) {
                $existing->update($data);
                $updated++;
            } elseif (isset($seenBarcodes[$barcode])) {
                continue;
            } else {
                $seenBarcodes[$barcode] = true;
                $batch[] = $data;
                if (count($batch) >= 100) {
                    Product::insert($batch);
                    $inserted += count($batch);
                    $batch = [];
                    $seenBarcodes = [];
                }
            }
        }

        if (!empty($batch)) {
            Product::insert($batch);
            $inserted += count($batch);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (isset($path) && file_exists($path)) {
            @unlink($path);
        } elseif (file_exists($this->filePath)) {
            @unlink($this->filePath);
        }

        $import = ProductImport::find($this->importId);
        if ($import) {
            $import->update([
                'status' => 'completed',
                'total_rows' => $inserted + $updated,
                'imported_rows' => $inserted,
                'inserted_rows' => $inserted,
                'updated_rows' => $updated,
                'skipped_rows' => 0,
                'failed_rows' => 0,
                'errors' => [],
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

    private function resolveMallSectionId(string $mainName, string $subName): ?int
    {
        if (empty($mainName) && empty($subName)) return null;

        // البحث عن القسم الرئيسي بالاسم
        $mainSection = null;
        if (!empty($mainName)) {
            $mainSection = MallSection::where('mall_id', $this->mallId)
                ->where('name_ar', $mainName)
                ->whereNull('parent_id')
                ->first();
            // إذا لم يوجد، ابحث في الأقسام العامة وأنشئ mall_section إن لزم
            if (!$mainSection) {
                $global = Section::where('name_ar', $mainName)->first();
                if ($global) {
                    $mainSection = MallSection::where('mall_id', $this->mallId)->where('section_id', $global->id)->first();
                }
                // إذا لا يزال غير موجود، أنشئ قسم مخصص
                if (!$mainSection) {
                    $mainSection = MallSection::create([
                        'mall_id' => $this->mallId,
                        'name_ar' => $mainName,
                        'name_en' => $mainName,
                        'is_active' => true,
                    ]);
                }
            }
        }

        if (!empty($subName) && $mainSection) {
            $sub = MallSection::where('mall_id', $this->mallId)
                ->where('parent_id', $mainSection->id)
                ->where('name_ar', $subName)
                ->first();
            if (!$sub) {
                $sub = MallSection::create([
                    'mall_id' => $this->mallId,
                    'parent_id' => $mainSection->id,
                    'name_ar' => $subName,
                    'name_en' => $subName,
                    'is_active' => true,
                ]);
            }
            return $sub->id;
        }

        return $mainSection?->id;
    }

    private function mapHeadersNumeric(array $headers): array
    {
        $keywords = [
            'barcode' => ['barcode', 'الباركود', 'باركود', 'رقم الباركود', 'barcod'],
            'name' => ['name', 'الاسم (عربي)', 'اسم المنتج', 'الاسم', 'product', 'المنتج', 'name_ar'],
            'name_ar' => ['الاسم (عربي)', 'name_ar', 'الاسم العربي'],
            'name_en' => ['الاسم (إنجليزي)', 'name_en', 'الاسم الانجليزي'],
            'selling_price' => ['selling_price', 'price', 'السعر', 'سعر', 'سعر_البيع', 'السعر '],
            'price' => ['السعر', 'سعر', 'price', 'selling_price'],
            'discount_price' => ['سعر الخصم', 'discount_price', 'الخصم'],
            'stock_quantity' => ['الكمية', 'stock_quantity', 'الكمية ', 'quantity'],
            'unit' => ['unit', 'الوحدة', 'وحدة', 'unite'],
            'sku' => ['sku', 'SKU'],
            'brand' => ['العلامة التجارية', 'brand', 'العلامة'],
            'link_photo' => ['رابط الصورة', 'link_photo', 'الصورة', 'image', 'photo'],
            'is_active' => ['فعال', 'is_active', 'نشط'],
            'main_section' => ['القسم الرئيسي (محدث)', 'القسم الرئيسي', 'main_section', 'القسم الرئيسي المحدث'],
            'sub_section' => ['القسم الفرعي', 'sub_section', 'القسم الفرعي '],
            'section_legacy' => ['القسم (قديم)', 'القسم', 'section', 'القسم القديم'],
            'category' => ['التصنيف', 'category', 'الفئة'],
            'section_main' => ['القسم الرئيسي', 'main_section'],
        ];

        $result = [];
        foreach ($headers as $col => $header) {
            $clean = mb_strtolower(trim($header));
            // إزالة الأقواس والمسافات الزائدة للمطابقة
            $clean = preg_replace('/\s*\(.*\)\s*/u', '', $clean);
            $clean = trim($clean);
            foreach ($keywords as $field => $aliases) {
                foreach ($aliases as $alias) {
                    $aliasClean = mb_strtolower(trim($alias));
                    $aliasClean = preg_replace('/\s*\(.*\)\s*/u', '', $aliasClean);
                    if ($clean === $aliasClean || str_contains($clean, $aliasClean) || str_contains($aliasClean, $clean)) {
                        if (!isset($result[$field])) $result[$field] = $col;
                        break 2;
                    }
                }
            }
        }

        // fallback للأسماء القديمة إذا لم يُكتشف
        if (!isset($result['barcode']) && isset($result['barcode_alt'])) $result['barcode'] = $result['barcode_alt'];
        if (!isset($result['name']) && isset($result['name_ar'])) $result['name'] = $result['name_ar'];

        return $result;
    }

    private function parsePrice($value): float
    {
        if (is_numeric($value)) return (float) $value;
        $cleaned = str_replace([',', ' ', '$', '₪'], '', trim($value));
        return is_numeric($cleaned) ? (float) $cleaned : 0;
    }
}
