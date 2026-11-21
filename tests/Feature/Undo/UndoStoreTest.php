<?php

namespace Tests\Feature\Undo;

use App\Enums\ElementKind;
use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\UndoRecord;
use App\Models\User;
use App\Services\Audit\DeletesModelByModel;
use App\Services\Undo\UndoStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UndoStoreTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private Canvas $canvas;

    private Element $domain;

    private Element $activity;

    /** @var array<string, mixed> */
    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
        $this->canvas = Canvas::factory()->create();
        $this->domain = Element::factory()->create(['canvas_id' => $this->canvas->id, 'kind' => ElementKind::Domain, 'name' => 'Inkoop']);
        $this->activity = Element::factory()->create(['canvas_id' => $this->canvas->id, 'parent_id' => $this->domain->id, 'kind' => ElementKind::Activity, 'name' => 'Werving']);

        $this->payload = ['elements' => [$this->raw($this->domain), $this->raw($this->activity)]];
    }

    public function test_remember_returns_a_token_of_forty_characters_and_holds_the_payload_for_ten_minutes(): void
    {
        $this->travelTo(now()->startOfSecond());

        $token = $this->store()->remember('element_deletion', $this->payload, $this->administrator);

        $record = UndoRecord::findOrFail($token);

        $this->assertSame(40, strlen($token));
        $this->assertSame($this->administrator->id, $record->user_id);
        $this->assertSame('element_deletion', $record->kind);
        $this->assertSame('Inkoop', $record->payload['elements'][0]['name']);
        $this->assertSame(600, (int) round(now()->diffInSeconds($record->expires_at)));
    }

    public function test_remember_removes_the_records_that_expired(): void
    {
        UndoRecord::create([
            'token' => 'stale',
            'user_id' => $this->administrator->id,
            'kind' => 'element_deletion',
            'payload' => [],
            'expires_at' => now()->subMinute(),
        ]);

        $token = $this->store()->remember('element_deletion', $this->payload, $this->administrator);

        $this->assertNull(UndoRecord::find('stale'));
        $this->assertNotNull(UndoRecord::find($token));
    }

    public function test_a_restore_removes_the_records_that_expired(): void
    {
        $token = $this->deleteSubtree();
        UndoRecord::create([
            'token' => 'stale',
            'user_id' => User::factory()->create()->id,
            'kind' => 'element_deletion',
            'payload' => [],
            'expires_at' => now()->subMinute(),
        ]);

        $this->store()->restore($token, $this->administrator);

        $this->assertNull(UndoRecord::find('stale'));
    }

    public function test_a_restore_puts_the_whole_subtree_back_with_its_own_identifiers_and_writes_one_undone_entry_per_record(): void
    {
        $token = $this->deleteSubtree();

        $this->store()->restore($token, $this->administrator);

        $domain = Element::findOrFail($this->domain->id);

        $this->assertSame('Inkoop', $domain->name);
        $this->assertSame('inkoop', $domain->name_normalised);
        $this->assertSame($this->domain->id, Element::findOrFail($this->activity->id)->parent_id);
        $this->assertSame(2, Element::query()->count());
        $this->assertSame(['undone', 'undone'], AuditEntry::query()->orderBy('id')->pluck('action')->all());
        $this->assertEqualsCanonicalizing(
            [$this->domain->id, $this->activity->id],
            AuditEntry::query()->pluck('subject_id')->all(),
        );
        $this->assertNull(UndoRecord::find($token));
    }

    public function test_an_expired_token_restores_nothing(): void
    {
        $token = $this->deleteSubtree();

        $this->travel(11)->minutes();

        $this->assertSame('Ongedaan maken kan niet meer', $this->refusal($token, $this->administrator));
        $this->assertSame(0, Element::query()->count());
    }

    public function test_another_users_token_restores_nothing(): void
    {
        $token = $this->deleteSubtree();

        $this->assertSame('Ongedaan maken kan niet meer', $this->refusal($token, User::factory()->administrator()->create()));
        $this->assertSame(0, Element::query()->count());
        $this->assertNotNull(UndoRecord::find($token));
    }

    public function test_an_unknown_token_restores_nothing(): void
    {
        $this->deleteSubtree();

        $this->assertSame('Ongedaan maken kan niet meer', $this->refusal('nothing', $this->administrator));
        $this->assertSame(0, Element::query()->count());
    }

    public function test_a_name_taken_meanwhile_names_the_rule_and_restores_nothing(): void
    {
        $token = $this->deleteSubtree();

        Element::factory()->create(['canvas_id' => $this->canvas->id, 'area_id' => $this->domain->area_id, 'kind' => ElementKind::Domain, 'name' => ' inkoop ']);
        DB::table('audit_entries')->delete();

        $this->assertSame('Naam: er staat al een element met deze naam op deze plek', $this->refusal($token, $this->administrator));
        $this->assertSame(1, Element::query()->count());
        $this->assertSame(0, AuditEntry::query()->count());
        $this->assertNotNull(UndoRecord::find($token));
    }

    public function test_an_archived_name_does_not_block_the_restore(): void
    {
        $token = $this->deleteSubtree();

        Element::factory()->create(['canvas_id' => $this->canvas->id, 'area_id' => $this->domain->area_id, 'kind' => ElementKind::Domain, 'name' => 'Inkoop', 'archived_at' => now()]);

        $this->store()->restore($token, $this->administrator);

        $this->assertSame(3, Element::query()->count());
    }

    public function test_a_removed_parent_names_the_rule_and_restores_nothing(): void
    {
        $this->activity->refresh();
        $token = $this->store()->remember('element_deletion', ['elements' => [$this->raw($this->activity)]], $this->administrator);
        DB::transaction(fn () => app(DeletesModelByModel::class)->delete($this->activity));
        DB::transaction(fn () => app(DeletesModelByModel::class)->delete($this->domain));

        $this->assertSame('De rij waar dit onder hoorde bestaat niet meer', $this->refusal($token, $this->administrator));
        $this->assertSame(0, Element::query()->count());
    }

    public function test_an_archived_canvas_takes_no_restore(): void
    {
        $token = $this->deleteSubtree();

        $this->canvas->update(['archived_at' => now()]);

        $this->assertSame('Dit canvas is gearchiveerd', $this->refusal($token, $this->administrator));
        $this->assertSame(0, Element::query()->count());
    }

    public function test_the_route_restores_for_an_administrator(): void
    {
        $token = $this->deleteSubtree();

        $this->actingAs($this->administrator)->post('/undo/'.$token)->assertRedirect();

        $this->assertSame(2, Element::query()->count());
    }

    public function test_the_route_refuses_a_broken_rule_on_the_key_rule(): void
    {
        $token = $this->deleteSubtree();

        Element::factory()->create(['canvas_id' => $this->canvas->id, 'area_id' => $this->domain->area_id, 'kind' => ElementKind::Domain, 'name' => 'Inkoop']);

        $this->actingAs($this->administrator)->post('/undo/'.$token)
            ->assertSessionHasErrors(['rule' => 'Naam: er staat al een element met deze naam op deze plek']);

        $this->assertSame(1, Element::query()->count());
    }

    public function test_the_route_refuses_a_reader(): void
    {
        $token = $this->deleteSubtree();

        $this->actingAs(User::factory()->create())->post('/undo/'.$token)->assertForbidden();

        $this->assertSame(0, Element::query()->count());
    }

    private function store(): UndoStore
    {
        return app(UndoStore::class);
    }

    private function deleteSubtree(): string
    {
        $token = $this->store()->remember('element_deletion', $this->payload, $this->administrator);

        DB::transaction(fn () => app(DeletesModelByModel::class)->delete($this->domain));
        DB::table('audit_entries')->delete();

        return $token;
    }

    /** @return array<string, mixed> */
    private function raw(Element $element): array
    {
        return $element->refresh()->getAttributes();
    }

    private function refusal(string $token, User $user): ?string
    {
        try {
            $this->store()->restore($token, $user);
        } catch (ValidationException $refusal) {
            return (string) $refusal->errors()['rule'][0];
        }

        return null;
    }
}
