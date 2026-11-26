<?php

namespace Tests\Prototype;

use App\Enums\Role;
use App\Models\User;
use App\Services\Accounts\OneTimePassword;
use App\Services\Audit\SecurityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VariantsTest extends TestCase
{
    use RefreshDatabase;

    private const DATA = ['name' => 'N', 'email' => 'n@example.test', 'role' => 'administrator'];

    public static function variants(): array
    {
        return [
            'baseline: save random, issue second' => [function (OneTimePassword $p, array $d) {
                $u = new User($d);
                $u->password = $p->generate();
                $u->save();
                SecurityLog::record('user_created', ['subject_id' => $u->getKey(), 'role' => $u->role->value]);

                return [$u, $p->issueTo($u)];
            }],
            'A: issueTo unsaved user, log after' => [function (OneTimePassword $p, array $d) {
                $u = new User($d);
                $pw = $p->issueTo($u);
                SecurityLog::record('user_created', ['subject_id' => $u->getKey(), 'role' => $u->role->value]);

                return [$u, $pw];
            }],
            'B: baseline inside one transaction' => [function (OneTimePassword $p, array $d) {
                return DB::transaction(function () use ($p, $d) {
                    $u = new User($d);
                    $u->password = $p->generate();
                    $u->save();
                    $pw = $p->issueTo($u);
                    SecurityLog::record('user_created', ['subject_id' => $u->getKey(), 'role' => $u->role->value]);

                    return [$u, $pw];
                });
            }],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("variants")]
    public function test_variant(\Closure $create): void
    {
        $p = new OneTimePassword;
        $writes = [];
        DB::listen(function ($q) use (&$writes) {
            if (preg_match('/^(insert|update) "users"/i', $q->sql, $m)) {
                $writes[] = strtolower($m[1]);
            }
        });
        $events = 0;
        \Event::listen(\Illuminate\Log\Events\MessageLogged::class, function ($e) use (&$events) {
            $e->message === 'user_created' && $events++;
        });
        [$u, $pw] = $create($p, self::DATA);
        $fresh = $u->fresh();
        fwrite(STDERR, sprintf("\n  user writes=%s | one password=%s mustChange=%s expiry=%s | event rows=%d", implode('+', $writes), password_verify($pw, $fresh->password) || \Hash::check($pw, $fresh->password) ? 'yes' : 'NO', var_export((bool) $fresh->must_change_password, true), $fresh->one_time_password_expires_at ? 'yes' : 'no', $events));

        DB::table('users')->delete();
        $events = 0;
        User::saving(fn ($m) => $m->must_change_password ? throw new \RuntimeException('boom') : null);
        $before = [];
        try {
            $create($p, ['name' => 'M', 'email' => 'm@example.test', 'role' => 'administrator']);
        } catch (\Throwable) {
        }
        fwrite(STDERR, sprintf("\n  after injected failure on credential save: users=%d events=%d\n", DB::table('users')->count(), $events));
        $this->assertTrue(true);
    }
}
