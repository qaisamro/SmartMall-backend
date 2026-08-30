<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mall_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mall_id')->constrained('malls')->onDelete('cascade');
            $table->foreignId('section_id')->nullable()->constrained('sections')->onDelete('set null');
            $table->foreignId('parent_id')->nullable()->constrained('mall_sections')->onDelete('cascade');
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('icon')->nullable();
            $table->text('bg_image')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['mall_id', 'section_id']);
            $table->index(['mall_id', 'parent_id', 'is_active']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('mall_section_id')->nullable()->after('section_id')->constrained('mall_sections')->onDelete('set null');
        });

        // Seed default per-mall sections from global sections
        $malls = DB::table('malls')->pluck('id');
        $sections = DB::table('sections')->where('is_active', true)->get(['id', 'name_ar', 'name_en', 'icon', 'bg_image']);

        foreach ($malls as $mallId) {
            foreach ($sections as $sec) {
                DB::table('mall_sections')->insert([
                    'mall_id'    => $mallId,
                    'section_id' => $sec->id,
                    'name_ar'    => $sec->name_ar,
                    'name_en'    => $sec->name_en,
                    'icon'       => $sec->icon,
                    'bg_image'   => $sec->bg_image,
                    'sort_order' => 0,
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Link existing products to their mall section via global section
        DB::statement('
            UPDATE products p
            JOIN mall_sections ms ON ms.mall_id = p.mall_id AND ms.section_id = p.section_id
            SET p.mall_section_id = ms.id
            WHERE p.section_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['mall_section_id']);
            $table->dropColumn('mall_section_id');
        });

        Schema::dropIfExists('mall_sections');
    }
};