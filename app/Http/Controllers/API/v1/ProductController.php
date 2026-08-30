<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Helpers\ActivityLogger;
use App\Services\ProductExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category:id,name_ar,name_en', 'shelves:id', 'mall:id,name_ar', 'section:id,name_ar,name_en', 'mallSection:id,name_ar,name_en', 'barcodeOverride'])
            ->where('is_active', true);
        if ($request->has('mall_id')) $query->where('mall_id', $request->mall_id);
        if ($request->has('category_id')) $query->where('category_id', $request->category_id);
        if ($request->has('search')) {
            $search = trim($request->search);
            $query->where(fn($q) => $q->where('name_ar','like',"%{$search}%")->orWhere('name_en','like',"%{$search}%")->orWhere('barcode','like',"%{$search}%"));
        }
        return response()->json($query->latest()->simplePaginate(20));
    }

    public function show($id)
    {
        return response()->json(
            Product::with(['shelves:id', 'category:id,name_ar,name_en', 'section:id,name_ar,name_en', 'mallSection:id,name_ar,name_en', 'barcodeOverride'])
                ->select('id', 'mall_id', 'category_id', 'section_id', 'mall_section_id', 'name_ar', 'name_en', 'description_ar', 'description_en',
                    'price', 'discount_price', 'barcode', 'image', 'link_photo', 'is_active', 'hide_stock_from_customer')
                ->findOrFail($id)
        );
    }

    public function ownerProducts(Request $request)
    {
        $mallIds = $request->user()->malls()->pluck('id');
        $query = Product::with(['category:id,name_ar,name_en', 'section:id,name_ar,name_en', 'mallSection:id,name_ar,name_en', 'barcodeOverride'])
            ->whereIn('mall_id', $mallIds);
        if ($request->filled('mall_id')) $query->where('mall_id', $request->input('mall_id'));
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function($q) use ($search, $mallIds) {
                $subMatch = \App\Models\SubBarcode::whereIn('mall_id', $mallIds)->where('sub_barcode','like',"%{$search}%")->pluck('product_id')->filter()->unique()->values();
                $q->where('name_ar','like',"%{$search}%")->orWhere('name_en','like',"%{$search}%")->orWhere('barcode','like',"%{$search}%")->orWhere('sku','like',"%{$search}%");
                if ($subMatch->isNotEmpty()) $q->orWhereIn('id', $subMatch);
            });
        }
        // Per-mall section filter
        if ($request->filled('mall_section_id') && $request->input('mall_section_id') !== '') {
            $mallSectionId = $request->input('mall_section_id');
            if ((int)$mallSectionId === -1) {
                $query->whereNull('mall_section_id')->whereNull('section_id');
            } else {
                $mallSection = \App\Models\MallSection::find($mallSectionId);
                $query->where(function($q) use ($mallSectionId, $mallSection) {
                    $q->where('mall_section_id', $mallSectionId);
                    if ($mallSection && $mallSection->parent_id === null) {
                        $childIds = \App\Models\MallSection::where('parent_id', $mallSection->id)->where('is_active', true)->pluck('id');
                        if ($childIds->isNotEmpty()) $q->orWhereIn('mall_section_id', $childIds);
                        if ($mallSection->section_id) $q->orWhere(fn($qq) => $qq->whereNull('mall_section_id')->where('section_id', $mallSection->section_id));
                    }
                });
            }
        }
        return response()->json($query->orderByRaw("COALESCE(NULLIF(name_ar, ''), name_en) ASC")->paginate((int) $request->input('per_page', 20)));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'mall_id' => 'required|exists:malls,id',
            'category_id' => 'nullable|exists:categories,id',
            'mall_section_id' => 'nullable|exists:mall_sections,id',
            'name_ar' => 'required|string',
            'name_en' => 'required|string',
            'price' => 'required|numeric|min:0',
            'sku' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
        ]);
        $mall = \App\Models\Mall::findOrFail($validated['mall_id']);
        $mallIds = $request->user()->malls()->pluck('id');
        if (!$mallIds->contains((int)$validated['mall_id'])) return response()->json(['message' => 'You do not own this mall.'], 403);
        $data = $request->only(['mall_id','category_id','mall_section_id','section_id','name_ar','name_en','description_ar','description_en','price','discount_price','brand','sku','image','link_photo','shelf_location','stock_quantity']);
        if (!empty($data['mall_section_id'])) {
            $linkedSectionId = \App\Models\MallSection::where('id', $data['mall_section_id'])->where('mall_id', $data['mall_id'])->value('section_id');
            if ($linkedSectionId && empty($data['section_id'])) $data['section_id'] = $linkedSectionId;
        }
        $autoBarcode = !$request->filled('barcode');
        $barcode = $autoBarcode ? strtoupper(Str::random(12)) : $request->barcode;
        $product = Product::create(array_merge($data, ['barcode'=>$barcode,'link_photo'=>$request->input('link_photo') ?: null]));
        ActivityLogger::log('product_created', 'إضافة منتج: ' . $product->name_ar, $product, null, $product->mall_id, ['price' => $product->price]);
        return response()->json($product->load('category'), 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $updates = $request->only(['name_ar','name_en','description_ar','description_en','price','discount_price','brand','sku','image','link_photo','is_active','barcode','shelf_location','section_id','mall_section_id','stock_quantity','category_id']);
        if (array_key_exists('mall_section_id', $updates)) {
            if ($updates['mall_section_id']) {
                $ms = \App\Models\MallSection::where('id', $updates['mall_section_id'])->where('mall_id', $product->mall_id)->first();
                if ($ms) $updates['section_id'] = $ms->section_id;
            } elseif (empty($updates['section_id'])) $updates['section_id'] = null;
        }
        $product->update($updates);
        ActivityLogger::log('product_updated', 'تحديث منتج: ' . $product->name_ar, $product, null, $product->mall_id);
        return response()->json($product->load('category'));
    }

    public function destroy($id) { $p = Product::findOrFail($id); ActivityLogger::log('product_deleted', 'حذف منتج: ' . $p->name_ar, $p, null, $p->mall_id); $p->delete(); return response()->json(['message' => 'Product deleted']); }
    public function destroyAll(Request $request) { $mallIds = $request->user()->malls()->pluck('id'); $c = Product::whereIn('mall_id', $mallIds)->delete(); return response()->json(['message' => "Deleted {$c} products"]); }

    public function adminIndex(Request $request)
    {
        $query = Product::with(['category:id,name_ar,name_en', 'mall:id,name_ar', 'section:id,name_ar,name_en', 'mallSection:id,name_ar,name_en'])->orderByDesc('created_at');
        if ($request->filled('mall_id')) $query->where('mall_id', $request->mall_id);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name_ar','like',"%{$s}%")->orWhere('barcode','like',"%{$s}%"));
        }
        return response()->json($query->paginate((int) $request->input('per_page', 20)));
    }

    public function adminLookupByBarcode(Request $request)
    {
        $request->validate(['mall_id'=>'required|exists:malls,id','barcode'=>'required|string']);
        $barcode = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}]/u', '', trim($request->barcode));
        $product = Product::where('mall_id', $request->mall_id)->where('barcode', $barcode)->first();
        if (!$product) return response()->json(['found'=>false,'data'=>null]);
        return response()->json(['found'=>true,'data'=>$product->only(['id','name_ar','name_en','price','barcode','category_id','section_id','link_photo','unit'])]);
    }

    public function exportExcel(Request $request)
    {
        $query = Product::with(['category:id,name_ar,name_en','mall:id,name_ar','section:id,name_ar,name_en','mallSection:id,mall_id,section_id,parent_id,name_ar,name_en,updated_at','mallSection.parent:id,name_ar,name_en,updated_at']);
        if ($request->filled('mall_id')) $query->where('mall_id', $request->mall_id);
        if ($request->filled('search')) { $s = $request->search; $query->where(fn($q) => $q->where('name_ar','like',"%{$s}%")->orWhere('barcode','like',"%{$s}%")); }
        $products = $query->orderByDesc('created_at')->get();
        $bulkSectionMap = [];
        try {
            $rows = \App\Models\BulkPhotoImportRow::whereNotNull('section_id')->latest()->limit(5000)->get(['barcode','section_id']);
            foreach ($rows as $r) if (!isset($bulkSectionMap[$r->barcode]) && $r->section_id) $bulkSectionMap[$r->barcode] = \App\Models\Section::find($r->section_id)?->name_ar;
        } catch (\Throwable $e) {}
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        if ($request->input('format') === 'template') {
            $headers = ['barcode', 'name', 'selling_price', 'unit'];
            $sheet->fromArray([$headers], null, 'A1');
            $sheet->getStyle('A1:' . chr(64 + count($headers)) . '1')->getFont()->setBold(true);
            $row = 2;
            foreach ($products as $p) { $sheet->fromArray([$p->barcode, $p->name_ar ?: $p->name_en, $p->price, $p->unit], null, 'A' . $row); $row++; }
            foreach (range('A', chr(64 + count($headers))) as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
            $mallForName = $request->filled('mall_id') ? \App\Models\Mall::find($request->mall_id) : null;
            $safeName = $mallForName ? preg_replace('/[\/\\\\:*?"<>|]/u', '', str_replace(' ', '_', trim($mallForName->name_ar))) : 'all-malls';
            $safeName = preg_replace('/[^\p{L}\p{N}_\-]/u', '', $safeName);
            if (empty($safeName)) $safeName = 'mall-' . ($mallForName->id ?? 'all');
            $filename = $safeName . '_' . now()->format('Y-m-d_H-i-s') . '_template.xlsx';
            $tempPath = sys_get_temp_dir() . '/' . $filename;
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tempPath);
            return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
        }
        if ($request->filled('mall_id')) {
            $mallForExport = \App\Models\Mall::find($request->mall_id);
            if ($mallForExport) {
                $exportService = app(ProductExportService::class);
                $spreadsheet = $exportService->buildSpreadsheetForMall($mallForExport);
                $filename = $exportService->buildFilename($mallForExport);
                $tempPath = sys_get_temp_dir() . '/' . $filename;
                (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tempPath);
                $spreadsheet->disconnectWorksheets();
                return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
            }
        }
        $headers = ['ID','الاسم (عربي)','الاسم (إنجليزي)','الباركود','SKU','السعر','سعر الخصم','الكمية','القسم (قديم)','القسم الرئيسي (محدث)','القسم الفرعي','تاريخ تحديث القسم','التصنيف','المنشأة','الوحدة','العلامة التجارية','رابط الصورة','فعال','تاريخ الإضافة'];
        $sheet->setRightToLeft(true);
        $sheet->fromArray([$headers], null, 'A1');
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true);
        $sheet->getStyle('A1:' . $lastCol . '1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EEF6');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:' . $lastCol . '1');
        $row = 2;
        foreach ($products as $p) {
            $mainSection = $subSection = $sectionUpdatedAt = null;
            if ($p->mallSection) {
                if ($p->mallSection->parent) { $mainSection = $p->mallSection->parent->name_ar; $subSection = $p->mallSection->name_ar; $sectionUpdatedAt = $p->mallSection->updated_at ?: $p->mallSection->parent->updated_at; }
                else { $mainSection = $p->mallSection->name_ar; $sectionUpdatedAt = $p->mallSection->updated_at; }
            } elseif ($p->section) { $mainSection = $p->section->name_ar; }
            elseif (isset($bulkSectionMap[$p->barcode])) $mainSection = $bulkSectionMap[$p->barcode];
            $sheet->fromArray([$p->id,$p->name_ar,$p->name_en,$p->barcode,$p->sku,$p->price,$p->discount_price,$p->stock_quantity,$p->section?->name_ar,$mainSection,$subSection,$sectionUpdatedAt?->format('Y-m-d H:i'),$p->category?->name_ar,$p->mall?->name_ar,$p->unit,$p->brand,$p->link_photo,$p->is_active?'نعم':'لا',$p->created_at?->format('Y-m-d H:i')], null, 'A'.$row);
            if ($subSection) $sheet->getStyle('K'.$row)->getFont()->setBold(true)->getColor()->setARGB('FF2563EB');
            $row++;
        }
        foreach (range(1,count($headers)) as $idx) $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($idx))->setAutoSize(true);
        $mallForName = $request->filled('mall_id') ? \App\Models\Mall::find($request->mall_id) : null;
        $safeName = $mallForName ? preg_replace('/[\/\\\\:*?"<>|]/u', '', str_replace(' ', '_', trim($mallForName->name_ar))) : 'all-malls';
        $safeName = preg_replace('/[^\p{L}\p{N}_\-]/u', '', $safeName);
        if (empty($safeName)) $safeName = 'mall-' . ($mallForName->id ?? 'all');
        $filename = $safeName . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        $tempPath = sys_get_temp_dir() . '/' . $filename;
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tempPath);
        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }

    public function adminDestroy($id) { $p = Product::findOrFail($id); \App\Models\OrderItem::where('product_id',$p->id)->delete(); $p->delete(); return response()->json(['message'=>'تم حذف المنتج']); }
    public function adminStore(Request $request) {
        $request->validate(['mall_id'=>'required|exists:malls,id','name_ar'=>'required|string','price'=>'required|numeric|min:0']);
        $barcode = $request->filled('barcode') ? $request->barcode : 'IMP-'.Str::random(12);
        $data = $request->only(['mall_id','category_id','section_id','mall_section_id','name_ar','name_en','price','link_photo','shelf_location']);
        $data['name_en'] = $data['name_en'] ?? $data['name_ar'];
        $product = Product::create(array_merge($data, ['barcode'=>$barcode,'is_active'=>true]));
        return response()->json($product,201);
    }
    public function adminUpdate(Request $request,$id){ $p=Product::findOrFail($id); $p->update($request->only(['name_ar','name_en','price','barcode','link_photo','stock_quantity','shelf_location'])); return response()->json($p); }
    public function destroyAllByMall($mallId){ $mall=\App\Models\Mall::findOrFail($mallId); $c=Product::where('mall_id',$mall->id)->delete(); return response()->json(['message'=>"Deleted {$c}"]); }

    public function getMallProducts(Request $request, $id)
    {
        $query = Product::with(['category:id,name_ar,name_en', 'shelves:id', 'section:id,name_ar,name_en,icon', 'mallSection:id,name_ar,name_en', 'barcodeOverride'])
            ->where('mall_id', $id)
            ->where('is_active', true);
        if ($request->has('category_id')) $query->where('category_id', $request->category_id);
        if ($request->has('search')) {
            $s = trim($request->search);
            $query->where(fn($q) => $q->where('name_ar','like',"%{$s}%")->orWhere('name_en','like',"%{$s}%")->orWhere('barcode','like',"%{$s}%"));
        }
        if ($request->boolean('group_by_section')) {
            $mall = \App\Models\Mall::find($id);
            $hideStock = !$mall || !$mall->enable_quantity_system;
            if ($request->has('mall_section_id')) {
                $mallSectionId = $request->input('mall_section_id');
                if ((int)$mallSectionId === -1) {
                    $query->whereNull('mall_section_id')->whereNull('section_id');
                } else {
                    $mallSection = \App\Models\MallSection::find($mallSectionId);
                    $query->where(function($q) use ($mallSectionId, $mallSection) {
                        $q->where('mall_section_id', $mallSectionId);
                        if ($mallSection && $mallSection->parent_id === null) {
                            $childIds = \App\Models\MallSection::where('parent_id', $mallSection->id)->where('is_active', true)->pluck('id');
                            if ($childIds->isNotEmpty()) $q->orWhereIn('mall_section_id', $childIds);
                            if ($mallSection->section_id) $q->orWhere(fn($qq) => $qq->whereNull('mall_section_id')->where('section_id', $mallSection->section_id));
                        }
                    });
                }
                $products = $query->with(['category:id,name_ar,name_en', 'shelves:id', 'section:id,name_ar,name_en,icon', 'mallSection:id,name_ar,name_en', 'barcodeOverride'])->orderBy('name_ar')->paginate(24);
                if ($products instanceof \Illuminate\Support\Collection) $products->each(fn($p) => $hideStock || $p->hide_stock_from_customer ? : null);
                return response()->json($products);
            }
            if ($request->has('section_id')) {
                $sectionId = $request->input('section_id');
                if ((int)$sectionId === -1) $query->whereNull('section_id');
                else $query->where('section_id', $sectionId);
                $products = $query->with(['category:id,name_ar,name_en', 'shelves:id', 'section:id,name_ar,name_en,icon', 'mallSection:id,name_ar,name_en', 'barcodeOverride'])->orderBy('name_ar')->paginate(24);
                return response()->json($products);
            }
            return (new \App\Http\Controllers\API\v1\SectionController())->mallSections($request, $id);
        }
        if ($request->has('random')) {
            $limit = min((int)$request->get('random', 5), 50);
            $products = $query->inRandomOrder()->limit($limit)->get();
        } elseif ($request->has('all') && $request->boolean('all')) {
            $products = $query->latest()->get();
        } else {
            $products = $query->latest()->paginate(24);
        }
        $mall = \App\Models\Mall::find($id);
        $hideStock = !$mall || !$mall->enable_quantity_system;
        if ($products instanceof \Illuminate\Support\Collection) $products->each(function($p) use ($hideStock) { if ($hideStock || $p->hide_stock_from_customer) unset($p->stock_quantity); });
        return response()->json($products);
    }

    public function scanner(Request $request)
    {
        $request->validate(['code'=>'required|string','mall_id'=>'nullable|exists:malls,id']);
        $code = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}]/u', '', trim($request->code));
        $codeNorm = preg_replace('/[^0-9]/', '', $code);
        $mallId = $request->mall_id;
        $query = Product::with('shelves','category')->where(fn($q) => $q->where('barcode',$code)->orWhere('qr_code',$code)->orWhere('sku',$code)->when($codeNorm !== $code, fn($qq) => $qq->orWhere('barcode',$codeNorm)));
        if ($mallId) $query->where('mall_id', $mallId);
        $product = $query->first();
        if (!$product) {
            $sub = \App\Models\SubBarcode::where(fn($q) => $q->where('sub_barcode',$code)->when($codeNorm !== $code, fn($qq) => $qq->orWhere('sub_barcode',$codeNorm)))->when($mallId, fn($q) => $q->where('mall_id',$mallId))->first();
            if ($sub && $sub->product_id) $product = Product::with('shelves','category')->where('id',$sub->product_id)->when($mallId, fn($q) => $q->where('mall_id',$mallId))->first();
        }
        if (!$product) return response()->json(['message'=>'المنتج غير موجود'],404);
        if ($product->hide_stock_from_customer || ($product->mall && !$product->mall->enable_quantity_system)) unset($product->stock_quantity);
        return response()->json($product);
    }

    public function lookupByBarcode(Request $request,$barcode){ $mallIds=$request->user()->malls()->pluck('id'); $p=Product::whereIn('mall_id',$mallIds)->where('barcode',$barcode)->first(); if(!$p) return response()->json(['message'=>'المنتج غير موجود'],404); return response()->json($p); }
}
