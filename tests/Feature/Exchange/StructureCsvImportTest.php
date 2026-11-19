<?php

namespace Tests\Feature\Exchange;

use App\Enums\ElementKind;
use App\Models\Element;
use App\Models\Modality;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;

class StructureCsvImportTest extends ExchangeTestCase
{
    private const HEADER = 'area;domein;cluster;activiteit;omschrijving;modaliteit';

    /**
     * @param  list<string>  $lines
     * @return TestResponse<JsonResponse>
     */
    private function preview(array $lines): TestResponse
    {
        return $this->upload(self::HEADER, $lines, '/canvases/'.$this->canvas->id.'/import/structure/preview');
    }

    /**
     * @param  list<string>  $lines
     * @return TestResponse<JsonResponse>
     */
    private function commit(array $lines): TestResponse
    {
        return $this->upload(self::HEADER, $lines, '/canvases/'.$this->canvas->id.'/import/structure');
    }

    /**
     * @param  TestResponse<JsonResponse>  $response
     * @return list<string>
     */
    private function messages(TestResponse $response, int $index = 0): array
    {
        return $response->json("lines.$index.messages");
    }

    private function find(string $name): Element
    {
        return Element::query()->where('canvas_id', $this->canvas->id)->where('name', $name)->sole();
    }

    private function modality(string $key): int
    {
        return (int) Modality::query()->where('key', $key)->firstOrFail()->id;
    }

    public function test_a_line_creates_the_missing_elements_of_its_path_and_reuses_the_existing_ones(): void
    {
        $this->commit([
            'Primaire activiteiten;Inkoop;;Offreren;Offertes maken;common',
            'primary_activities;Verkoop;Acquisitie;Bellen;;A',
        ])->assertOk()->assertJsonPath('totals', ['new' => 1, 'updated' => 1, 'unchanged' => 0, 'errors' => 0]);

        $this->activity->refresh();
        $this->assertSame('Offertes maken', $this->activity->description);
        $this->assertSame($this->modality('common'), (int) $this->activity->modality_id);

        $cluster = $this->find('Acquisitie');
        $bellen = $this->find('Bellen');
        $this->assertSame($this->find('Verkoop')->id, $cluster->parent_id);
        $this->assertSame(ElementKind::Cluster, $cluster->kind);
        $this->assertSame($cluster->id, $bellen->parent_id);
        $this->assertSame(ElementKind::Activity, $bellen->kind);
        $this->assertSame($this->modality('adaptive'), (int) $bellen->modality_id);
    }

    public function test_an_existing_element_is_reused_by_name_regardless_of_case_and_shared_parents_are_created_once(): void
    {
        $this->commit([
            'Primaire activiteiten;INKOOP;;Inkopen;;',
            'Primaire activiteiten;Verkoop;;Bellen;;',
            'Primaire activiteiten;verkoop;;Mailen;;',
            'Primaire activiteiten;Inkoop;;Offreren;;',
        ])->assertOk()->assertJsonPath('totals', ['new' => 3, 'updated' => 0, 'unchanged' => 1, 'errors' => 0]);

        $this->assertSame(1, Element::query()->where('canvas_id', $this->canvas->id)->where('name_normalised', 'verkoop')->count());
        $this->assertSame($this->domain->id, $this->find('Inkopen')->parent_id);
        $this->assertSame($this->find('Verkoop')->id, $this->find('Mailen')->parent_id);
    }

    public function test_a_description_or_modality_that_does_not_change_reads_unchanged_on_a_re_import(): void
    {
        $lines = [
            'Primaire activiteiten;Inkoop;;Offreren;Offertes maken;C',
            'Primaire activiteiten;Inkoop;Werving;Bellen;Bellen maar;distinct',
        ];

        $this->commit($lines)->assertOk()->assertJsonPath('totals.new', 1)->assertJsonPath('totals.updated', 1);

        $this->preview($lines)->assertOk()
            ->assertJsonPath('lines.0.status', 'unchanged')
            ->assertJsonPath('lines.1.status', 'unchanged')
            ->assertJsonPath('totals', ['new' => 0, 'updated' => 0, 'unchanged' => 2, 'errors' => 0]);
    }

    public function test_each_faulty_line_is_reported_on_its_own_line(): void
    {
        $this->element('primary_activities', ElementKind::Domain, 'Oud')->update(['archived_at' => now()]);

        $response = $this->preview([
            'Onbekend;Inkoop;;Offreren;;',
            'Koers;Inkoop;;Offreren;;',
            'Primaire activiteiten;;;Offreren;;',
            'Actoren;Klanten;Groep;Piet;;',
            'Actoren;Klanten;;Piet;;C',
            'Primaire activiteiten;Inkoop;;Offreren;;Z',
            'Primaire activiteiten;Inkoop > Verkoop;;Offreren;;',
            'Primaire activiteiten;'.str_repeat('a', 151).';;Offreren;;',
            'Primaire activiteiten;Inkoop;;Offreren;'.str_repeat('b', 2001).';',
            'Primaire activiteiten;Oud;;Offreren;;',
            'Ondersteunende activiteiten;Onbekende allocatie;;Offreren;;',
        ])->assertOk();

        $this->assertSame(["Regel 2, area: 'Onbekend' is geen area. Gebruik de naam of de sleutel van een area"], $this->messages($response, 0));
        $this->assertSame(['Regel 3, area: in Koers staan geen elementen'], $this->messages($response, 1));
        $this->assertSame(['Regel 4, domein: domein ontbreekt. Vul een domein in'], $this->messages($response, 2));
        $this->assertSame(['Regel 5, cluster: in deze area bestaan geen clusters'], $this->messages($response, 3));
        $this->assertSame(['Regel 6, modaliteit: in deze area bestaat geen modaliteit'], $this->messages($response, 4));
        $this->assertStringStartsWith("Regel 7, modaliteit: 'Z' is geen modaliteit", $this->messages($response, 5)[0]);
        $this->assertSame(['Regel 8, domein: het teken > is niet toegestaan'], $this->messages($response, 6));
        $this->assertSame(['Regel 9, domein: gebruik hoogstens 150 tekens'], $this->messages($response, 7));
        $this->assertSame(['Regel 10, omschrijving: gebruik hoogstens 2000 tekens'], $this->messages($response, 8));
        $this->assertStringStartsWith("Regel 11, domein: 'Oud' is gearchiveerd", $this->messages($response, 9)[0]);
        $this->assertStringStartsWith("Regel 12, domein: 'Onbekende allocatie' wordt", $this->messages($response, 10)[0]);
        $response->assertJsonPath('totals.errors', 11)->assertJsonPath('lines.0.status', 'error');
    }

    public function test_a_commit_with_one_sound_and_one_faulty_line_writes_nothing(): void
    {
        $before = Element::query()->count();

        $this->commit([
            'Primaire activiteiten;Verkoop;;Bellen;;',
            'Onbekend;Inkoop;;Offreren;;',
        ])->assertStatus(422)->assertJsonPath('errors.file.0', 'Importeren is niet gelukt; het bestand bevat fouten. Er is niets gewijzigd');

        $this->assertSame($before, Element::query()->count());
    }

    public function test_a_file_of_forty_lines_with_shared_parents_is_imported_in_one_step(): void
    {
        $lines = [];

        foreach (range(1, 4) as $domain) {
            foreach (range(1, 10) as $activity) {
                $lines[] = "Primaire activiteiten;Domein $domain;;Activiteit $domain.$activity;;";
            }
        }

        $before = Element::query()->count();

        $this->commit($lines)->assertOk()->assertJsonPath('totals', ['new' => 40, 'updated' => 0, 'unchanged' => 0, 'errors' => 0]);

        $this->assertSame($before + 44, Element::query()->count());
        $this->preview($lines)->assertOk()->assertJsonPath('totals.unchanged', 40);
    }

    public function test_the_positions_of_created_elements_follow_the_order_of_the_file(): void
    {
        $this->commit([
            'Primaire activiteiten;Zeta;;;;',
            'Primaire activiteiten;Alfa;;;;',
            'Primaire activiteiten;Midden;;;;',
        ])->assertOk();

        $this->assertLessThan($this->find('Alfa')->position, $this->find('Zeta')->position);
        $this->assertLessThan($this->find('Midden')->position, $this->find('Alfa')->position);
    }

    public function test_a_reader_is_refused_on_both_routes(): void
    {
        $before = Element::query()->count();
        $this->actingAs(User::factory()->create());

        $this->preview(['Primaire activiteiten;Verkoop;;Bellen;;'])->assertForbidden();
        $this->commit(['Primaire activiteiten;Verkoop;;Bellen;;'])->assertForbidden();

        $this->assertSame($before, Element::query()->count());
    }
}
