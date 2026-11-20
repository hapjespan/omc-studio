<?php

namespace Tests\Feature\Layout;

use App\Models\Element;
use App\Models\User;
use App\Services\Canvas\UnknownAllocationDomain;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\Structure\StructureTestCase;

class DrawingMoveTargetsTest extends StructureTestCase
{
    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
    }

    public function test_a_cluster_moves_to_a_domain_of_another_activity_area(): void
    {
        $cluster = $this->cluster($this->domain('primary_activities', 'Inkoop'));
        $target = $this->domain('supporting_activities', 'Beheer');

        $this->move($cluster, $target)->assertOk();
        $this->assertSame($target->id, $cluster->fresh()->parent_id);
    }

    public function test_an_activity_moves_to_a_cluster_or_domain_other_than_its_parent_also_within_its_domain(): void
    {
        $domain = $this->domain('primary_activities', 'Inkoop');
        $cluster = $this->cluster($domain);
        $activity = $this->activity($domain);

        $this->move($activity, $cluster)->assertOk();
        $this->assertSame($cluster->id, $activity->fresh()->parent_id);
        $this->move($activity->fresh(), $domain)->assertOk();
        $this->assertSame($domain->id, $activity->fresh()->parent_id);
    }

    public function test_an_item_moves_to_another_group_of_a_list_area(): void
    {
        $item = $this->activity($this->domain('actors', 'Klanten'));
        $other = $this->domain('actors', 'Partners');

        $this->move($item, $other)->assertOk();
        $this->assertSame($other->id, $item->fresh()->parent_id);
    }

    public function test_a_domain_is_never_moved_with_shift(): void
    {
        $domain = $this->domain('primary_activities', 'Inkoop');
        $target = $this->domain('supporting_activities', 'Beheer');

        $this->move($domain, $target)->assertUnprocessable()->assertJsonValidationErrors('rule')->assertJsonPath('errors.rule.0', 'Een domein verplaats je met Hangt onder in Invoeren');
    }

    public function test_the_current_parent_is_refused(): void
    {
        $domain = $this->domain('primary_activities', 'Inkoop');
        $activity = $this->activity($domain);

        $this->move($activity, $domain)->assertUnprocessable()->assertJsonPath('errors.rule.0', 'Dit blok hangt hier al onder');
    }

    public function test_another_kind_of_area_is_refused_with_the_invoeren_message(): void
    {
        $activity = $this->activity($this->domain('primary_activities', 'Inkoop'));
        $group = $this->domain('actors', 'Klanten');

        $this->move($activity, $group)->assertUnprocessable()->assertJsonPath('errors.rule.0', 'Verplaatsen kan alleen naar een area van dezelfde soort');
    }

    public function test_the_unknown_allocation_domain_is_refused_with_the_invoeren_message(): void
    {
        $activity = $this->activity($this->domain('primary_activities', 'Inkoop'));
        $unknown = UnknownAllocationDomain::ensure($this->canvas);

        $this->move($activity, $unknown)->assertUnprocessable()->assertJsonPath('errors.rule.0', 'Onbekende allocatie wordt door het systeem beheerd');
    }

    public function test_a_name_already_used_under_the_target_is_refused_with_the_invoeren_message(): void
    {
        $source = $this->domain('primary_activities', 'Inkoop');
        $target = $this->domain('primary_activities', 'Verkoop');
        $activity = $this->activity($source, 'Offreren');
        $this->activity($target, 'Offreren');

        $this->move($activity, $target)->assertUnprocessable()->assertJsonPath('errors.rule.0', 'Naam: er staat al een element met deze naam op deze plek');
    }

    public function test_an_activity_cannot_hang_under_an_activity_and_a_cluster_not_under_a_cluster(): void
    {
        $domain = $this->domain('primary_activities', 'Inkoop');
        $other = $this->domain('primary_activities', 'Verkoop');
        $activity = $this->activity($domain);
        $leaf = $this->activity($other, 'Eindje');
        $cluster = $this->cluster($domain);
        $otherCluster = $this->cluster($other, 'Elders');

        $this->move($activity, $leaf)->assertUnprocessable();
        $this->move($cluster, $otherCluster)->assertUnprocessable();
    }

    public function test_a_cluster_cannot_move_into_a_list_area(): void
    {
        $cluster = $this->cluster($this->domain('primary_activities', 'Inkoop'));
        $group = $this->domain('actors', 'Klanten');

        $this->move($cluster, $group)->assertUnprocessable();
    }

    /** @return TestResponse<Response> */
    private function move(Element $element, Element $target): TestResponse
    {
        $this->canvas->refresh();

        return $this->actingAs($this->administrator)->postJson("/canvases/{$this->canvas->id}/drawing/moves", [
            'element_id' => (string) $element->id,
            'target_id' => (string) $target->id,
            'structure_version' => (int) $this->canvas->structure_version,
            'layout_version' => (int) $this->canvas->layout_version,
            'layout' => null,
        ]);
    }
}
