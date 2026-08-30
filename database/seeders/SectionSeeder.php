<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;

class SectionSeeder extends Seeder
{
    public function run()
    {
        $sections = [
            ['name_ar' => 'ألبان وأجبان', 'name_en' => 'Dairy & Cheese', 'sort_order' => 1, 'bg_image' => 'https://images.unsplash.com/photo-1624806992066-5ffcf7ca7b29?w=400&h=300&fit=crop'],
            ['name_ar' => 'خضار وفواكه', 'name_en' => 'Vegetables & Fruits', 'sort_order' => 2, 'bg_image' => 'https://images.unsplash.com/photo-1518843875459-f738682238a6?w=400&h=300&fit=crop'],
            ['name_ar' => 'لحوم ودواجن', 'name_en' => 'Meat & Poultry', 'sort_order' => 3, 'bg_image' => 'https://images.unsplash.com/photo-1607623814075-e51df1bdc82f?w=400&h=300&fit=crop'],
            ['name_ar' => 'مشروبات', 'name_en' => 'Beverages', 'sort_order' => 4, 'bg_image' => 'https://images.unsplash.com/photo-1527668752968-14dc70a27c95?w=400&h=300&fit=crop'],
            ['name_ar' => 'معلبات', 'name_en' => 'Canned Food', 'sort_order' => 5, 'bg_image' => 'https://images.unsplash.com/photo-1580910051074-8eb0f8b14a8a?w=400&h=300&fit=crop'],
            ['name_ar' => 'حلويات ووجبات خفيفة', 'name_en' => 'Sweets & Snacks', 'sort_order' => 6, 'bg_image' => 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=400&h=300&fit=crop'],
            ['name_ar' => 'مجمدات', 'name_en' => 'Frozen Food', 'sort_order' => 7, 'bg_image' => 'https://images.unsplash.com/photo-1582561424557-058531705a52?w=400&h=300&fit=crop'],
            ['name_ar' => 'مخبوزات', 'name_en' => 'Bakery', 'sort_order' => 8, 'bg_image' => 'https://images.unsplash.com/photo-1509365465985-25d11c17e812?w=400&h=300&fit=crop'],
            ['name_ar' => 'أرز ومعكرونة', 'name_en' => 'Rice & Pasta', 'sort_order' => 9, 'bg_image' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=400&h=300&fit=crop'],
            ['name_ar' => 'زيوت وبهارات', 'name_en' => 'Oils & Spices', 'sort_order' => 10, 'bg_image' => 'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?w=400&h=300&fit=crop'],
            ['name_ar' => 'منظفات', 'name_en' => 'Cleaning', 'sort_order' => 11, 'bg_image' => 'https://images.unsplash.com/photo-1585421514284-efb74c2b69ba?w=400&h=300&fit=crop'],
            ['name_ar' => 'عناية شخصية', 'name_en' => 'Personal Care', 'sort_order' => 12, 'bg_image' => 'https://images.unsplash.com/photo-1556228578-0d85b1a4d571?w=400&h=300&fit=crop'],
            ['name_ar' => 'مستلزمات منزلية', 'name_en' => 'Household', 'sort_order' => 13, 'bg_image' => 'https://images.unsplash.com/photo-1556020685-ae41abfc9365?w=400&h=300&fit=crop'],
            ['name_ar' => 'أطفال', 'name_en' => 'Baby', 'sort_order' => 14, 'bg_image' => 'https://images.unsplash.com/photo-1519689680058-324335c77eba?w=400&h=300&fit=crop'],
            ['name_ar' => 'مشروبات ساخنة', 'name_en' => 'Hot Drinks', 'sort_order' => 15, 'bg_image' => 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?w=400&h=300&fit=crop'],
            ['name_ar' => 'أخرى', 'name_en' => 'Other', 'sort_order' => 99],
        ];

        foreach ($sections as $section) {
            Section::create($section);
        }
    }
}
