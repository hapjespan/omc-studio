<?php

namespace Tests\Feature\Organisations;

use App\Models\Canvas;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_the_list_hides_archived_searches_by_name_and_reports_the_logo_flag(): void
    {
        Organisation::factory()->create(['name' => 'Noord']);
        Organisation::factory()->create(['name' => 'Zuid', 'archived_at' => now()]);
        Organisation::factory()->create(['name' => 'Oost', 'logo_path' => 'logos/x.png']);

        $this->admin()->get('/admin/organisations')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Organisations')
            ->where('count', 2)
            ->where('rows.0.name', 'Noord')
            ->where('rows.1.has_logo', 1));

        $this->admin()->get('/admin/organisations?archived=1')->assertInertia(fn (Assert $page) => $page->where('count', 3));
        $this->admin()->get('/admin/organisations?search=ost')->assertInertia(fn (Assert $page) => $page->where('count', 1)->where('rows.0.name', 'Oost'));
    }

    public function test_every_write_flashes_a_status_message(): void
    {
        $this->admin()->post('/admin/organisations', ['name' => 'Noord'])->assertSessionHas('status', 'Organisatie aangemaakt');
        $organisation = Organisation::firstOrFail();

        $this->admin()->patch('/admin/organisations/'.$organisation->id, ['name' => 'Zuid'])->assertSessionHas('status', 'Organisatie opgeslagen');
        $this->admin()->post('/admin/organisations/'.$organisation->id.'/archive')->assertSessionHas('status', 'Organisatie gearchiveerd');
        $this->admin()->post('/admin/organisations/'.$organisation->id.'/restore')->assertSessionHas('status', 'Organisatie teruggezet');
        $this->admin()->delete('/admin/organisations/'.$organisation->id)->assertSessionHas('status', 'Organisatie verwijderd');
    }

    public function test_a_logo_sent_as_an_array_is_a_validation_error_not_a_server_error(): void
    {
        $this->admin()->post('/admin/organisations', ['name' => 'Noord', 'logo' => ['x']])
            ->assertSessionHasErrors(['logo' => 'Logo: kies een PNG- of JPEG-afbeelding van maximaal 1 MB en 4000 bij 4000 pixels']);

        $this->assertSame(0, Organisation::count());
    }
}
