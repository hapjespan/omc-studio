<?php

namespace App\Services\Audit;

use Illuminate\Support\Facades\Log;

class SecurityLog
{
    private const FORBIDDEN_KEYS = ['email', 'ip', 'ip_address'];

    private const EMAIL_PATTERN = '/[^\s@"]+@[^\s@"]+/';

    /** @param array<string, mixed> $context */
    public static function record(string $event, array $context = []): void
    {
        $context = array_diff_key($context, array_flip(self::FORBIDDEN_KEYS));
        $scrubbed = json_decode((string) preg_replace(self::EMAIL_PATTERN, '[removed]', json_encode($context, JSON_THROW_ON_ERROR)), true);

        Log::channel('security')->info($event, $scrubbed);
    }
}
