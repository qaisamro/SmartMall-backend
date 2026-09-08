<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\RequestIdMiddleware::class);
        $middleware->alias([
            'role'       => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'mall.theme' => \App\Http\Middleware\ApplyMallTheme::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });
        $exceptions->report(function (\Throwable $e) {
            try {
                if ($e instanceof \Illuminate\Auth\AuthenticationException) return;
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    // سجل أخطاء التحقق كـ warning في سجل الصحة (مفيد لتعديل المنشأة)
                    try {
                        \App\Services\ErrorMonitoringService::report([
                            'type' => 'validation.' . ($e->validator->fails() ? array_key_first($e->validator->failed() ?? []) : 'unknown'),
                            'severity' => 'warning',
                            'source' => 'backend',
                            'message' => 'فشل التحقق: ' . implode(', ', array_map(fn($msgs) => implode(', ', (array)$msgs), $e->errors())),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'status_code' => 422,
                            'request_id' => request()->attributes->get('request_id'),
                            'stack_trace' => substr($e->getTraceAsString(), 0, 2000),
                        ]);
                    } catch (\Throwable $inner2) {}
                    return;
                }
                $severity = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException && $e->getStatusCode() < 500 ? 'warning' : 'error';
                if ($e->getCode() >= 500 || str_contains($e->getMessage(), 'CRITICAL')) $severity = 'critical';
                \App\Services\ErrorMonitoringService::reportThrowable($e, $severity);
            } catch (\Throwable $inner) {
                // لا نكسر سلسلة التقارير
            }
        });
    })->create();
