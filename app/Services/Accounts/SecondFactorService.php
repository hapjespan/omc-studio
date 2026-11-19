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
    private const NAMESPACE = 'second-factor-password';

    private const QR_SIZE = 200;

    public function __construct(
        private readonly Google2FA $google,
        private readonly RecoveryCodes $codes,
        private readonly SerialisedWrite $write,
        private readonly TotpVerifier $verifier,
    ) {}

    /** @return array{secret: string, qr: string} */
    public function begin(User $user, string $password): array
    {
        $this->requirePassword($user, $password);

        if ($user->two_factor_confirmed_at !== null) {
            throw ValidationException::withMessages(['rule' => __('second_factor.already_active')]);
        }

        $secret = $this->google->generateSecretKey();
        $user->fill(['two_factor_secret' => $secret])->save();

        return ['secret' => $secret, 'qr' => $this->qrCode($user, $secret)];
    }

    /** @return list<string> */
    public function confirm(User $user, string $code): array
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

        return $this->write->run([$user], function () use ($user): array {
            if ($user->getAttribute('two_factor_confirmed_at') !== null) {
                throw ValidationException::withMessages(['rule' => __('second_factor.already_active')]);
            }

            $user->fill(['two_factor_confirmed_at' => Carbon::now()])->save();

            return $this->codes->regenerate($user);
        });
    }

    public function requirePassword(User $user, string $password): void
    {
        $throttle = new Throttle(self::NAMESPACE);
        $identifier = (string) $user->getKey();

        if ($throttle->tooManyAttempts($identifier)) {
            SecurityLog::record('second_factor_password_throttled');

            throw ValidationException::withMessages([
                'current_password' => __('auth.throttled', ['seconds' => $throttle->secondsLeft($identifier)]),
            ]);
        }

        if (! Hash::check($password, (string) $user->password)) {
            $throttle->hit($identifier);
            SecurityLog::record('second_factor_password_failed');

            throw ValidationException::withMessages(['current_password' => __('account.current_password_wrong')]);
        }

        $throttle->clear($identifier);
    }

    private function qrCode(User $user, string $secret): string
    {
        $url = $this->google->getQRCodeUrl((string) config('app.name'), (string) $user->email, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(self::QR_SIZE), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($url));
    }
}
