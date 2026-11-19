<?php

namespace Tests\Feature\Exchange;

use App\Models\FinalisedYear;
use App\Models\User;

class CanvasExportTest extends ExchangeTestCase
{
    public function test_the_export_holds_everything_of_the_contract(): void
    {
        $this->richCanvas();

        $data = $this->exported();

        $this->assertSame('omc-studio-canvas', $data['format']);
        $this->assertSame(1, $data['format_version']);
        $this->assertSame('Acme Zorg', $data['organisation']['name']);
        $this->assertContains('Jansen', $data['organisation']['owners']);
        $this->assertSame([['name' => 'ERP', 'supplier' => 'Leverancier X']], $data['organisation']['applications']);
        $this->assertSame([2026, 2027], $data['organisation']['fiscal_years']);
        $this->assertCount(2, $data['cost_records']);
        $this->assertSame(1_250_000, $data['cost_records'][0]['yearly_figures'][0]['budget_cents']);
        $this->assertSame('Basis canvas', $data['canvas']['name']);
        $this->assertCount(2, $data['canvas']['statements']);
        $this->assertNull($data['canvas']['layout']);
        $this->assertSame([['year' => 2027, 'finalised_at' => $data['canvas']['finalised_years'][0]['finalised_at']]], $data['canvas']['finalised_years']);
        $this->assertSame([3333, 3333, 3334], array_column($data['canvas']['allocation_sets'][0]['lines'], 'basis_points'));
    }

    public function test_every_element_is_exported_including_archived_and_the_unknown_allocation_domain(): void
    {
        $this->richCanvas();

        $elements = $this->exported()['canvas']['elements'];
        $byName = array_column($elements, null, 'name');

        $this->assertCount(8, $elements);
        $this->assertSame(1, count(array_filter($elements, fn (array $element) => $element['is_unknown_allocation'])));
        $this->assertNotNull($byName['Oud']['archived_at']);
        $this->assertSame('common', $byName['Bestellen']['modality']);
    }

    public function test_the_file_holds_no_user_names_or_addresses(): void
    {
        $this->richCanvas();
        $user = User::factory()->administrator()->create(['name' => 'Zeldzame Gebruiker']);
        FinalisedYear::query()->update(['finalised_by' => $user->id]);

        $json = json_encode($this->exported());

        $this->assertStringNotContainsString('Zeldzame Gebruiker', (string) $json);
        $this->assertStringNotContainsString($user->email, (string) $json);
    }

    public function test_the_route_downloads_the_file_with_a_neutral_name_for_a_reader(): void
    {
        $this->richCanvas();
        $this->actingAs(User::factory()->create());

        $response = $this->get('/canvases/'.$this->canvas->id.'/export/json');

        $response->assertOk();
        $this->assertSame('attachment; filename=acme-zorg-basis-canvas-'.now()->format('Y-m-d').'.json', $response->headers->get('Content-Disposition'));
        $this->assertSame('Basis canvas', json_decode((string) $response->getContent(), true)['canvas']['name']);
    }

    public function test_a_guest_is_sent_away(): void
    {
        $this->richCanvas();

        $this->get('/canvases/'.$this->canvas->id.'/export/json')->assertRedirect();
    }
}
