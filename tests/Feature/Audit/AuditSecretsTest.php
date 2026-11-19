<?php

namespace Tests\Feature\Audit;

use App\Models\AuditEntry;
use App\Models\RecoveryCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditSecretsTest extends TestCase
{
    use RefreshDatabase;

    private function raw(): string
    {
        return DB::table('audit_entries')->get()->toJson();
    }

    public function test_a_password_change_is_an_event_without_the_password_or_its_hash(): void
    {
        $user = User::factory()->create();
        AuditEntry::query()->delete();

        $user->update(['password' => 'Sw0rdfish-plain-9921']);

        $entry = AuditEntry::query()->sole();
        $this->assertSame('password_changed', $entry->action);
        $this->assertSame([], $entry->changes);
        $this->assertStringNotContainsString('Sw0rdfish-plain-9921', $this->raw());
        $this->assertStringNotContainsString($user->fresh()->password, $this->raw());
    }

    public function test_a_second_factor_change_is_an_event_without_the_key(): void
    {
        $user = User::factory()->create();
        AuditEntry::query()->delete();

        $user->update(['two_factor_secret' => 'JBSWY3DPEHPK3PXP']);

        $entry = AuditEntry::query()->sole();
        $this->assertSame('second_factor_changed', $entry->action);
        $this->assertSame([], $entry->changes);
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $this->raw());
        $this->assertStringNotContainsString($user->getRawOriginal('two_factor_secret'), $this->raw());
    }

    public function test_creating_a_user_holds_no_hash_and_recovery_codes_write_nothing(): void
    {
        $user = User::factory()->create(['password' => 'Another-plain-7731']);
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        AuditEntry::query()->delete();
        $before = AuditEntry::count();
        RecoveryCode::create(['user_id' => $user->id, 'code_hash' => Hash::make('abcde-fghij')]);

        $this->assertSame($before, AuditEntry::count());
        $this->assertStringNotContainsString('abcde-fghij', $this->raw());

        AuditEntry::query()->delete();
        User::factory()->create();
        $created = AuditEntry::query()->sole();
        $this->assertArrayNotHasKey('password', $created->changes);
        $this->assertArrayNotHasKey('two_factor_secret', $created->changes);
    }
}
