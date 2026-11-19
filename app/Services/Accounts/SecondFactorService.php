<?php

namespace App\Services\Accounts;

use App\Models\User;
use App\Services\Audit\SecurityLog;
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

    public function __construct(private readonly Google2FA $google) {}

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

    public function confirm(User $user, string $code): void
    {
        if ($user->two_factor_confirmed_at !== null) {
            throw ValidationException::withMessages(['rule' => __('second_factor.already_active')]);
        }

        if ($user->two_factor_secret === null) {
            throw ValidationException::withMessages(['rule' => __('second_factor.not_started')]);
        }

        if (! $this->google->verifyKey((string) $user->two_factor_secret, $code, 1, $this->step())) {
            throw ValidationException::withMessages(['code' => __('second_factor.code_invalid')]);
        }

        $user->fill(['two_factor_confirmed_at' => Carbon::now()])->save();
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

    private function step(): int
    {
        return intdiv(Carbon::now()->getTimestamp(), 30);
    }
}
