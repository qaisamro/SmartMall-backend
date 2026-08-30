<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\MallSection;
use App\Models\Product;
use App\Models\Section;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    /**
     * Ensure the mall has a per-mall row for every active global section.
     */
    public static function ensureDefaults($mallId): void
    {
        $globalSections = Section::where('is_active', true)->get(['id', 'name_ar', 'name_en', 'icon', 'bg_image']);
        if ($globalSections->isEmpty()) return;

        $existingIds = MallSection::where('mall_id', $mallId)
            ->whereNotNull('section_id')
            ->pluck('section_id')
            ->all();

        foreach ($globalSections as $sec) {
            if (in_array($sec->id, $existingIds, true)) continue;

            MallSection::create([
                'mall_id'    => $mallId,
                'section_id' => $sec->id,
                'name_ar'    => $sec->name_ar,
                'name_en'    => $sec->name_en,
                'icon'       => $sec->icon,
                'bg_image'   => $sec->bg_image,
                'is_active'  => true,
            ]);
        }
    }

    public function index()
    {
        return response()->json(Section::where('is_active', true)->orderBy('name_ar')->get());
    }

    public function adminIndex()
    {
        return response()->json(Section::orderBy('name_ar')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:255',
            'bg_image' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        return response()->json(Section::create($validated), 201);
    }

    public function update(Request $request, $id)
    {
        $section = Section::findOrFail($id);
        $section->update($request->only(['name_ar', 'name_en', 'icon', 'sort_order', 'is_active']));
        return response()->json($section);
    }

    public function destroy($id)
    {
        Section::findOrFail($id)->delete();
        return response()->json(['message' => 'Section deleted']);
    }

    /**
     * Public: full per-mall section tree (main sections + sub sections) with product counts.
     */
    public function mallSections(Request $request, $mallId)
    {
        static::ensureDefaults($mallId);

        $mainSections = static::ordered(MallSection::with('children')
            ->where('mall_id', $mallId)
            ->whereNull('parent_id')
            ->where('is_active', true))->get();

        $result = $mainSections->map(function ($sec) use ($mallId) {
            return [
                'id'            => $sec->id,
                'section_id'    => $sec->section_id,
                'name_ar'       => $sec->name_ar,
                'name_en'       => $sec->name_en,
                'icon'          => $sec->icon,
                'bg_image'      => $sec->bg_image,
                'is_custom'     => $sec->section_id === null,
                'product_count' => static::sectionProductCount($mallId, $sec),
                'children'      => $sec->children->where('is_active', true)->values()->map(function ($child) use ($mallId) {
                    return [
                        'id'            => $child->id,
                        'parent_id'     => $child->parent_id,
                        'name_ar'       => $child->name_ar,
                        'name_en'       => $child->name_en,
                        'icon'          => $child->icon,
                        'bg_image'      => $child->bg_image,
                        'is_custom'     => true,
                        'product_count' => Product::where('mall_id', $mallId)
                            ->where('is_active', true)
                            ->where('mall_section_id', $child->id)
                            ->count(),
                        'children'      => [],
                    ];
                })->all(),
            ];
        });

        $noSectionCount = Product::where('mall_id', $mallId)
            ->where('is_active', true)
            ->whereNull('mall_section_id')
            ->whereNull('section_id')
            ->count();

        return response()->json([
            'sections'         => $result,
            'no_section_count' => $noSectionCount,
        ]);
    }

    /**
     * Order sections by the owner-defined display order; sections never
     * explicitly ordered (sort_order = 0) fall back to alphabetical order.
     */
    private static function ordered($query)
    {
        return $query->orderByRaw('CASE WHEN sort_order = 0 THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderBy('name_ar');
    }

    /**
     * Count products that belong to a main section (direct + children + legacy global-section fallback).
     */
    public static function sectionProductCount($mallId, MallSection $sec)
    {
        $childIds = $sec->children()->where('is_active', true)->pluck('id');

        return Product::where('mall_id', $mallId)
            ->where('is_active', true)
            ->where(function ($q) use ($sec, $childIds) {
                $q->where('mall_section_id', $sec->id);
                if ($childIds->isNotEmpty()) {
                    $q->orWhereIn('mall_section_id', $childIds);
                }
                // Legacy: products still linked only via the global section
                if ($sec->section_id) {
                    $q->orWhere(function ($qq) use ($sec) {
                        $qq->whereNull('mall_section_id')->where('section_id', $sec->section_id);
                    });
                }
            })
            ->count();
    }

    /**
     * Owner: list the mall's section tree for management.
     */
    public function ownerSections(Request $request)
    {
        $mallIds = $request->user()->malls()->pluck('id');
        $targetMallId = $request->input('mall_id');
        if ($targetMallId && $mallIds->contains((int) $targetMallId)) {
            $mallIds = collect([(int) $targetMallId]);
        }

        foreach ($mallIds as $mallId) {
            static::ensureDefaults($mallId);
        }

        $rows = static::ordered(MallSection::whereIn('mall_id', $mallIds)
            ->whereNull('parent_id')
            ->with('children'))->get();

        $result = $rows->map(function ($sec) {
            return [
                'id'            => $sec->id,
                'mall_id'       => $sec->mall_id,
                'section_id'    => $sec->section_id,
                'parent_id'     => $sec->parent_id,
                'name_ar'       => $sec->name_ar,
                'name_en'       => $sec->name_en,
                'icon'          => $sec->icon,
                'bg_image'      => $sec->bg_image,
                'sort_order'    => $sec->sort_order,
                'is_active'     => $sec->is_active,
                'is_custom'     => $sec->section_id === null,
                'product_count' => static::sectionProductCount($sec->mall_id, $sec),
                'children'      => $sec->children->values()->map(function ($child) {
                    return [
                        'id'            => $child->id,
                        'mall_id'       => $child->mall_id,
                        'section_id'    => $child->section_id,
                        'parent_id'     => $child->parent_id,
                        'name_ar'       => $child->name_ar,
                        'name_en'       => $child->name_en,
                        'icon'          => $child->icon,
                        'bg_image'      => $child->bg_image,
                        'sort_order'    => $child->sort_order,
                        'is_active'     => $child->is_active,
                        'is_custom'     => true,
                        'product_count' => Product::where('mall_id', $child->mall_id)
                            ->where('mall_section_id', $child->id)
                            ->count(),
                        'children'      => [],
                    ];
                })->all(),
            ];
        });

        $noSectionCount = Product::whereIn('mall_id', $mallIds)
            ->whereNull('mall_section_id')
            ->whereNull('section_id')
            ->count();

        return response()->json([
            'sections'         => $result,
            'no_section_count' => $noSectionCount,
        ]);
    }

    /**
     * Owner: persist the display order of main sections and/or sub-sections.
     */
    public function ownerReorder(Request $request)
    {
        $validated = $request->validate([
            'mall_id'  => 'required|exists:malls,id',
            'order'    => 'nullable|array',
            'order.*'  => 'integer',
            'children' => 'nullable|array',
        ]);

        $mallIds = $request->user()->malls()->pluck('id');
        if (!$mallIds->contains((int) $validated['mall_id'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!empty($validated['order'])) {
            foreach (array_values($validated['order']) as $i => $id) {
                MallSection::where('id', $id)
                    ->where('mall_id', $validated['mall_id'])
                    ->whereNull('parent_id')
                    ->update(['sort_order' => $i + 1]);
            }
        }

        if (!empty($validated['children']) && is_array($validated['children'])) {
            foreach ($validated['children'] as $parentId => $childIds) {
                if (!is_array($childIds)) continue;
                foreach (array_values($childIds) as $i => $childId) {
                    MallSection::where('id', $childId)
                        ->where('mall_id', $validated['mall_id'])
                        ->where('parent_id', (int) $parentId)
                        ->update(['sort_order' => $i + 1]);
                }
            }
        }

        return response()->json(['message' => 'تم حفظ ترتيب الأقسام']);
    }

    public function ownerStore(Request $request)
    {
        $validated = $request->validate([
            'mall_id'    => 'required|exists:malls,id',
            'name_ar'    => 'required|string|max:255',
            'name_en'    => 'nullable|string|max:255',
            'parent_id'  => 'nullable|exists:mall_sections,id',
            'icon'       => 'nullable|string|max:255',
            'bg_image'   => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $mallIds = $request->user()->malls()->pluck('id');
        if (!$mallIds->contains((int) $validated['mall_id'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!empty($validated['parent_id'])) {
            $parent = MallSection::where('id', $validated['parent_id'])
                ->where('mall_id', $validated['mall_id'])
                ->whereNull('parent_id')
                ->first();
            if (!$parent) {
                return response()->json(['message' => 'Parent section is invalid or not a main section.'], 422);
            }
        }

        $section = MallSection::create($validated);

        return response()->json($section->fresh(['children']), 201);
    }

    public function ownerUpdate(Request $request, $id)
    {
        $section = MallSection::findOrFail($id);
        $mallIds = $request->user()->malls()->pluck('id');
        if (!$mallIds->contains($section->mall_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name_ar'    => 'sometimes|string|max:255',
            'name_en'    => 'nullable|string|max:255',
            'icon'       => 'nullable|string|max:255',
            'bg_image'   => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'sometimes|boolean',
        ]);

        $section->update($validated);

        return response()->json($section->fresh(['children']));
    }

    public function ownerDestroy(Request $request, $id)
    {
        $section = MallSection::findOrFail($id);
        $mallIds = $request->user()->malls()->pluck('id');
        if (!$mallIds->contains($section->mall_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Default (linked) sections are hidden so ensureDefaults() won't recreate them.
        if ($section->section_id !== null) {
            $section->update(['is_active' => false]);
            return response()->json(['message' => 'تم إخفاء القسم', 'hidden' => true]);
        }

        // Custom sections (and their sub-sections) are hard-deleted; products get unlinked by FK.
        $section->delete();
        return response()->json(['message' => 'تم حذف القسم']);
    }
}