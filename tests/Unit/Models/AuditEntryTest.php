<?php

namespace Tests\Unit\Models;

use App\Enums\ActorType;
use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuditEntryTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $attributes */
    private function entry(array $attributes = []): AuditEntry
    {
        return AuditEntry::create($attributes + [
            'occurred_at' => now(),
            'actor_type' => ActorType::User,
            'action' => 'changed',
            'subject_type' => 'element',
            'subject_id' => 'abc',
            'subject_label' => 'Intake',
            'organisation_id' => 7,
            'canvas_id' => 9,
            'changes' => ['name' => ['Oud', 'Nieuw']],
        ]);
    }

    public function test_changes_round_trip_as_json(): void
    {
        $entry = $this->entry();

        $this->assertSame(['Oud', 'Nieuw'], $entry->fresh()->changes['name']);
        $this->assertSame(ActorType::User, $entry->fresh()->actor_type);
    }

    public function test_an_entry_survives_a_deleted_actor_with_actor_id_null(): void
    {
        $user = User::factory()->create();
        $entry = $this->entry(['actor_id' => $user->id]);

        $user->delete();

        $this->assertNull($entry->fresh()->actor_id);
        $this->assertSame(1, DB::table('audit_entries')->where('id', $entry->id)->count());
    }

    public function test_an_entry_about_unknown_organisation_and_canvas_is_stored(): void
    {
        $entry = $this->entry(['organisation_id' => null, 'canvas_id' => null, 'actor_type' => ActorType::System]);

        $this->assertNull($entry->fresh()->canvas_id);
        $this->assertNull($entry->fresh()->actor_id);
    }

    public function test_the_six_indexes_exist_and_no_foreign_key_points_at_organisation_or_canvas(): void
    {
        $indexes = collect(Schema::getIndexes('audit_entries'));

        foreach ([
            ['occurred_at'],
            ['canvas_id', 'occurred_at'],
            ['organisation_id', 'occurred_at'],
            ['actor_id'],
            ['subject_type', 'occurred_at'],
            ['subject_type', 'subject_id'],
        ] as $columns) {
            $this->assertTrue($indexes->contains(fn ($i) => $i['columns'] === $columns), implode(',', $columns));
        }

        $this->assertSame(['users'], collect(Schema::getForeignKeys('audit_entries'))->pluck('foreign_table')->all());
    }
}
