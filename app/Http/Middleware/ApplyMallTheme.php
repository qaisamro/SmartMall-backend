<?php

namespace App\Http\Middleware;

use App\Models\Mall;
use App\Services\ThemeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyMallTheme
{
    public function __construct(private readonly ThemeService $themeService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $mall = $this->resolveMall($request);

        if ($mall) {
            $request->attributes->set('mall_theme', $this->themeService->getThemeForMall($mall));
        }

        $response = $next($request);

        if ($mall) {
            $response->headers->set('X-Mall-Id', (string) $mall->id);
        }

        return $response;
    }

    private function resolveMall(Request $request): ?Mall
    {
        $mallId = $request->route('mall')
            ?? $request->route('id')
            ?? $request->query('mall_id')
            ?? $request->input('mall_id');

        if ($mallId && is_numeric($mallId)) {
            return Mall::find((int) $mallId);
        }

        $slug = $request->route('slug');
        if ($slug) {
            return Mall::where('slug', $slug)->first();
        }

        return null;
    }
}
