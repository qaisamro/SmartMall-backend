<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\SubscriptionPlan::updateOrCreate(
            ['slug' => 'premium-mall'],
            [
                'name_ar' => 'اشتراك المول المميز',
                'name_en' => 'Premium Mall Subscription',
                'price_monthly' => 180.00,
                'price_quarterly' => 500.00,
                'price_yearly' => 1900.00,
                'features' => [
                    'نظام كاشير متقدم',
                    'استيراد منتجات غير محدود',
                    'إحصائيات متقدمة',
                    'دعم فني متميز'
                ],
                'is_active' => true,
            ]
        );
    }
}
