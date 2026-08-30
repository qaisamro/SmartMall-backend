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
        $query = Product::with(['category:id,name_ar,name_en', 'shelves:id', 'mall:id,name_ar', 'section:id,name_ar,name_en', 'barcodeOverride'])
            ->where('is_active', true);
        if ($request->has('mall_id')) {
            $query->where('mall_id', $request->mall_id);
        }
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->has('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name_ar', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
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
        $query = Product::with(['category:id,name_ar,name_en', 'section:id,name_ar,name_en', 'mallSection:id,name_ar,name_en'])
            ->whereIn('mall_id', $mallIds);
        if ($request->filled('mall_id')) {
            $query->where('mall_id', $request->input('mall_id'));
        }
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function($q) use ($search) {
                $q->where('name_ar', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }
        return response()->json($query->orderByRaw("COALESCE(NULLIF(name_ar, ''), name_en) ASC")->paginate((int) $request->input('per_page', 20)));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'mall_id' => 'required|exists:malls,id',
            'category_id' => 'nullable|exists:categories,id',
            'name_ar' => 'required|string',
            'name_en' => 'required|string',
            'price' => 'required|numeric|min:0',
            'barcode' => 'nullable|string|max:255',
        ]);
        $product = Product::create($request->only(['mall_id','category_id','name_ar','name_en','price','barcode','sku','brand','link_photo','shelf_location','stock_quantity']));
        return response()->json($product, 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $product->update($request->only(['name_ar','name_en','price','barcode','link_photo','stock_quantity','shelf_location']));
        return response()->json($product);
    }

    public function destroy($id)
    {
        Product::findOrFail($id)->delete();
        return response()->json(['message' => 'Product deleted']);
    }

    public function destroyAll(Request $request)
    {
        $mallIds = $request->user()->malls()->pluck('id');
        $deleted = Product::whereIn('mall_id', $mallIds)->delete();
        return response()->json(['message' => "Deleted {$deleted} products"]);
    }

    public function adminIndex(Request $request)
    {
        $query = Product::with(['category:id,name_ar,name_en', 'mall:id,name_ar', 'section:id,name_ar,name_en'])->orderByDesc('created_at');
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
        $query = Product::with([
            'category:id,name_ar,name_en',
            'mall:id,name_ar',
            'section:id,name_ar,name_en',
            'mallSection:id,mall_id,section_id,parent_id,name_ar,name_en,updated_at',
            'mallSection.parent:id,name_ar,name_en,updated_at'
        ]);
        if ($request->filled('mall_id')) $query->where('mall_id', $request->mall_id);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name_ar','like',"%{$s}%")->orWhere('barcode','like',"%{$s}%"));
        }
        $products = $query->orderByDesc('created_at')->get();
        $bulkSectionMap = [];
        try {
            $rows = \App\Models\BulkPhotoImportRow::whereNotNull('section_id')->latest()->limit(5000)->get(['barcode','section_id']);
            foreach ($rows as $r) {
                if (!isset($bulkSectionMap[$r->barcode]) && $r->section_id) {
                    $bulkSectionMap[$r->barcode] = \App\Models\Section::find($r->section_id)?->name_ar;
                }
            }
        } catch (\Throwable $e) {}

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if ($request->input('format') === 'template') {
            $headers = ['barcode', 'name', 'selling_price', 'unit'];
            $sheet->fromArray([$headers], null, 'A1');
            $sheet->getStyle('A1:' . chr(64 + count($headers)) . '1')->getFont()->setBold(true);
            $row = 2;
            foreach ($products as $p) {
                $sheet->fromArray([$p->barcode, $p->name_ar ?: $p->name_en, $p->price, $p->unit], null, 'A' . $row);
                $row++;
            }
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

        // استخدام Service لإعادة الاستخدام مع Backup (نفس 19 عمود)
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
    public function getMallProducts(Request $request,$id){
        $q=Product::with(['category:id,name_ar,name_en','section:id,name_ar,name_en'])->where('mall_id',$id)->where('is_active',true);
        if($request->has('category_id')) $q->where('category_id',$request->category_id);
        if($request->has('search')){ $s=trim($request->search); $q->where(fn($qq)=>$qq->where('name_ar','like',"%{$s}%")->orWhere('barcode','like',"%{$s}%")); }
        return response()->json($q->latest()->paginate(24));
    }
    public function scanner(Request $request){
        $request->validate(['code'=>'required|string']);
        $code=trim($request->code);
        $product=Product::where('barcode',$code)->orWhere('sku',$code)->first();
        if(!$product) return response()->json(['message'=>'المنتج غير موجود'],404);
        return response()->json($product);
    }
    public function lookupByBarcode(Request $request,$barcode){ $mallIds=$request->user()->malls()->pluck('id'); $p=Product::whereIn('mall_id',$mallIds)->where('barcode',$barcode)->first(); if(!$p) return response()->json(['message'=>'المنتج غير موجود'],404); return response()->json($p); }
}
