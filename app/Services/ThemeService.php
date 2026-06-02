<?php

namespace App\Services;

use App\Models\Mall;
use App\Models\MallTheme;

class ThemeService
{
    public const BORDER_RADIUS_OPTIONS = ['modern', 'sharp', 'rounded'];

    public function defaultTheme(): array
    {
        return [
            'primary_color' => '#3b82f6',
            'secondary_color' => '#0f172a',
            'accent_color' => '#10b981',
            'background_color' => '#080810',
            'text_color' => '#f8fafc',
            'dark_mode' => true,
            'font_family' => 'Cairo',
            'border_radius' => 'modern',
        ];
    }

    public function predefinedThemes(): array
    {
        return [
            'smart-blue' => [
                'name' => 'Smart Blue',
                ...$this->defaultTheme(),
            ],
            'luxury-gold' => [
                'name' => 'Luxury Gold',
                'primary_color' => '#d97706',
                'secondary_color' => '#17120a',
                'accent_color' => '#facc15',
                'background_color' => '#090806',
                'text_color' => '#fff7ed',
                'dark_mode' => true,
                'font_family' => 'Cairo',
                'border_radius' => 'modern',
            ],
            'fresh-market' => [
                'name' => 'Fresh Market',
                'primary_color' => '#16a34a',
                'secondary_color' => '#ecfdf5',
                'accent_color' => '#f97316',
                'background_color' => '#f8fafc',
                'text_color' => '#10231a',
                'dark_mode' => false,
                'font_family' => 'Inter',
                'border_radius' => 'rounded',
            ],
            'mono-sharp' => [
                'name' => 'Mono Sharp',
                'primary_color' => '#111827',
                'secondary_color' => '#f3f4f6',
                'accent_color' => '#ef4444',
                'background_color' => '#ffffff',
                'text_color' => '#111827',
                'dark_mode' => false,
                'font_family' => 'Inter',
                'border_radius' => 'sharp',
            ],
        ];
    }

    public function getThemeForMall(Mall $mall): MallTheme
    {
        return $mall->theme()->firstOrCreate([], $this->defaultTheme());
    }

    public function saveTheme(Mall $mall, array $data): MallTheme
    {
        $themeData = array_merge($this->defaultTheme(), $this->sanitize($data));

        return $mall->theme()->updateOrCreate(
            ['mall_id' => $mall->id],
            $themeData
        );
    }

    public function sanitize(array $data): array
    {
        $allowed = array_intersect_key($data, $this->defaultTheme());

        foreach (['primary_color', 'secondary_color', 'accent_color', 'background_color', 'text_color'] as $field) {
            if (isset($allowed[$field])) {
                $allowed[$field] = strtolower($allowed[$field]);
            }
        }

        if (isset($allowed['dark_mode'])) {
            $allowed['dark_mode'] = filter_var($allowed['dark_mode'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        if (isset($allowed['border_radius']) && ! in_array($allowed['border_radius'], self::BORDER_RADIUS_OPTIONS, true)) {
            $allowed['border_radius'] = 'modern';
        }

        return $allowed;
    }
}
