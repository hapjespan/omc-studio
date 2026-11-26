<?php

namespace Tests\Feature\Audit;

use App\Enums\ActorType;
use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Audit\Audited;
use App\Services\Audit\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuditedSample extends Model
{
    use Audited;

    protected $guarded = [];

    protected $casts = ['layout_saved_at' => 'datetime'];
}

class AuditedTest extends TestCase
{
    use RefreshDatabase;

    private function lastEntry(): AuditEntry
    {
        return AuditEntry::query()->orderByDesc('id')->firstOrFail();
    }

    private function forgetEntries(): void
    {
        DB::table('audit_entries')->delete();
    }

    private function rawEntry(AuditEntry $entry): string
    {
        return (string) json_encode(DB::table('audit_entries')->where('id', $entry->id)->first());
    }

    public function test_a_create_writes_one_entry_with_the_new_values(): void
    {
        $organisation = Organisation::create(['name' => 'Noord']);

        $entry = $this->lastEntry();

        $this->assertSame(1, AuditEntry::query()->count());
        $this->assertSame('created', $entry->action);
        $this->assertSame('organisation', $entry->subject_type);
        $this->assertSame((string) $organisation->id, $entry->subject_id);
        $this->assertSame('Noord', $entry->subject_label);
        $this->assertSame([null, 'Noord'], $entry->changes['name']);
        $this->assertArrayNotHasKey('id', $entry->changes);
        $this->assertArrayNotHasKey('created_at', $entry->changes);
        $this->assertArrayNotHasKey('updated_at', $entry->changes);
    }

    public function test_without_a_signed_in_user_the_actor_is_the_system(): void
    {
        Organisation::create(['name' => 'Noord']);

        $this->assertSame(ActorType::System, $this->lastEntry()->actor_type);
        $this->assertNull($this->lastEntry()->actor_id);
    }

    public function test_a_change_writes_one_entry_with_old_and_new_value(): void
    {
        $organisation = Organisation::create(['name' => 'Noord']);
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->forgetEntries();

        $organisation->update(['name' => 'Zuid']);

        $entry = $this->lastEntry();

        $this->assertSame(1, AuditEntry::query()->count());
        $this->assertSame('changed', $entry->action);
        $this->assertSame(['Noord', 'Zuid'], $entry->changes['name']);
        $this->assertSame('Zuid', $entry->subject_label);
        $this->assertSame(ActorType::User, $entry->actor_type);
        $this->assertSame($user->id, $entry->actor_id);
    }

    public function test_a_delete_writes_one_entry_with_the_old_values(): void
    {
        $organisation = Organisation::create(['name' => 'Noord']);
        $this->forgetEntries();

        $organisation->delete();

        $entry = $this->lastEntry();

        $this->assertSame(1, AuditEntry::query()->count());
        $this->assertSame('deleted', $entry->action);
        $this->assertSame(['Noord', null], $entry->changes['name']);
    }

    public function test_an_entry_carries_its_organisation_and_canvas(): void
    {
        $canvas = Canvas::factory()->create();
        $element = Element::factory()->create(['canvas_id' => $canvas->id]);

        $entries = AuditEntry::query()->orderBy('id')->get()->keyBy('subject_type');

        $this->assertSame($canvas->id, $entries['canvas']->canvas_id);
        $this->assertSame($canvas->organisation_id, $entries['canvas']->organisation_id);
        $this->assertSame($canvas->organisation_id, $entries['organisation']->organisation_id);
        $this->assertSame($canvas->id, $entries['element']->canvas_id);
        $this->assertSame($element->id, $entries['element']->subject_id);
    }

    public function test_a_canvas_touch_writes_no_entry(): void
    {
        $canvas = Canvas::factory()->create();
        $this->forgetEntries();

        Element::factory()->create(['canvas_id' => $canvas->id]);
        $canvas->touch();

        $this->assertSame(['element'], AuditEntry::query()->pluck('subject_type')->unique()->all());
    }

    public function test_a_version_raise_writes_no_entry(): void
    {
        Schema::create('audited_samples', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->unsignedInteger('structure_version')->default(0);
            $table->unsignedInteger('layout_version')->default(0);
            $table->timestamp('layout_saved_at')->nullable();
            $table->timestamps();
        });

        $sample = AuditedSample::create(['name' => 'Een']);
        $this->forgetEntries();

        $sample->update(['structure_version' => 1, 'layout_version' => 2, 'layout_saved_at' => now()]);

        $this->assertSame(0, AuditEntry::query()->count());
    }

    public function test_a_password_change_writes_the_event_name_and_never_a_hash(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->forgetEntries();

        $user->update(['password' => 'a-very-long-new-password']);

        $entry = $this->lastEntry();

        $this->assertSame(1, AuditEntry::query()->count());
        $this->assertSame('password_changed', $entry->action);
        $this->assertSame([], $entry->changes);
        $this->assertSame('Wachtwoord gewijzigd', __('audit.action.'.$entry->action));
        $this->assertStringNotContainsString('$2y$', $this->rawEntry($entry));
        $this->assertStringNotContainsString('a-very-long-new-password', $this->rawEntry($entry));
    }

    public function test_creating_and_deleting_a_user_keeps_the_hash_and_the_key_out_of_the_entry(): void
    {
        $user = User::factory()->create(['two_factor_secret' => 'JBSWY3DPEHPK3PXP']);
        $created = $this->lastEntry();

        $user->delete();
        $deleted = $this->lastEntry();

        $this->assertSame('created', $created->action);
        $this->assertSame('deleted', $deleted->action);
        $this->assertSame([null, $user->name], $created->changes['name']);
        $this->assertArrayNotHasKey('password', $created->changes);
        $this->assertArrayNotHasKey('password', $deleted->changes);
        $this->assertArrayNotHasKey('two_factor_secret', $created->changes);
        $this->assertArrayNotHasKey('two_factor_secret', $deleted->changes);
        $this->assertStringNotContainsString('$2y$', $this->rawEntry($created).$this->rawEntry($deleted));
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $this->rawEntry($created).$this->rawEntry($deleted));
    }

    public function test_a_second_factor_change_writes_the_event_name_and_never_the_key(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->forgetEntries();

        $user->update(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_last_time_step' => 58000000]);

        $entry = $this->lastEntry();

        $this->assertSame(1, AuditEntry::query()->count());
        $this->assertSame('second_factor_changed', $entry->action);
        $this->assertSame([], $entry->changes);
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $this->rawEntry($entry));
        $this->assertStringNotContainsString('58000000', $this->rawEntry($entry));
    }

    public function test_a_field_change_beside_a_password_change_keeps_the_field_and_hides_the_secret(): void
    {
        $user = User::factory()->create(['name' => 'Sam']);
        $this->actingAs($user);
        $this->forgetEntries();

        $user->update(['name' => 'Robin', 'password' => 'a-very-long-new-password']);

        $entry = $this->lastEntry();

        $this->assertSame(1, AuditEntry::query()->count());
        $this->assertSame('password_changed', $entry->action);
        $this->assertSame(['name' => ['Sam', 'Robin']], $entry->changes);
    }

    public function test_a_service_names_the_action_through_the_context(): void
    {
        $canvas = Canvas::factory()->create();
        $this->forgetEntries();

        app(AuditContext::class)->during('archived', fn () => $canvas->update(['archived_at' => now()]));
        $archived = $this->lastEntry()->action;

        $canvas->update(['name' => 'Noord 2027']);

        $this->assertSame('archived', $archived);
        $this->assertSame('changed', $this->lastEntry()->action);
    }

    public function test_an_explicit_entry_carries_the_given_action(): void
    {
        $element = Element::factory()->create();
        $this->forgetEntries();

        app(AuditTrail::class)->record($element, 'undone', ['name' => ['Oud', 'Nieuw']]);

        $entry = $this->lastEntry();

        $this->assertSame('undone', $entry->action);
        $this->assertSame('element', $entry->subject_type);
        $this->assertSame($element->id, $entry->subject_id);
        $this->assertSame($element->canvas_id, $entry->canvas_id);
        $this->assertSame(['Oud', 'Nieuw'], $entry->changes['name']);
    }
}
