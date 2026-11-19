<?php

namespace Tests\Feature\Accounts;

use App\Models\AuditEntry;
use App\Models\UndoRecord;
use App\Models\User;
use App\Services\Accounts\UserDeletion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserDeletionTest extends TestCase
{
    use RefreshDatabase;

    private const NAME = 'Target Person';

    private const RENAMED = 'Renamed Person';

    private const EMAIL = 'target@example.test';

    private const DEVICE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const OTHER_DEVICE = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function test_a_deletion_ends_the_sessions_and_leaves_no_name_or_address_in_the_audit_log(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = $this->targetWithHistory($administrator);
        $bystander = User::factory()->create();
        $this->storedSession(self::OTHER_DEVICE, $bystander);

        app(UserDeletion::class)->delete($target, $administrator);

        $this->assertDatabaseMissing('users', ['id' => $target->getKey()]);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->getKey())->count());
        $this->assertDatabaseHas('sessions', ['id' => self::OTHER_DEVICE]);

        foreach (AuditEntry::query()->get() as $entry) {
            $written = (string) json_encode($entry->getAttributes());
            foreach ([self::NAME, self::RENAMED, self::EMAIL] as $identifying) {
                $this->assertStringNotContainsStringIgnoringCase($identifying, $written);
            }
        }
    }

    public function test_entries_about_the_user_show_the_pseudonym_and_entries_by_the_user_lose_the_actor(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = $this->targetWithHistory($administrator);

        app(UserDeletion::class)->delete($target, $administrator);

        $about = AuditEntry::query()->where('subject_type', 'user')->where('subject_id', (string) $target->getKey())->get();
        $this->assertCount(3, $about);
        foreach ($about as $entry) {
            $this->assertSame(__('users.deleted_user'), $entry->subject_label);
            foreach (['name', 'email'] as $key) {
                foreach ($entry->changes[$key] ?? [] as $value) {
                    $this->assertContains($value, [null, __('users.deleted_user')]);
                }
            }
        }

        $this->assertSame(1, AuditEntry::query()->where('action', 'deleted')->where('subject_type', 'user')->count());
        $this->assertSame(0, AuditEntry::query()->where('actor_id', $target->getKey())->count());
        $this->assertSame(1, AuditEntry::query()->whereNull('actor_id')->where('actor_type', 'user')->count());
    }

    public function test_a_deletion_offers_no_undo_and_removes_the_undo_records_of_the_user(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = $this->targetWithHistory($administrator);
        UndoRecord::create([
            'token' => 'a-token',
            'user_id' => $target->getKey(),
            'kind' => 'element',
            'payload' => ['name' => self::RENAMED],
            'expires_at' => Carbon::now()->addSeconds(10),
        ]);

        app(UserDeletion::class)->delete($target, $administrator);

        $this->assertSame(0, UndoRecord::query()->count());
    }

    public function test_the_security_log_records_the_deletion_without_the_address(): void
    {
        $log = storage_path('logs/security-'.Carbon::now()->format('Y-m-d').'.log');
        File::delete($log);
        $administrator = User::factory()->administrator()->create();
        $target = $this->targetWithHistory($administrator);

        $this->actingAs($administrator);
        app(UserDeletion::class)->delete($target, $administrator);

        $written = (string) File::get($log);
        $this->assertStringContainsString('user_deleted', $written);
        $this->assertStringContainsString('"sessions_ended":1', $written);
        $this->assertStringNotContainsString(self::EMAIL, $written);
    }

    public function test_the_own_account_guard_refuses_the_deletion_and_keeps_the_user(): void
    {
        $administrator = User::factory()->administrator()->create();
        User::factory()->administrator()->create();

        $this->expectException(ValidationException::class);

        try {
            app(UserDeletion::class)->delete($administrator, $administrator);
        } finally {
            $this->assertDatabaseHas('users', ['id' => $administrator->getKey()]);
        }
    }

    public function test_a_short_name_inside_a_longer_word_leaves_an_unrelated_entry_untouched(): void
    {
        $administrator = User::factory()->administrator()->create();
        Auth::login($administrator);
        $target = User::factory()->create(['name' => 'Ed', 'email' => 'ed@example.test']);
        Auth::logout();
        $unrelated = $this->canvasEntry('Education Fund', ['name' => [null, 'Edward Edison'], 'note' => [null, 'edition of the Education Fund']]);
        $mentioning = $this->canvasEntry(str_repeat('a', 140).' Ed', ['note' => [null, 'Ed wrote to ed@example.test.']]);
        $untouched = (array) $unrelated->fresh()?->getAttributes();

        app(UserDeletion::class)->delete($target, $administrator);

        $this->assertSame($untouched, (array) $unrelated->fresh()?->getAttributes());

        $mentioning->refresh();
        $pseudonym = (string) __('users.deleted_user');
        $this->assertSame(150, mb_strlen((string) $mentioning->subject_label));
        $this->assertStringNotContainsString(' Ed', (string) $mentioning->subject_label);
        $this->assertStringContainsString(mb_substr($pseudonym, 0, 9), (string) $mentioning->subject_label);
        $this->assertSame($pseudonym.' wrote to '.$pseudonym.'.', $mentioning->changes['note'][1]);
    }

    /** @param array<string, array{mixed, mixed}> $changes */
    private function canvasEntry(string $label, array $changes): AuditEntry
    {
        return AuditEntry::create([
            'occurred_at' => Carbon::now(),
            'actor_type' => 'system',
            'actor_id' => null,
            'action' => 'created',
            'subject_type' => 'canvas',
            'subject_id' => '9',
            'subject_label' => $label,
            'changes' => $changes,
        ]);
    }

    private function targetWithHistory(User $administrator): User
    {
        Auth::login($administrator);
        $target = User::factory()->create(['name' => self::NAME, 'email' => self::EMAIL]);
        $target->fill(['name' => self::RENAMED])->save();

        Auth::login($target);
        $administrator->fill(['name' => 'Administrator'])->save();

        Auth::logout();
        AuditEntry::create([
            'occurred_at' => Carbon::now(),
            'actor_type' => 'system',
            'actor_id' => null,
            'action' => 'created',
            'subject_type' => 'canvas',
            'subject_id' => '7',
            'subject_label' => 'Canvas of '.self::RENAMED,
            'changes' => ['finalised_by' => [null, self::EMAIL], 'note' => [null, 'Created for '.self::NAME]],
        ]);

        $this->storedSession(self::DEVICE, $target);

        return $target;
    }

    private function storedSession(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->getKey(),
            'payload' => base64_encode((string) json_encode([Auth::guard('web')->getName() => $user->getKey()])),
            'last_activity' => Carbon::now()->getTimestamp(),
        ]);
    }
}
