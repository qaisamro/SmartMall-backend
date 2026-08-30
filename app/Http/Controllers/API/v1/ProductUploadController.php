<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Imports\ProductsImport;
use App\Imports\SubBarcodesImport;
use App\Models\ProductImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProductUploadController extends Controller
{
    public function uploadMainExcel(Request $request)
    {
        $user = Auth::user();

        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            $request->validate([
                'mall_id' => 'required|exists:malls,id',
                'file' => 'required|file|mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,application/vnd.oasis.opendocument.spreadsheet,text/csv,text/plain,text/comma-separated-values,application/octet-stream,application/xml,text/xml|max:30720',
            ]);
            $mallId = (int) $request->input('mall_id');
        } else {
            $request->validate([
                'file' => 'required|file|mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,application/vnd.oasis.opendocument.spreadsheet,text/csv,text/plain,text/comma-separated-values,application/octet-stream,application/xml,text/xml|max:30720',
            ]);
            $mallId = (int) $user->mall_id;
            if (!$mallId) {
                return response()->json(['status' => false, 'message' => 'الحساب غير مرتبط بمول.'], 403);
            }
        }

        $filePath = $request->file('file')->store('imports', 'local');

        $import = ProductImport::create([
            'user_id' => $user->id,
            'mall_id' => $mallId,
            'import_type' => 'main',
            'file_name' => $request->file('file')->getClientOriginalName(),
            'file_path' => $filePath,
            'status' => 'queued',
            'total_rows' => 0,
            'imported_rows' => 0,
            'inserted_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'failed_rows' => 0,
            'errors' => [],
        ]);

        // تشغيل مباشر لتجنب بقاء الحالة قيد الانتظار في بيئة لا يعمل فيها الـ queue worker
        try {
            ProductsImport::dispatchSync($import->id, $mallId, Storage::disk('local')->path($filePath), $user->id);
        } catch (\Throwable $e) {
            // fallback للـ queue العادي
            ProductsImport::dispatch($import->id, $mallId, Storage::disk('local')->path($filePath), $user->id);
        }

        return response()->json([
            'status' => true,
            'message' => 'بدأت معالجة المنتجات الأساسية في الخلفية بنجاح.',
            'import' => $import,
        ]);
    }

    public function uploadSubExcel(Request $request)
    {
        $user = Auth::user();

        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            $request->validate([
                'mall_id' => 'required|exists:malls,id',
                'file' => 'required|file|mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,application/vnd.oasis.opendocument.spreadsheet,text/csv,text/plain,text/comma-separated-values,application/octet-stream,application/xml,text/xml|max:30720',
            ]);
            $mallId = (int) $request->input('mall_id');
        } else {
            $request->validate([
                'file' => 'required|file|mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,application/vnd.oasis.opendocument.spreadsheet,text/csv,text/plain,text/comma-separated-values,application/octet-stream,application/xml,text/xml|max:30720',
            ]);
            $mallId = (int) $user->mall_id;
            if (!$mallId) {
                return response()->json(['status' => false, 'message' => 'غير مصرح لك أو الحساب غير مرتبط بمول.'], 403);
            }
        }

        $filePath = $request->file('file')->store('imports', 'local');

        $import = ProductImport::create([
            'user_id' => $user->id,
            'mall_id' => $mallId,
            'import_type' => 'sub',
            'file_name' => $request->file('file')->getClientOriginalName(),
            'file_path' => $filePath,
            'status' => 'queued',
            'total_rows' => 0,
            'imported_rows' => 0,
            'inserted_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'failed_rows' => 0,
            'errors' => [],
        ]);

        try {
            SubBarcodesImport::dispatchSync($import->id, $mallId, Storage::disk('local')->path($filePath), $user->id);
        } catch (\Throwable $e) {
            SubBarcodesImport::dispatch($import->id, $mallId, Storage::disk('local')->path($filePath), $user->id);
        }

        return response()->json([
            'status' => true,
            'message' => 'بدأت معالجة الأكواد الفرعية وربطها بالمنتجات الأساسية في الخلفية.',
            'import' => $import,
        ]);
    }

    public function downloadTemplate(Request $request)
    {
        $user = Auth::user();
        $type = $request->query('type', 'main');

        $headers = $type === 'sub'
            ? ['sub_barcode', 'main_barcode']
            : ['barcode', 'name', 'selling_price', 'unit'];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$headers], null, 'A1');
        $sheet->getStyle('A1:' . chr(64 + count($headers)) . '1')->getFont()->setBold(true);

        $filename = $type === 'sub'
            ? 'smartmall-sub-barcodes-template.xlsx'
            : 'smartmall-products-template.xlsx';

        $tempPath = sys_get_temp_dir() . '/' . $filename;
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }
}
