<?php

namespace Database\Seeders;

use App\Models\Mall;
use App\Models\MallBranch;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class MallSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::whereHas('roles', fn($q) => $q->where('name', 'mall-owner'))->first();

        $malls = [
            [
                'name_ar' => 'رافال زون',
                'name_en' => 'Rafal Zone',
                'description_ar' => 'أكبر مركز تسوق في المنطقة، يضم ماركات عالمية ومحلية.',
                'description_en' => 'Largest shopping center in the region, featuring global and local brands.',
                'owner_id' => $owner->id,
                'status' => 'approved',
            ],
            [
                'name_ar' => 'بلازا سنتر',
                'name_en' => 'Plaza Center',
                'description_ar' => 'وجهتك المثالية للتسوق العائلي والترفيه.',
                'description_en' => 'Your perfect destination for family shopping and entertainment.',
                'owner_id' => $owner->id,
                'status' => 'approved',
            ],
            [
                'name_ar' => 'سيتي مول رام الله',
                'name_en' => 'City Mall Ramallah',
                'description_ar' => 'قلب التسوق في مدينة رام الله، أحدث الماركات وأرقى الأذواق.',
                'description_en' => 'The heart of shopping in Ramallah, latest brands and finest tastes.',
                'owner_id' => $owner->id,
                'status' => 'approved',
            ]
        ];

        foreach ($malls as $mallData) {
            $mall = Mall::create($mallData);

            // Create Branches
            MallBranch::create([
                'mall_id' => $mall->id,
                'name_ar' => 'الفرع الرئيسي',
                'name_en' => 'Main Branch',
                'address_ar' => 'وسط المدينة، الطابق الثاني',
                'address_en' => 'City Center, 2nd Floor',
                'latitude' => 31.9,
                'longitude' => 35.2,
                'is_main' => true,
            ]);

            // Create Categories
            $categories = ['الملابس', 'الإلكترونيات', 'المواد الغذائية', 'العطور'];
            foreach ($categories as $cat) {
                Category::create([
                    'mall_id' => $mall->id,
                    'name_ar' => $cat,
                    'name_en' => $cat . ' EN',
                ]);
            }
        }
    }
}
