<?php

namespace Tests\Feature\Organisations;

use App\Models\Canvas;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganisationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->administrator()->create());
    }

    public function test_an_administrator_creates_and_renames(): void
    {
        $this->admin()->post('/admin/organisations', ['name' => 'Noord'])->assertRedirect();
        $organisation = Organisation::firstOrFail();

        $this->admin()->patch('/admin/organisations/'.$organisation->id, ['name' => 'Zuid'])->assertRedirect();

        $this->assertSame('Zuid', $organisation->fresh()->name);
    }

    public function test_a_duplicate_name_is_a_validation_error_on_name(): void
    {
        Organisation::factory()->create(['name' => 'Noord']);

        $this->admin()->post('/admin/organisations', ['name' => 'noord '])->assertSessionHasErrors(['name' => 'Naam: er bestaat al een organisatie met deze naam']);
    }

    public function test_deleting_with_a_canvas_is_a_rule_error_and_archive_restore_work(): void
    {
        $organisation = Organisation::factory()->create();
        Canvas::factory()->create(['organisation_id' => $organisation->id]);

        $this->admin()->delete('/admin/organisations/'.$organisation->id)->assertSessionHasErrors('rule');
        $this->admin()->post('/admin/organisations/'.$organisation->id.'/archive')->assertRedirect();
        $this->assertNotNull($organisation->fresh()->archived_at);
        $this->admin()->post('/admin/organisations/'.$organisation->id.'/restore')->assertRedirect();
        $this->assertNull($organisation->fresh()->archived_at);
    }

    public function test_an_empty_organisation_is_deleted(): void
    {
        $organisation = Organisation::factory()->create();

        $this->admin()->delete('/admin/organisations/'.$organisation->id)->assertRedirect();

        $this->assertSame(0, Organisation::count());
    }

    public function test_a_reader_is_refused(): void
    {
        $this->actingAs(User::factory()->create())->post('/admin/organisations', ['name' => 'X'])->assertForbidden();
        $this->assertSame(0, Organisation::count());
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->post('/admin/organisations', ['name' => 'X'])->assertRedirect('/sign-in');
    }
}
