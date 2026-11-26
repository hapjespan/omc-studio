<?php

namespace Tests\Feature\Allocation;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\Element;
use App\Services\Canvas\UnknownAllocationDomain;
use App\Services\Finance\FigureService;
use App\Services\Structure\ElementArchive;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

class RedistributionApplyTest extends AllocationTestCase
{
    /**
     * @param  array<array-key, array<string, string>>  $choices
     * @return TestResponse<Response>
     */
    private function apply(Element $element, array $choices): TestResponse
    {
        return $this->actingAs($this->admin())
            ->postJson("/canvases/{$this->canvas->id}/elements/{$element->id}/redistribution", ['choices' => $choices]);
    }

    /** @return list<array{string, int, int}> */
    private function lines(AllocationSet $set): array
    {
        return AllocationLine::where('allocation_set_id', $set->id)->orderBy('position')->get()
            ->map(fn (AllocationLine $line) => [(string) $line->element_id, $line->basis_points, $line->position])->all();
    }

    public function test_move_adds_the_affected_percentages_to_a_line_the_target_already_has(): void
    {
        $domain = $this->domain();
        $first = $this->activity($domain, 'A1');
        $second = $this->activity($domain, 'A2');
        $target = $this->target('Doel');
        $set = $this->allocate($this->costRecord('Licentie', 2025), 2025, [$first->id => 3000, $second->id => 2000, $target->id => 5000]);

        $this->apply($domain, ['2025' => ['mode' => 'move', 'target' => (string) $target->id]])->assertRedirect();

        $this->assertSame([[(string) $target->id, 10000, 0]], $this->lines($set));
        $this->assertNotNull($domain->refresh()->archived_at);
        $this->assertNotNull($first->refresh()->archived_at);
        $this->assertNotNull($second->refresh()->archived_at);
    }

    public function test_move_without_an_existing_target_line_keeps_the_place_of_the_first_affected_line(): void
    {
        $domain = $this->domain();
        $first = $this->activity($domain, 'A1');
        $second = $this->activity($domain, 'A2');
        $other = $this->target('Elders');
        $target = $this->target('Doel');
        $set = $this->allocate($this->costRecord('Licentie', 2025), 2025, [$other->id => 5000, $first->id => 3000, $second->id => 2000]);

        $this->apply($domain, ['2025' => ['mode' => 'move', 'target' => (string) $target->id]])->assertRedirect();

        $this->assertSame([[(string) $other->id, 5000, 0], [(string) $target->id, 5000, 1]], $this->lines($set));
    }

    public function test_the_target_takes_the_whole_set_when_every_line_was_affected(): void
    {
        $domain = $this->domain();
        $first = $this->activity($domain, 'A1');
        $target = $this->target('Doel');
        $set = $this->allocate($this->costRecord('Licentie', 2025), 2025, [$first->id => 10000]);

        $this->apply($domain, ['2025' => ['mode' => 'move', 'target' => (string) $target->id]])->assertRedirect();

        $this->assertSame([[(string) $target->id, 10000, 0]], $this->lines($set));
    }

    public function test_unknown_removes_the_whole_set_and_never_a_remainder(): void
    {
        $domain = $this->domain();
        $first = $this->activity($domain, 'A1');
        $other = $this->target('Elders');
        $set = $this->allocate($this->costRecord('Licentie', 2025), 2025, [$first->id => 3000, $other->id => 7000]);

        $this->apply($domain, ['2025' => ['mode' => 'unknown']])->assertRedirect();

        $this->assertDatabaseMissing('allocation_sets', ['id' => $set->id]);
        $this->assertSame(0, AllocationLine::count());
    }

    public function test_every_year_follows_its_own_choice(): void
    {
        $domain = $this->domain();
        $first = $this->activity($domain, 'A1');
        $target = $this->target('Doel');
        $record = $this->costRecord('Licentie', 2025);
        $moved = $this->allocate($record, 2025, [$first->id => 4000, $target->id => 6000]);
        $removed = $this->allocate($record, 2026, [$first->id => 10000]);

        $this->apply($domain, [
            '2025' => ['mode' => 'move', 'target' => (string) $target->id],
            '2026' => ['mode' => 'unknown'],
        ])->assertRedirect();

        $this->assertSame([[(string) $target->id, 10000, 0]], $this->lines($moved));
        $this->assertDatabaseMissing('allocation_sets', ['id' => $removed->id]);
    }

    public function test_a_target_inside_the_removed_subtree_is_refused(): void
    {
        $domain = $this->domain();
        $first = $this->activity($domain, 'A1');
        $this->allocate($this->costRecord('Licentie', 2025), 2025, [$first->id => 10000]);

        foreach ([$first, $domain] as $target) {
            $this->apply($domain, ['2025' => ['mode' => 'move', 'target' => (string) $target->id]])
                ->assertUnprocessable()->assertJsonValidationErrors(['rule'])
                ->assertJsonPath('errors.rule.0', 'Kies een doel buiten het element dat je verwijdert');
        }

        $this->assertNull($domain->refresh()->archived_at);
    }

    public function test_a_target_that_cannot_carry_costs_is_refused_by_name(): void
    {
        $domain = $this->domain();
        $first = $this->activity($domain, 'A1');
        $this->allocate($this->costRecord('Licentie', 2025), 2025, [$first->id => 10000]);

        $archived = $this->target('Weg');
        app(ElementArchive::class)->archive($archived);
        $elsewhere = Canvas::factory()->create();

        $targets = [
            'Weg' => $archived,
            'Onbekende allocatie' => UnknownAllocationDomain::ensure($this->canvas),
            'Klant' => $this->domain('actors', 'Klant'),
            'Elders' => $this->domain('primary_activities', 'Elders', $elsewhere),
        ];

        foreach ($targets as $name => $target) {
            $this->apply($domain, ['2025' => ['mode' => 'move', 'target' => (string) $target?->id]])
                ->assertUnprocessable()->assertJsonPath('errors.rule.0', "Doel $name kan geen kosten dragen");
        }

        $this->assertNull($domain->refresh()->archived_at);
        $this->assertSame(10000, (int) AllocationLine::where('element_id', $first->id)->value('basis_points'));
    }

    public function test_a_year_finalised_in_this_canvas_refuses_the_whole_apply(): void
    {
        $domain = $this->domain();
        $first = $this->activity($domain, 'A1');
        $target = $this->target('Doel');
        $record = $this->costRecord('Licentie', 2025);
        $open = $this->allocate($record, 2025, [$first->id => 10000]);
        $this->allocate($record, 2026, [$first->id => 10000]);
        $this->finalise(2026);

        $this->apply($domain, [
            '2025' => ['mode' => 'move', 'target' => (string) $target->id],
            '2026' => ['mode' => 'unknown'],
        ])->assertUnprocessable()->assertJsonPath('errors.rule.0', '2026 is definitief. Heropen het jaar eerst');

        $this->assertSame([[(string) $first->id, 10000, 0]], $this->lines($open));
        $this->assertNull($domain->refresh()->archived_at);
    }

    public function test_an_affected_year_without_a_choice_refuses_the_whole_apply(): void
    {
        $domain = $this->domain();
        $first = $this->activity($domain, 'A1');
        $target = $this->target('Doel');
        $record = $this->costRecord('Licentie', 2025);
        $open = $this->allocate($record, 2025, [$first->id => 10000]);
        $this->allocate($record, 2026, [$first->id => 10000]);

        $this->apply($domain, ['2025' => ['mode' => 'move', 'target' => (string) $target->id]])
            ->assertUnprocessable()->assertJsonPath('errors.rule.0', '2026: kies wat er met de verdeling gebeurt');

        $this->assertSame([[(string) $first->id, 10000, 0]], $this->lines($open));
        $this->assertNull($domain->refresh()->archived_at);
    }

    public function test_an_element_without_lines_is_simply_archived(): void
    {
        $element = $this->target();

        $this->apply($element, [])->assertRedirect();

        $this->assertNotNull($element->refresh()->archived_at);
    }

    public function test_the_audit_log_holds_an_entry_per_changed_set_and_per_archived_element(): void
    {
        $domain = $this->domain();
        $first = $this->activity($domain, 'A1');
        $target = $this->target('Doel');
        $record = $this->costRecord('Licentie', 2025);
        $moved = $this->allocate($record, 2025, [$first->id => 4000, $target->id => 6000]);
        $removed = $this->allocate($record, 2026, [$first->id => 10000]);
        AuditEntry::query()->delete();

        $this->apply($domain, [
            '2025' => ['mode' => 'move', 'target' => (string) $target->id],
            '2026' => ['mode' => 'unknown'],
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_entries', ['subject_type' => 'allocation_set', 'subject_id' => (string) $moved->id, 'action' => 'redistributed']);
        $this->assertDatabaseHas('audit_entries', ['subject_type' => 'allocation_set', 'subject_id' => (string) $removed->id, 'action' => 'redistributed']);

        foreach ([$domain, $first] as $element) {
            $this->assertDatabaseHas('audit_entries', ['subject_type' => 'element', 'subject_id' => (string) $element->id, 'action' => 'changed']);
        }

        $changes = AuditEntry::where('subject_id', (string) $moved->id)->where('action', 'redistributed')->value('changes');
        $this->assertSame([['target' => 'A1', 'basis_points' => 4000], ['target' => 'Doel', 'basis_points' => 6000]], $changes['lines'][0]);
        $this->assertSame([['target' => 'Doel', 'basis_points' => 10000]], $changes['lines'][1]);
    }

    public function test_the_closing_check_passes_afterwards_and_archived_elements_hold_no_lines(): void
    {
        UnknownAllocationDomain::ensure($this->canvas);
        $domain = $this->domain();
        $first = $this->activity($domain, 'A1');
        $target = $this->target('Doel');
        $this->allocate($this->costRecord('Licentie', 2025), 2025, [$first->id => 4000, $target->id => 6000]);
        $this->allocate($this->costRecord('Hosting', 2025), 2025, [$first->id => 10000]);

        $this->apply($domain, ['2025' => ['mode' => 'move', 'target' => (string) $target->id]])->assertRedirect();

        $this->assertNotNull(app(FigureService::class)->figures($this->canvas, 2025));
        $this->assertSame(0, AllocationLine::whereIn('element_id', [$domain->id, $first->id])->count());
    }

    public function test_the_route_demands_an_administrator(): void
    {
        $element = $this->target();

        $this->postJson("/canvases/{$this->canvas->id}/elements/{$element->id}/redistribution", ['choices' => []])->assertUnauthorized();
    }
}
