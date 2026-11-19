<?php

namespace Tests\Feature\Structure;

use App\Models\Modality;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class ModalityRulesTest extends StructureTestCase
{
    private function modality(string $key): int
    {
        return (int) Modality::where('key', $key)->value('id');
    }

    public function test_a_modality_and_rationale_are_set_on_an_activity(): void
    {
        $activity = $this->activity($this->domain());

        $this->tree()->update($activity, ['modality_id' => $this->modality('adaptive'), 'rationale' => 'Verandert vaak']);

        $this->assertSame($this->modality('adaptive'), $activity->fresh()->modality_id);
        $this->assertSame('Verandert vaak', $activity->fresh()->rationale);
    }

    public function test_a_list_area_element_refuses_a_modality_and_a_rationale(): void
    {
        $group = $this->domain('actors', 'Pakket');

        $this->assertSame(__('modality_entry.list_area'), $this->refusal(fn () => $this->tree()->update($group, ['modality_id' => $this->modality('common')])));
        $this->assertSame(__('modality_entry.list_area'), $this->refusal(fn () => $this->tree()->update($group, ['rationale' => 'Tekst'])));
        $this->assertNull($group->fresh()->modality_id);
    }

    public function test_a_rationale_without_a_modality_is_refused(): void
    {
        $activity = $this->activity($this->domain());

        $this->assertSame(__('modality_entry.rationale_needs_modality'), $this->refusal(fn () => $this->tree()->update($activity, ['rationale' => 'Tekst'])));
        $this->assertNull($activity->fresh()->rationale);
    }

    public function test_clearing_the_modality_needs_the_confirmation_and_then_clears_both(): void
    {
        $activity = $this->activity($this->domain());
        $this->tree()->update($activity, ['modality_id' => $this->modality('common'), 'rationale' => 'Standaard']);

        $this->assertSame(__('modality_entry.confirm_clear'), $this->refusal(fn () => $this->tree()->update($activity, ['modality_id' => null])));
        $this->assertSame('Standaard', $activity->fresh()->rationale);

        $this->tree()->update($activity, ['modality_id' => null, 'confirm_rationale_clear' => true]);

        $this->assertNull($activity->fresh()->modality_id);
        $this->assertNull($activity->fresh()->rationale);
    }

    public function test_clearing_a_modality_without_a_rationale_needs_no_confirmation(): void
    {
        $activity = $this->activity($this->domain());
        $this->tree()->update($activity, ['modality_id' => $this->modality('common')]);

        $this->tree()->update($activity, ['modality_id' => null]);

        $this->assertNull($activity->fresh()->modality_id);
    }

    public function test_an_unknown_modality_is_refused(): void
    {
        $activity = $this->activity($this->domain());

        $this->assertSame(__('modality_entry.unknown'), $this->refusal(fn () => $this->tree()->update($activity, ['modality_id' => 9999])));
    }

    public function test_the_route_sets_and_clears_by_key_and_no_route_edits_the_modalities(): void
    {
        $activity = $this->activity($this->domain());
        $url = "/canvases/{$this->canvas->id}/elements/{$activity->id}";
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->patch($url, ['modality' => 'specialized', 'rationale' => 'Eigen'])->assertRedirect();
        $this->assertSame($this->modality('specialized'), $activity->fresh()->modality_id);

        $this->actingAs($admin)->patch($url, ['modality' => 'none'])->assertSessionHasErrors('rule');
        $this->actingAs($admin)->patch($url, ['modality' => 'none', 'confirm_rationale_clear' => true])->assertRedirect();
        $this->assertNull($activity->fresh()->modality_id);

        $writes = collect(Route::getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'modalit') && array_diff($route->methods(), ['GET', 'HEAD']) !== []);
        $this->assertCount(0, $writes);
    }
}
