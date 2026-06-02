<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Mall;
use App\Services\ThemeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MallThemeController extends Controller
{
    public function __construct(private readonly ThemeService $themeService)
    {
    }

    public function show(int $id)
    {
        $mall = Mall::with('theme')->findOrFail($id);

        return response()->json([
            'mall' => $mall,
            'theme' => $this->themeService->getThemeForMall($mall),
            'predefined_themes' => $this->themeService->predefinedThemes(),
        ]);
    }

    public function store(Request $request, int $id)
    {
        return $this->save($request, $id, 201);
    }

    public function update(Request $request, int $id)
    {
        return $this->save($request, $id);
    }

    private function save(Request $request, int $id, int $status = 200)
    {
        $mall = Mall::findOrFail($id);

        $validated = $request->validate([
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'background_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'text_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'dark_mode' => ['required', 'boolean'],
            'font_family' => ['nullable', 'string', 'max:80'],
            'border_radius' => ['required', Rule::in(ThemeService::BORDER_RADIUS_OPTIONS)],
        ]);

        $theme = $this->themeService->saveTheme($mall, $validated);

        return response()->json([
            'message' => 'Theme saved successfully.',
            'mall' => $mall->fresh('theme'),
            'theme' => $theme,
        ], $status);
    }
}
