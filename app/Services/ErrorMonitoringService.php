<?php

namespace App\Services;

use App\Models\SystemError;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ErrorMonitoringService
{
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password',
        'token', 'access_token', 'api_key', 'secret', 'credit_card', 'cvv', 'card_number',
    ];

    public static function sanitize(string $text): string
    {
        foreach (self::SENSITIVE_KEYS as $key) {
            $text = preg_replace('/' . preg_quote($key, '/') . '\s*[:=]\s*\S+/i', $key . ': [REDACTED]', $text);
        }
        return $text;
    }

    public static function fingerprint(string $source, string $type, string $message, ?string $file, ?int $line): string
    {
        $raw = implode('|', [$source, $type, substr($message, 0, 300), $file ?: '', (string) ($line ?: '')]);
        return hash('sha256', $raw);
    }

    /**
     * @param array{type:string,severity:string,source:string,message:string,file?:string,line?:int,url?:string,route?:string,method?:string,status_code?:int,request_id?:string,stack_trace?:string,user_id?:int,user_role?:string} $payload
     */
    public static function report(array $payload): SystemError
    {
        $payload['message'] = self::sanitize(substr($payload['message'] ?? 'Unknown', 0, 2000));
        if (!empty($payload['stack_trace'])) {
            $payload['stack_trace'] = self::sanitize(substr($payload['stack_trace'], 0, 8000));
        }

        $fingerprint = self::fingerprint(
            $payload['source'] ?? 'backend',
            $payload['type'] ?? 'exception',
            $payload['message'],
            $payload['file'] ?? null,
            $payload['line'] ?? null
        );

        // إذا لم تكن جداول النظام موجودة بعد (قبل migrate), لا نكسر الطلب
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('system_errors')) {
                \Illuminate\Support\Facades\Log::warning('system_errors table missing, skipping DB report', ['fingerprint' => $fingerprint]);
                // إرجاع كائن وهمي لتجنب كسر السلسلة
                $dummy = new SystemError(['fingerprint' => $fingerprint, 'message' => $payload['message']]);
                $dummy->id = 0;
                return $dummy;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Schema check failed', ['error' => $e->getMessage()]);
            $dummy = new SystemError(['fingerprint' => $fingerprint, 'message' => $payload['message']]);
            $dummy->id = 0;
            return $dummy;
        }

        try {
            $existing = SystemError::where('fingerprint', $fingerprint)->first();
            if ($existing) {
                $existing->increment('occurrences');
                $existing->update(['last_seen_at' => now()]);
                return $existing->fresh();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('system_errors query failed', ['error' => $e->getMessage()]);
            $dummy = new SystemError(['fingerprint' => $fingerprint, 'message' => $payload['message']]);
            $dummy->id = 0;
            return $dummy;
        }

        try {
            return SystemError::create([
                'fingerprint' => $fingerprint,
                'type' => $payload['type'] ?? 'exception',
                'severity' => $payload['severity'] ?? 'error',
                'source' => $payload['source'] ?? 'backend',
                'message' => $payload['message'],
                'file' => isset($payload['file']) ? substr($payload['file'], 0, 255) : null,
                'line' => $payload['line'] ?? null,
                'url' => isset($payload['url']) ? substr($payload['url'], 0, 500) : (Request::fullUrl() ? substr(Request::fullUrl(), 0, 500) : null),
                'route' => $payload['route'] ?? (Request::route()?->getName()),
                'method' => $payload['method'] ?? Request::method(),
                'status_code' => $payload['status_code'] ?? null,
                'request_id' => $payload['request_id'] ?? Request::attributes->get('request_id'),
                'stack_trace' => $payload['stack_trace'] ?? null,
                'user_id' => $payload['user_id'] ?? Auth::id(),
                'user_role' => $payload['user_role'] ?? (Auth::user()?->getRoleNames()->first()),
                'occurrences' => 1,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('system_errors create failed', ['error' => $e->getMessage()]);
            $dummy = new SystemError(['fingerprint' => $fingerprint, 'message' => $payload['message']]);
            $dummy->id = 0;
            return $dummy;
        }
    }

    public static function reportThrowable(\Throwable $e, string $severity = 'error', ?string $requestId = null): SystemError
    {
        $source = 'backend';
        if (str_contains($e->getFile(), 'database')) $source = 'database';
        elseif (str_contains($e->getFile(), 'Queue') || str_contains($e->getMessage(), 'queue')) $source = 'queue';

        return self::report([
            'type' => class_basename($e),
            'severity' => $severity,
            'source' => $source,
            'message' => $e->getMessage() ?: get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'stack_trace' => $e->getTraceAsString(),
            'status_code' => method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500,
            'request_id' => $requestId ?: Request::attributes->get('request_id'),
        ]);
    }
}
