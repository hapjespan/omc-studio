<?php

namespace App\Services\Accounts;

use App\Models\User;
use App\Services\Audit\SecurityLog;
use App\Services\Support\SerialisedWrite;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class SecondFactorService
{
    public const ENROLLING = 'second_factor_enrolling';

    private const NAMESPACE = 'second-factor-password';

    private const QR_SIZE = 200;

    public function __construct(
        private readonly Google2FA $google,
        private readonly RecoveryCodes $codes,
        private readonly SerialisedWrite $write,
        private readonly TotpVerifier $verifier,
        private readonly SessionTerminator $sessions,
    ) {}

    /** @return array{secret: string, qr: string} */
    public function begin(User $user, string $password): array
    {
        $this->requirePassword($user, $password);

        if ($user->two_factor_confirmed_at !== null) {
            throw ValidationException::withMessages(['rule' => __('second_factor.already_active')]);
        }

        $user->fill(['two_factor_secret' => $this->google->generateSecretKey()])->saveQuietly();

        return $this->pair($user);
    }

    public function discardUnconfirmed(User $user): void
    {
        if ($user->two_factor_secret !== null && $user->two_factor_confirmed_at === null) {
            $user->fill(['two_factor_secret' => null])->saveQuietly();
        }
    }

    /** @return array{secret: string, qr: string}|null */
    public function enrolment(User $user): ?array
    {
        if ($user->two_factor_secret === null || $user->two_factor_confirmed_at !== null) {
            return null;
        }

        return $this->pair($user);
    }

    /** @return list<string> */
    public function confirm(User $user, string $code, string $currentSessionId): array
    {
        if ($user->two_factor_confirmed_at !== null) {
            throw ValidationException::withMessages(['rule' => __('second_factor.already_active')]);
        }

        if ($user->two_factor_secret === null) {
            throw ValidationException::withMessages(['rule' => __('second_factor.not_started')]);
        }

        if (! $this->verifier->verify($user, $code)) {
            throw ValidationException::withMessages(['code' => __('second_factor.code_invalid')]);
        }

        $ended = 0;

        $codes = $this->write->run([$user], function () use ($user, $currentSessionId, &$ended): array {
            if ($user->getAttribute('two_factor_confirmed_at') !== null) {
                throw ValidationException::withMessages(['rule' => __('second_factor.already_active')]);
            }

            $user->fill(['two_factor_confirmed_at' => Carbon::now()])->save();
            $fresh = $this->codes->regenerate($user);
            $ended = $this->sessions->endOthers($user, $currentSessionId);

            return $fresh;
        });

        $this->record('second_factor_enabled', $user, $ended);

        return $codes;
    }

    /** @return list<string> */
    public function regenerate(User $user, string $password, string $currentSessionId): array
    {
        $this->requirePassword($user, $password);
        $ended = 0;

        $codes = $this->write->run([$user], function () use ($user, $currentSessionId, &$ended): array {
            $this->requireActive($user);
            $fresh = $this->codes->regenerate($user);
            $ended = $this->sessions->endOthers($user, $currentSessionId);

            return $fresh;
        });

        $this->record('recovery_codes_regenerated', $user, $ended);

        return $codes;
    }

    public function disable(User $user, string $password, string $currentSessionId): void
    {
        $this->requirePassword($user, $password);

        $ended = $this->write->run([$user], function () use ($user, $currentSessionId): int {
            $this->requireActive($user);

            $user->fill([
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_time_step' => null,
            ])->save();

            $this->codes->clear($user);

            return $this->sessions->endOthers($user, $currentSessionId);
        });

        $this->record('second_factor_disabled', $user, $ended);
    }

    public function requirePassword(User $user, string $password): void
    {
        $throttle = new Throttle(self::NAMESPACE);
        $identifier = (string) $user->getKey();

        if (! $throttle->claim($identifier)) {
            SecurityLog::record('second_factor_password_throttled');

            throw ValidationException::withMessages([
                'current_password' => __('auth.throttled', ['seconds' => $throttle->secondsLeft($identifier)]),
            ]);
        }

        if (! Hash::check($password, (string) $user->password)) {
            $throttle->fail($identifier);
            SecurityLog::record('second_factor_password_failed');

            throw ValidationException::withMessages(['current_password' => __('account.current_password_wrong')]);
        }

        $throttle->clear($identifier);
    }

    private function requireActive(User $user): void
    {
        if ($user->getAttribute('two_factor_confirmed_at') === null) {
            throw ValidationException::withMessages(['rule' => __('second_factor.not_active')]);
        }
    }

    private function record(string $event, User $user, int $ended): void
    {
        SecurityLog::record($event, ['user_id' => $user->getKey(), 'sessions_ended' => $ended]);
    }

    /** @return array{secret: string, qr: string} */
    private function pair(User $user): array
    {
        $secret = (string) $user->two_factor_secret;
        $url = $this->google->getQRCodeUrl((string) config('app.name'), (string) $user->email, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(self::QR_SIZE), new SvgImageBackEnd));

        return ['secret' => $secret, 'qr' => 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($url))];
    }
}
