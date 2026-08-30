<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class AdminActivityLogController extends Controller
{
    private function actionLabel($action): string
    {
        return match ($action) {
            'logged_in' => 'تسجيل دخول',
            'login_failed' => 'فشل تسجيل دخول',
            'added_to_cart' => 'إضافة إلى السلة',
            'cart_item_removed' => 'إزالة من السلة',
            'order_confirmed' => 'تأكيد طلب',
            'product_created' => 'إضافة منتج',
            'product_updated' => 'تحديث منتج',
            'product_deleted' => 'حذف منتج',
            default => $action,
        };
    }

    private function resolveModelName($modelType, $modelId)
    {
        if (!$modelType || !$modelId) return null;

        try {
            $instance = $modelType::find($modelId);
            if (!$instance) return '(محذوف)';

            return match ($modelType) {
                'App\Models\Product' => $instance->name_ar
                    ?? $instance->name_en
                    ?? 'منتج #' . $modelId,
                'App\Models\Mall' => $instance->name_ar
                    ?? $instance->name_en
                    ?? 'مول #' . $modelId,
                'App\Models\User' => $instance->name
                    ?? 'مستخدم #' . $modelId,
                'App\Models\Order' => 'طلب #' . $instance->id,
                'App\Models\PendingOrder' => 'طلب معلق #' . $instance->id,
                'App\Models\Offer' => $instance->title_ar
                    ?? $instance->title_en
                    ?? 'عرض #' . $modelId,
                'App\Models\Category' => $instance->name_ar
                    ?? $instance->name_en
                    ?? 'تصنيف #' . $modelId,
                default => class_basename($modelType) . ' #' . $modelId,
            };
        } catch (\Throwable $e) {
            return class_basename($modelType) . ' #' . $modelId;
        }
    }

    public function index(Request $request)
    {
        $query = ActivityLog::with('user', 'mall');

        // Filter by action
        if ($request->action) {
            $query->where('action', $request->action);
        }

        // Filter by user type
        if ($request->user_type) {
            $query->where('user_type', $request->user_type);
        }

        // Filter by date range
        if ($request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        // Search in description
        if ($request->search) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        // Filter by model
        if ($request->model_type) {
            $query->where('model_type', $request->model_type);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 50);

        // Resolve model names for each log
        $logs->getCollection()->transform(function ($log) {
            $log->model_name = $this->resolveModelName($log->model_type, $log->model_id);
            $log->action_label = $this->actionLabel($log->action);

            // Enrich metadata with human-readable values
            if ($log->metadata && is_array($log->metadata)) {
                $meta = $log->metadata;
                if (isset($meta['mall_id'])) {
                    $mall = \App\Models\Mall::find($meta['mall_id']);
                    $meta['المول'] = $mall ? $mall->name_ar : 'مول #' . $meta['mall_id'];
                    unset($meta['mall_id']);
                }
                if (isset($meta['category_id'])) {
                    $cat = \App\Models\Category::find($meta['category_id']);
                    $meta['التصنيف'] = $cat ? $cat->name_ar : 'تصنيف #' . $meta['category_id'];
                    unset($meta['category_id']);
                }
                if (isset($meta['product_id'])) {
                    $product = \App\Models\Product::find($meta['product_id']);
                    $meta['المنتج'] = $product ? $product->name_ar : 'منتج #' . $meta['product_id'];
                    unset($meta['product_id']);
                }
                if (isset($meta['user_id'])) {
                    $user = \App\Models\User::find($meta['user_id']);
                    $meta['المستخدم'] = $user ? $user->name : 'مستخدم #' . $meta['user_id'];
                    unset($meta['user_id']);
                }
                if (isset($meta['order_id'])) {
                    $meta['الطلب'] = 'طلب #' . $meta['order_id'];
                    unset($meta['order_id']);
                }
                $log->metadata = $meta;
            }

            return $log;
        });

        return response()->json($logs);
    }

    public function stats()
    {
        $totalToday = ActivityLog::whereDate('created_at', today())->count();
        $totalWeek = ActivityLog::where('created_at', '>=', now()->subWeek())->count();

        $topActions = ActivityLog::selectRaw('action, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $recentLogins = ActivityLog::with('user')
            ->where('action', 'logged_in')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $recentCarts = ActivityLog::with('user', 'mall')
            ->where('action', 'added_to_cart')
            ->where('created_at', '>=', now()->subHours(24))
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'total_today' => $totalToday,
            'total_week'  => $totalWeek,
            'top_actions' => $topActions,
            'recent_logins' => $recentLogins,
            'recent_carts'  => $recentCarts,
        ]);
    }

    public function destroy($id)
    {
        ActivityLog::findOrFail($id)->delete();
        return response()->json(['message' => 'تم حذف السجل']);
    }

    public function clearAll()
    {
        ActivityLog::query()->truncate();
        return response()->json(['message' => 'تم مسح جميع السجلات']);
    }
}
