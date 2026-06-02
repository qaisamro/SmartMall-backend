<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\ProductImport;
use App\Services\ProductImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class ProductImportController extends Controller
{
    public function __construct(private ProductImportService $service)
    {
    }

    private function ownedMallIds(Request $request)
    {
        return $request->user()->malls()->pluck('id');
    }

    private function ownerCanAccessMall(Request $request, int $mallId): bool
    {
        if (! $request->user()->hasRole('mall-owner')) {
            return true;
        }

        return $this->ownedMallIds($request)->contains($mallId);
    }

    private function ownerCanAccessImport(Request $request, ProductImport $import): bool
    {
        return $this->ownerCanAccessMall($request, (int) $import->mall_id);
    }

    public function template(Request $request)
    {
        $templatePath = $this->service->generateTemplate();
        return Response::download($templatePath, 'smartmall-product-import-template.xlsx')->deleteFileAfterSend(true);
    }

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'mall_id' => ['required', 'exists:malls,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'auto_create_categories' => ['sometimes', 'boolean'],
            'duplicate_strategy' => ['required', 'in:skip,update,create'],
            'duplicate_by' => ['required', 'in:sku,barcode,name'],
        ]);

        if (! $this->ownerCanAccessMall($request, (int) $validated['mall_id'])) {
            return response()->json(['message' => 'You do not own the selected mall.'], 403);
        }

        $filePath = $this->service->storeUploadedFile($request->file('file'));
        $import = ProductImport::create([
            'user_id' => $request->user()->id,
            'mall_id' => $validated['mall_id'],
            'file_name' => $request->file('file')->getClientOriginalName(),
            'file_path' => $filePath,
            'status' => 'pending',
            'options' => [
                'auto_create_categories' => filter_var($validated['auto_create_categories'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'duplicate_strategy' => $validated['duplicate_strategy'],
                'duplicate_by' => $validated['duplicate_by'],
            ],
        ]);

        try {
            $fullPath = Storage::disk('local')->path($filePath);
            $preview = $this->service->preview($fullPath, $validated['mall_id'], $import->options);
        } catch (\Exception $e) {
            // Fallback: try using the storage path directly
            $fullPath = storage_path('app/' . $filePath);
            $preview = $this->service->preview($fullPath, $validated['mall_id'], $import->options);
        }
        $import->update(['total_rows' => $preview['summary']['total_rows'], 'summary' => $preview['summary']]);

        return response()->json(['import' => $import, 'preview' => $preview]);
    }

    public function start(Request $request, $id)
    {
        $import = ProductImport::findOrFail($id);

        if (! $this->ownerCanAccessImport($request, $import)) {
            return response()->json(['message' => 'You do not own this import.'], 403);
        }

        if (! in_array($import->status, ['pending', 'failed', 'completed', 'queued'], true)) {
            // Return current status to help clients diagnose why start failed (useful during debugging)
            return response()->json([
                'message' => 'Import cannot be started in its current state.',
                'current_status' => $import->status,
            ], 422);
        }

        // Reset counters when re-starting a previously completed/failed import
        $import->update([
            'status' => 'processing',
            'imported_rows' => 0,
            'failed_rows' => 0,
            'errors' => [],
            'completed_at' => null,
            'started_at' => now(),
        ]);

        // Process import synchronously (immediately) instead of queuing
        try {
            $this->service->processImport($import);
        } catch (\Exception $e) {
            $import->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);
            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 500);
        }

        // Reload to get the latest status after processing
        $import->refresh();

        return response()->json(['import' => $import]);
    }

    public function index(Request $request)
    {
        $query = ProductImport::with(['mall', 'user'])->orderByDesc('created_at');

        if ($request->user()->hasRole('mall-owner')) {
            $mallIds = $this->ownedMallIds($request);

            if ($request->filled('mall_id') && ! $mallIds->contains((int) $request->mall_id)) {
                return response()->json(['message' => 'You do not own the selected mall.'], 403);
            }

            $query->whereIn('mall_id', $mallIds);
        }

        if ($request->filled('mall_id')) {
            $query->where('mall_id', $request->mall_id);
        }

        return response()->json($query->paginate(20));
    }

    public function show(Request $request, $id)
    {
        $import = ProductImport::findOrFail($id);

        if (! $this->ownerCanAccessImport($request, $import)) {
            return response()->json(['message' => 'You do not own this import.'], 403);
        }

        return response()->json($import->load(['mall', 'user']));
    }

    public function downloadReport(Request $request, $id)
    {
        $import = ProductImport::findOrFail($id);

        if (! $this->ownerCanAccessImport($request, $import)) {
            return response()->json(['message' => 'You do not own this import.'], 403);
        }

        $errors = $import->errors ?? [];
        $filename = 'product-import-report-' . $import->id . '.csv';

        $callback = function () use ($errors) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Row', 'Message', 'Product Name', 'SKU', 'Category', 'Price', 'Quantity', 'Brand', 'Image URL', 'Status']);
            foreach ($errors as $error) {
                $preview = $error['data']['preview'] ?? [];
                fputcsv($handle, [
                    $error['row'],
                    $error['message'],
                    $preview['Product Name'] ?? '',
                    $preview['SKU'] ?? '',
                    $preview['Category'] ?? '',
                    $preview['Price'] ?? '',
                    $preview['Quantity'] ?? '',
                    $preview['Brand'] ?? '',
                    $preview['Image URL'] ?? '',
                    $preview['Status'] ?? '',
                ]);
            }
            fclose($handle);
        };

        return Response::streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $import = ProductImport::findOrFail($id);

        if (! $this->ownerCanAccessImport($request, $import)) {
            return response()->json(['message' => 'You do not own this import.'], 403);
        }

        // Delete stored file if present
        try {
            if ($import->file_path && Storage::disk('local')->exists($import->file_path)) {
                Storage::disk('local')->delete($import->file_path);
            }
        } catch (\Exception $e) {
            // Log and proceed with deletion
        }

        $import->delete();

        return response()->json(['message' => 'Import deleted']);
    }
}
