<?php

namespace App\Services\Accounts;

use App\Models\RecoveryCode;
use App\Models\User;
use App\Services\Support\SerialisedWrite;
use Illuminate\Support\Facades\Hash;

class RecoveryCodes
{
    public const COUNT = 8;

    private const ALPHABET = '23456789abcdefghjkmnpqrstuvwxyz';

    private const GROUP = 5;

    private const SHAPE = '/^['.self::ALPHABET.']{'.self::GROUP.'}-['.self::ALPHABET.']{'.self::GROUP.'}$/';

    public function __construct(private readonly SerialisedWrite $write) {}

    /** @return list<string> */
    public function regenerate(User $user): array
    {
        $codes = [];

        while (count($codes) < self::COUNT) {
            $code = $this->generate();

            if (! in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        $rows = array_map(fn (string $code) => ['user_id' => $user->getKey(), 'code_hash' => Hash::make($code)], $codes);

        $this->write->run([$user], function () use ($user, $rows): void {
            $this->clear($user);
            RecoveryCode::query()->insert($rows);
        });

        return $codes;
    }

    public function clear(User $user): int
    {
        return RecoveryCode::query()->where('user_id', $user->getKey())->delete();
    }

    public function consume(User $user, string $code): bool
    {
        $code = mb_strtolower(trim($code));

        if (preg_match(self::SHAPE, $code) !== 1) {
            return false;
        }

        foreach (RecoveryCode::query()->where('user_id', $user->getKey())->get() as $stored) {
            if (Hash::check($code, (string) $stored->code_hash)) {
                return RecoveryCode::query()->whereKey($stored->getKey())->delete() === 1;
            }
        }

        return false;
    }

    public function remaining(User $user): int
    {
        return RecoveryCode::query()->where('user_id', $user->getKey())->count();
    }

    private function generate(): string
    {
        $code = '';

        for ($character = 0; $character < self::GROUP * 2; $character++) {
            $code .= ($character === self::GROUP ? '-' : '').self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}
