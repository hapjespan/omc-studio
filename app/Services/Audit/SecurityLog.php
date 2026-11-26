<?php

namespace App\Services\Audit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SecurityLog
{
    private const SESSION_KEY = 'security_ref';

    private const FORBIDDEN_KEYS = ['email', 'ip', 'ip_address'];

    private const EMAIL_PATTERN = '/[^\s@"]+@[^\s@"]+/';

    /** @param array<string, mixed> $context */
    public static function record(string $event, array $context = []): void
    {
        $context += ['user_id' => Auth::id(), 'session_ref' => self::sessionReference()];
        $context = array_diff_key($context, array_flip(self::FORBIDDEN_KEYS));
        $scrubbed = json_decode((string) preg_replace(self::EMAIL_PATTERN, '[removed]', json_encode($context, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)), true);

        Log::channel('security')->info($event, $scrubbed);
    }

    private static function sessionReference(): ?string
    {
        $request = app()->bound('request') ? app('request') : null;

        if (! $request instanceof Request || ! $request->hasSession()) {
            return null;
        }

        $session = $request->session();

        if (! is_string($session->get(self::SESSION_KEY))) {
            $session->put(self::SESSION_KEY, bin2hex(random_bytes(6)));
        }

        return $session->get(self::SESSION_KEY);
    }
}
