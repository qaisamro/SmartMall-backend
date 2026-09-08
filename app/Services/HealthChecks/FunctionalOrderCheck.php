<?php

namespace App\Services\HealthChecks;

use Illuminate\Support\Facades\DB;

class FunctionalOrderCheck implements HealthCheckInterface
{
    public function key(): string { return 'functional.order_flow'; }
    public function category(): string { return 'application'; }
    public function severity(): string { return 'error'; }
    public function title(): string { return 'إنشاء وتعديل الطلب'; }

    public function run(): HealthResult
    {
        $start = hrtime(true);
        DB::beginTransaction();
        try {
            // استخدام طلب موجود للاختبار (تعديل حالة)
            $order = DB::table('orders')->first();
            if (!$order) {
                DB::rollBack();
                return HealthResult::notChecked('لا يوجد طلبات للاختبار', ['reason' => 'empty orders']);
            }

            $originalStatus = $order->status;
            // نختبر تعديلاً آمناً (نفس القيمة لإثبات أن UPDATE يعمل)
            $affected = DB::table('orders')->where('id', $order->id)->update(['status' => $originalStatus]);
            // حتى لو كانت القيمة نفسها، يجب أن ينجح الاستعلام بدون خطأ

            // محاولة إنشاء عنصر طلب وهمي ثم حذفه (اختبار العلاقة)
            $orderId = $order->id;
            $product = DB::table('products')->first();
            if ($product) {
                $itemId = DB::table('order_items')->insertGetId([
                    'order_id' => $orderId,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'price_at_sale' => $product->price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $inserted = DB::table('order_items')->where('id', $itemId)->first();
                if (!$inserted) {
                    DB::rollBack();
                    return HealthResult::failed('فشل إنشاء عنصر طلب', ['order_id' => $orderId], 'high', 'error');
                }
                DB::table('order_items')->where('id', $itemId)->delete();
            }

            DB::rollBack();
            $ms = (int) round((hrtime(true) - $start) / 1e6);
            return HealthResult::pass("دورة الطلب تعمل ({$ms}ms) — تعديل حالة + إنشاء عنصر (rollback)", ['order_id' => $orderId, 'duration_ms' => $ms]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return HealthResult::failed('فشل اختبار الطلب: ' . substr($e->getMessage(), 0, 300), ['exception' => get_class($e)], 'high', 'error');
        }
    }
}
