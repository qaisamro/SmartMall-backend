<?php

namespace App\Services\HealthChecks;

use Illuminate\Support\Facades\DB;

class FunctionalProductCheck implements HealthCheckInterface
{
    public function key(): string { return 'functional.product_save'; }
    public function category(): string { return 'application'; }
    public function severity(): string { return 'critical'; }
    public function title(): string { return 'حفظ وتعديل المنتج'; }

    public function run(): HealthResult
    {
        $start = hrtime(true);
        DB::beginTransaction();
        try {
            // استخدام منتج موجود للاختبار بدون إنشاء جديد
            $product = DB::table('products')->first();
            if (!$product) {
                DB::rollBack();
                return HealthResult::notChecked('لا يوجد منتجات للاختبار', ['reason' => 'empty products table']);
            }

            $originalName = $product->name_ar;
            $testName = $originalName . ' [health-check]';

            // محاولة تعديل
            $affected = DB::table('products')->where('id', $product->id)->update(['name_ar' => $testName]);
            if ($affected !== 1) {
                DB::rollBack();
                return HealthResult::failed('فشل تعديل المنتج: لم يتم تحديث أي صف', ['product_id' => $product->id], 'high', 'error');
            }

            // تحقق من الحفظ
            $reloaded = DB::table('products')->where('id', $product->id)->first();
            if ($reloaded->name_ar !== $testName) {
                DB::rollBack();
                return HealthResult::failed('التعديل لم يُحفظ: القيمة المرجعة لا تطابق', ['expected' => $testName, 'got' => $reloaded->name_ar], 'critical', 'critical');
            }

            // إرجاع القيمة الأصلية
            DB::table('products')->where('id', $product->id)->update(['name_ar' => $originalName]);
            $verified = DB::table('products')->where('id', $product->id)->first();
            if ($verified->name_ar !== $originalName) {
                DB::rollBack();
                return HealthResult::failed('فشل إرجاع القيمة الأصلية', null, 'high', 'error');
            }

            DB::rollBack(); // تأكيد عدم حفظ أي تغيير
            $ms = (int) round((hrtime(true) - $start) / 1e6);
            return HealthResult::pass("حفظ وتعديل المنتج يعمل ({$ms}ms) — تم تعديل وحفظ وإرجاع بنجاح", ['product_id' => $product->id, 'duration_ms' => $ms]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return HealthResult::failed('استثناء في حفظ المنتج: ' . substr($e->getMessage(), 0, 300), ['exception' => get_class($e), 'file' => $e->getFile() . ':' . $e->getLine()], 'critical', 'critical');
        }
    }
}
