<?php

namespace App\Services\HealthChecks;

class HealthResult
{
    public function __construct(
        public string $status,      // pass|warning|failed|not_checked|review_required
        public string $message,
        public ?array $details = null,
        public ?string $userImpact = null, // low|medium|high|critical
        public string $severity = 'info',
    ) {}

    public static function pass(string $msg, ?array $details = null, string $severity = 'info'): self
    {
        return new self('pass', $msg, $details, 'low', $severity);
    }

    public static function warning(string $msg, ?array $details = null, string $impact = 'medium', string $severity = 'warning'): self
    {
        return new self('warning', $msg, $details, $impact, $severity);
    }

    public static function failed(string $msg, ?array $details = null, string $impact = 'high', string $severity = 'error'): self
    {
        return new self('failed', $msg, $details, $impact, $severity);
    }

    public static function notChecked(string $msg, ?array $details = null): self
    {
        return new self('not_checked', $msg, $details, 'low', 'info');
    }

    public static function reviewRequired(string $msg, ?array $details = null): self
    {
        return new self('review_required', $msg, $details, 'medium', 'warning');
    }
}
