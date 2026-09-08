<?php

namespace App\Services\HealthChecks;

use Illuminate\Support\Facades\DB;

class FunctionalMallCheck implements HealthCheckInterface
{
    public function key(): string { return 'functional.mall_save'; }
    public function category(): string { return 'application'; }
    public function severity(): string { return 'critical'; }
    public function title(): string { return 'إنشاء وتعديل المنشأة'; }

    public function run(): HealthResult
    {
        $start = hrtime(true);
        DB::beginTransaction();
        try {
            $mall = DB::table('malls')->first();
            if (!$mall) {
                DB::rollBack();
                return HealthResult::notChecked('لا يوجد منشآت للاختبار', ['reason' => 'empty malls']);
            }

            $originalName = $mall->name_ar;
            $testName = $originalName . ' [HC]';

            $affected = DB::table('malls')->where('id', $mall->id)->update(['name_ar' => $testName]);
            if ($affected !== 1) {
                DB::rollBack();
                return HealthResult::failed('فشل تعديل المنشأة', ['mall_id' => $mall->id], 'high', 'error');
            }

            $reloaded = DB::table('malls')->where('id', $mall->id)->first();
            if ($reloaded->name_ar !== $testName) {
                DB::rollBack();
                return HealthResult::failed('التعديل لم يُحفظ', ['expected' => $testName, 'got' => $reloaded->name_ar], 'critical', 'critical');
            }

            // اختبار حفظ offer_limit أيضاً (المشكلة المبلغ عنها)
            $originalLimit = $mall->offer_limit ?? 0;
            $testLimit = $originalLimit + 1;
            DB::table('malls')->where('id', $mall->id)->update(['offer_limit' => $testLimit]);
            $reloaded2 = DB::table('malls')->where('id', $mall->id)->first();
            if ((int)$reloaded2->offer_limit !== (int)$testLimit) {
                DB::rollBack();
                return HealthResult::failed('فشل حفظ offer_limit', ['expected' => $testLimit, 'got' => $reloaded2->offer_limit, 'mall_id' => $mall->id], 'critical', 'critical');
            }
            DB::table('malls')->where('id', $mall->id)->update(['offer_limit' => $originalLimit]);

            // اختبار إنشاء منشأة وهمية ثم حذفها
            $testMallId = DB::table('malls')->insertGetId([
                'owner_id' => $mall->owner_id,
                'name_ar' => 'HC Test Mall ' . uniqid(),
                'name_en' => 'HC Test ' . uniqid(),
                'slug' => 'hc-test-' . uniqid(),
                'status' => 'pending',
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created = DB::table('malls')->where('id', $testMallId)->first();
            if (!$created) {
                DB::rollBack();
                return HealthResult::failed('فشل إنشاء منشأة اختبارية', null, 'high', 'error');
            }
            DB::table('malls')->where('id', $testMallId)->delete();

            // إرجاع الأصل
            DB::table('malls')->where('id', $mall->id)->update(['name_ar' => $originalName]);

            DB::rollBack();
            $ms = (int) round((hrtime(true) - $start) / 1e6);
            return HealthResult::pass("إنشاء وتعديل المنشأة يعمل ({$ms}ms)", ['mall_id' => $mall->id, 'duration_ms' => $ms]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return HealthResult::failed('فشل اختبار المنشأة: ' . substr($e->getMessage(), 0, 300), ['exception' => get_class($e)], 'critical', 'critical');
        }
    }
}
