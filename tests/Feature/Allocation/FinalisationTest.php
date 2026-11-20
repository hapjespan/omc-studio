<?php

namespace Tests\Feature\Allocation;

use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FinalisedYear;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;

class FinalisationTest extends AllocationTestCase
{
    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = $this->admin();
    }

    /**
     * @param  array<string, bool>  $data
     * @return TestResponse<Response>
     */
    private function send(string $action, array $data = []): TestResponse
    {
        return $this->actingAs($this->administrator)->postJson("/canvases/{$this->canvas->id}/years/2026/{$action}", $data);
    }

    private function allocated(string $name = 'Toegewezen'): CostRecord
    {
        $record = $this->costRecord($name, 2026);
        $this->allocate($record, 2026, [$this->target($name.' doel')->id => 10000]);

        return $record;
    }

    public function test_an_archived_cost_record_without_a_set_refuses_finalising_and_is_named(): void
    {
        $this->costRecord('Licentie', 2026)->update(['archived_at' => now()]);

        $this->send('finalise')->assertUnprocessable()
            ->assertJsonPath('errors.rule.0', 'Nog 1 kostenpost in onbekende allocatie: Licentie');
        $this->assertSame(0, FinalisedYear::count());
    }

    public function test_the_refusal_counts_and_names_every_cost_record_without_a_set(): void
    {
        $this->allocated();
        $this->costRecord('Hosting', 2026);
        $this->costRecord('Licentie', 2026);

        $this->send('finalise')->assertUnprocessable()
            ->assertJsonPath('errors.rule.0', 'Nog 2 kostenposten in onbekende allocatie: Hosting, Licentie');
    }

    public function test_a_failing_closing_check_refuses_finalising(): void
    {
        $record = $this->costRecord('Licentie', 2026);
        $element = $this->target();
        $this->allocate($record, 2026, [$element->id => 10000]);
        Element::whereKey($element->id)->update(['archived_at' => now()]);

        $this->send('finalise')->assertUnprocessable()
            ->assertJsonPath('errors.rule.0', 'De cijfers sluiten niet; definitief maken kan niet');
        $this->assertSame(0, FinalisedYear::count());
    }

    public function test_finalising_records_who_and_when(): void
    {
        $this->allocated();

        $this->send('finalise')->assertRedirect();

        $row = FinalisedYear::sole();
        $this->assertSame($this->canvas->id, $row->canvas_id);
        $this->assertSame($this->administrator->id, $row->finalised_by);
        $this->assertEqualsWithDelta(now()->timestamp, $row->finalised_at->timestamp, 5);
        $this->assertDatabaseHas('audit_entries', ['subject_type' => 'finalised_year', 'subject_id' => (string) $row->id, 'action' => 'finalised', 'actor_id' => $this->administrator->id]);
    }

    public function test_a_year_that_is_already_finalised_is_refused(): void
    {
        $this->allocated();
        $this->finalise(2026);

        $this->send('finalise')->assertUnprocessable()->assertJsonPath('errors.rule.0', '2026 is al definitief');
        $this->assertSame(1, FinalisedYear::count());
    }

    public function test_reopening_without_confirmation_is_refused(): void
    {
        $this->finalise(2026);

        $this->send('reopen')->assertUnprocessable()->assertJsonValidationErrors('confirm');
        $this->assertSame(1, FinalisedYear::count());
    }

    public function test_a_confirmed_reopen_frees_the_year_and_is_audited(): void
    {
        $row = $this->finalise(2026);

        $this->send('reopen', ['confirm' => true])->assertRedirect();

        $this->assertSame(0, FinalisedYear::count());
        $this->assertDatabaseHas('audit_entries', ['subject_type' => 'finalised_year', 'subject_id' => (string) $row->id, 'action' => 'reopened', 'actor_id' => $this->administrator->id]);
    }

    public function test_an_archived_canvas_refuses_finalising_and_reopening(): void
    {
        $this->allocated();
        $this->canvas->update(['archived_at' => now()]);

        $this->send('finalise')->assertUnprocessable();
        $this->send('reopen', ['confirm' => true])->assertUnprocessable();
    }

    public function test_only_an_administrator_may_finalise_or_reopen(): void
    {
        $this->postJson("/canvases/{$this->canvas->id}/years/2026/finalise")->assertUnauthorized();
        $this->postJson("/canvases/{$this->canvas->id}/years/2026/reopen")->assertUnauthorized();
    }
}
