<?php

namespace Tests\Feature\Finance;

use App\Models\Application;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsAdministrator;
use Tests\TestCase;

class OrganisationListsTest extends TestCase
{
    use ActsAsAdministrator;
    use RefreshDatabase;

    /** @return array<string, array{string, class-string}> */
    public static function lists(): array
    {
        return ['owners' => ['owners', Owner::class], 'applications' => ['applications', Application::class]];
    }

    #[DataProvider('lists')]
    public function test_the_list_shows_only_this_organisations_entries(string $path, string $model): void
    {
        $organisation = Organisation::factory()->create();
        $model::factory()->create(['organisation_id' => $organisation->id, 'name' => 'Eigen']);
        $model::factory()->create(['name' => 'Vreemd']);

        $this->admin()->get("/admin/organisations/{$organisation->id}/{$path}")->assertInertia(
            fn (Assert $page) => $page->component('Admin/'.ucfirst($path), false)->where('count', 1)->where('rows.0.name', 'Eigen')->has('organisation')->has('organisations', 2)
        );
    }

    #[DataProvider('lists')]
    public function test_a_reader_gets_403_on_every_route(string $path, string $model): void
    {
        $organisation = Organisation::factory()->create();
        $entry = $model::factory()->create(['organisation_id' => $organisation->id]);
        $reader = $this->actingAs(User::factory()->create());
        $base = "/admin/organisations/{$organisation->id}/{$path}";

        $reader->get($base)->assertForbidden();
        $reader->post($base, ['name' => 'X'])->assertForbidden();
        $reader->patch("{$base}/{$entry->id}", ['name' => 'X'])->assertForbidden();
        $reader->delete("{$base}/{$entry->id}")->assertForbidden();
    }

    #[DataProvider('lists')]
    public function test_create_rename_and_delete_unused(string $path, string $model): void
    {
        $organisation = Organisation::factory()->create();
        $base = "/admin/organisations/{$organisation->id}/{$path}";

        $this->admin()->post($base, ['name' => ' Afdeling ICT '])->assertSessionHasNoErrors();
        $entry = $model::firstOrFail();
        $this->assertSame('Afdeling ICT', $entry->name);
        $this->assertSame($organisation->id, $entry->organisation_id);

        $this->admin()->patch("{$base}/{$entry->id}", ['name' => 'Afdeling HR'])->assertSessionHasNoErrors();
        $this->assertSame('Afdeling HR', $entry->fresh()->name);

        $this->admin()->delete("{$base}/{$entry->id}")->assertSessionHasNoErrors();
        $this->assertSame(0, $model::count());
    }

    #[DataProvider('lists')]
    public function test_a_duplicate_name_is_refused_ignoring_case_and_spaces_but_allowed_elsewhere(string $path, string $model): void
    {
        $organisation = Organisation::factory()->create();
        $model::factory()->create(['organisation_id' => $organisation->id, 'name' => 'Afdeling ICT']);
        $base = "/admin/organisations/{$organisation->id}/{$path}";

        $this->admin()->post($base, ['name' => '  afdeling ict '])->assertSessionHasErrors(['name' => 'Naam: deze naam staat al in de lijst']);
        $other = Organisation::factory()->create();
        $this->admin()->post("/admin/organisations/{$other->id}/{$path}", ['name' => 'Afdeling ICT'])->assertSessionHasNoErrors();
    }

    #[DataProvider('lists')]
    public function test_renaming_to_itself_is_allowed_and_to_a_sibling_is_not(string $path, string $model): void
    {
        $organisation = Organisation::factory()->create();
        $first = $model::factory()->create(['organisation_id' => $organisation->id, 'name' => 'Een']);
        $model::factory()->create(['organisation_id' => $organisation->id, 'name' => 'Twee']);
        $base = "/admin/organisations/{$organisation->id}/{$path}/{$first->id}";

        $this->admin()->patch($base, ['name' => 'EEN'])->assertSessionHasNoErrors();
        $this->admin()->patch($base, ['name' => 'twee'])->assertSessionHasErrors('name');
    }

    public function test_an_application_holds_an_optional_supplier_of_150_characters(): void
    {
        $organisation = Organisation::factory()->create();
        $base = "/admin/organisations/{$organisation->id}/applications";

        $this->admin()->post($base, ['name' => 'Planningssysteem', 'supplier' => 'Leverancier X'])->assertSessionHasNoErrors();
        $this->assertSame('Leverancier X', Application::firstOrFail()->supplier);
        $this->admin()->post($base, ['name' => 'Zonder'])->assertSessionHasNoErrors();
        $this->admin()->post($base, ['name' => 'Lang', 'supplier' => str_repeat('a', 151)])->assertSessionHasErrors('supplier');
    }

    #[DataProvider('lists')]
    public function test_an_archived_organisation_is_read_only(string $path, string $model): void
    {
        $organisation = Organisation::factory()->create(['archived_at' => now()]);
        $entry = $model::factory()->create(['organisation_id' => $organisation->id]);
        $base = "/admin/organisations/{$organisation->id}/{$path}";

        $this->admin()->post($base, ['name' => 'Nieuw'])->assertSessionHasErrors('rule');
        $this->admin()->patch("{$base}/{$entry->id}", ['name' => 'Nieuw'])->assertSessionHasErrors('rule');
        $this->admin()->delete("{$base}/{$entry->id}")->assertSessionHasErrors('rule');
        $this->admin()->get($base)->assertOk();
    }

    #[DataProvider('lists')]
    public function test_an_entry_of_another_organisation_is_not_found(string $path, string $model): void
    {
        $organisation = Organisation::factory()->create();
        $entry = $model::factory()->create();

        $this->admin()->patch("/admin/organisations/{$organisation->id}/{$path}/{$entry->id}", ['name' => 'X'])->assertNotFound();
    }

    #[DataProvider('lists')]
    public function test_the_admin_entry_lands_on_the_first_organisation_and_a_reader_gets_403(string $path, string $model): void
    {
        $this->admin()->get("/admin/{$path}")->assertRedirect('/admin/organisations');
        $first = Organisation::factory()->create(['name' => 'Aaa']);
        Organisation::factory()->create(['name' => 'Zzz']);

        $this->admin()->get("/admin/{$path}")->assertRedirect("/admin/organisations/{$first->id}/{$path}");
        $this->actingAs(User::factory()->create())->get("/admin/{$path}")->assertForbidden();
    }
}
