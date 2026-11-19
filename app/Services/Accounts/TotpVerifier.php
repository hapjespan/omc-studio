<?php

namespace App\Services\Accounts;

use App\Models\User;
use App\Services\Support\SerialisedWrite;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TotpVerifier
{
    private const SECONDS = 30;

    private const WINDOW = 1;

    private const MATCHED_STEP = -1;

    public function __construct(private readonly Google2FA $google, private readonly SerialisedWrite $write) {}

    public function verify(User $user, string $code): bool
    {
        return $this->write->run([$user], function () use ($user, $code): bool {
            $secret = $user->getAttribute('two_factor_secret');

            if ($secret === null) {
                return false;
            }

            $accepted = $this->google->verifyKeyNewer((string) $secret, $code, self::MATCHED_STEP, self::WINDOW, $this->step());

            if ($accepted === false) {
                return false;
            }

            $last = $user->getAttribute('two_factor_last_time_step');

            if ($last !== null && $last >= $accepted) {
                throw ValidationException::withMessages(['code' => __('second_factor.code_reused')]);
            }

            $user->fill(['two_factor_last_time_step' => (int) $accepted])->save();

            return true;
        });
    }

    public function step(): int
    {
        return intdiv(Carbon::now()->getTimestamp(), self::SECONDS);
    }
}
