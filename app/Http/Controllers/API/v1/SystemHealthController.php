<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\SystemError;
use App\Models\SystemScan;
use App\Services\ErrorMonitoringService;
use App\Services\SystemHealthService;
use Illuminate\Http\Request;

class SystemHealthController extends Controller
{
    public function overview()
    {
        return response()->json(SystemHealthService::overview());
    }

    public function scan(Request $request)
    {
        $scan = SystemHealthService::runScan($request->user()?->id, 'manual');
        return response()->json(['scan' => $scan->load('results'), 'message' => 'تم الفحص بنجاح']);
    }

    public function scans(Request $request)
    {
        $scans = SystemScan::latestFirst()->paginate($request->input('per_page', 20));
        return response()->json($scans);
    }

    public function showScan($id)
    {
        $scan = SystemScan::with('results')->findOrFail($id);
        $grouped = $scan->results->groupBy('category');
        return response()->json(['scan' => $scan, 'grouped' => $grouped]);
    }

    public function errors(Request $request)
    {
        $q = SystemError::query();
        if ($request->filled('severity')) $q->where('severity', $request->severity);
        if ($request->filled('source')) $q->where('source', $request->source);
        if ($request->filled('status')) {
            $q->where('resolved', $request->status === 'resolved');
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(function ($qq) use ($s) {
                $qq->where('message', 'like', "%$s%")->orWhere('type', 'like', "%$s%")->orWhere('fingerprint', 'like', "%$s%");
            });
        }
        if ($request->filled('date_from')) $q->whereDate('last_seen_at', '>=', $request->date_from);
        if ($request->filled('date_to')) $q->whereDate('last_seen_at', '<=', $request->date_to);
        $sort = $request->input('sort', 'last_seen_at');
        $dir = $request->input('dir', 'desc');
        if (in_array($sort, ['last_seen_at', 'occurrences', 'severity', 'created_at'])) {
            $q->orderBy($sort, $dir === 'asc' ? 'asc' : 'desc');
        }
        return response()->json($q->paginate($request->input('per_page', 20)));
    }

    public function showError($id)
    {
        $error = SystemError::with(['user', 'resolver'])->findOrFail($id);
        // Stack trace للمسؤول فقط (already gated by role middleware)
        return response()->json(['error' => $error]);
    }

    public function resolve(Request $request, $id)
    {
        $error = SystemError::findOrFail($id);
        $error->update(['resolved' => true, 'resolved_at' => now(), 'resolved_by' => $request->user()?->id]);
        return response()->json(['message' => 'تم الحل', 'error' => $error->fresh()]);
    }

    public function unresolve($id)
    {
        $error = SystemError::findOrFail($id);
        $error->update(['resolved' => false, 'resolved_at' => null, 'resolved_by' => null]);
        return response()->json(['message' => 'تم إلغاء الحل', 'error' => $error->fresh()]);
    }

    public function destroy($id)
    {
        $error = SystemError::findOrFail($id);
        if ($error->severity === 'critical' && !$error->resolved) {
            return response()->json(['message' => 'لا يمكن حذف خطأ حرج غير محلول'], 422);
        }
        $error->delete();
        return response()->json(['message' => 'تم الحذف']);
    }

    public function report(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'type' => 'nullable|string|max:60',
            'severity' => 'nullable|in:info,warning,error,critical',
            'source' => 'nullable|in:backend,frontend,database,network,queue,storage',
            'url' => 'nullable|string|max:500',
            'route' => 'nullable|string|max:255',
            'stack_trace' => 'nullable|string|max:8000',
        ]);

        $error = ErrorMonitoringService::report([
            'type' => $request->input('type', 'frontend.error'),
            'severity' => $request->input('severity', 'error'),
            'source' => $request->input('source', 'frontend'),
            'message' => $request->input('message'),
            'file' => $request->input('file'),
            'line' => $request->input('line'),
            'url' => $request->input('url') ?: $request->header('Referer'),
            'route' => $request->input('route'),
            'method' => $request->input('method', 'GET'),
            'status_code' => $request->input('status_code'),
            'request_id' => $request->header('X-Request-Id') ?: $request->input('request_id'),
            'stack_trace' => $request->input('stack_trace'),
            'user_id' => $request->user()?->id,
            'user_role' => $request->user()?->getRoleNames()->first(),
        ]);

        return response()->json(['error' => $error], 201);
    }

    public function export($scanId)
    {
        $scan = SystemScan::with('results')->findOrFail($scanId);
        return response()->json(['scan' => $scan, 'results' => $scan->results], 200, [
            'Content-Disposition' => 'attachment; filename="scan-' . $scanId . '.json"',
        ]);
    }
}
