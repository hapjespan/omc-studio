<?php

namespace Tests\Feature\Refusals;

use App\Models\CostRecord;
use App\Models\Element;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

trait RefusalHelpers
{
    private const HEADER = 'code;naam;soort;leverancier;eigenaar;kostenplaats;applicatie;jaar;budget;prognose;run_pct;change_pct;fte';

    private const TABLES = [
        'cost_records',
        'yearly_figures',
        'allocation_sets',
        'allocation_lines',
        'fiscal_years',
        'owners',
        'applications',
        'finalised_years',
        'elements',
    ];

    private Organisation $organisation;

    private User $actor;

    protected function setUpRefusals(): void
    {
        $this->canvas->update(['name' => 'Bedrijfsmodel']);
        $this->organisation = $this->canvas->organisation()->firstOrFail();
        $this->actor = $this->admin();
    }

    private function signedIn(): static
    {
        return $this->actingAs($this->actor);
    }

    private function listPath(string $suffix): string
    {
        return '/admin/organisations/'.$this->organisation->id.'/'.$suffix;
    }

    private function figuresPath(CostRecord $record, int $year = 2026): string
    {
        return '/cost-records/'.$record->id.'/years/'.$year;
    }

    private function allocationPath(CostRecord $record, int $year = 2026): string
    {
        return '/canvases/'.$this->canvas->id.'/years/'.$year.'/cost-records/'.$record->id.'/allocation';
    }

    private function redistributionPath(Element $element): string
    {
        return '/canvases/'.$this->canvas->id.'/elements/'.$element->id.'/redistribution';
    }

    private function finalisePath(string $action, int $year = 2026): string
    {
        return '/canvases/'.$this->canvas->id.'/years/'.$year.'/'.$action;
    }

    private function finalisedRecord(): CostRecord
    {
        $record = $this->costRecord();
        $this->allocate($record, 2026, [$this->target('Toewijzing')->id => 10_000]);
        $this->finalise(2026);

        return $record;
    }

    private function allocatedTarget(): Element
    {
        $target = $this->target('Offreren');
        $this->allocate($this->costRecord(), 2026, [$target->id => 10_000]);

        return $target;
    }

    private function listElement(string $name): Element
    {
        return Element::factory()->create([
            'canvas_id' => $this->canvas->id,
            'area_id' => $this->area('actors')->id,
            'name' => $name,
        ]);
    }

    /**
     * @param  list<string>  $lines
     * @return TestResponse<Response>
     */
    private function commit(array $lines): TestResponse
    {
        return $this->send('import', $lines);
    }

    /** @param list<string> $lines */
    private function assertImportMessage(array $lines, string $message, int $index = 0): void
    {
        $before = $this->stored();

        $this->send('import/preview', $lines)->assertOk()
            ->assertJsonPath('lines.'.$index.'.status', 'error')
            ->assertJsonPath('lines.'.$index.'.messages.0', $message);

        $this->assertEquals($before, $this->stored());
    }

    /**
     * @param  list<string>  $lines
     * @return TestResponse<Response>
     */
    private function send(string $suffix, array $lines): TestResponse
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', implode("\n", [self::HEADER, ...$lines])."\n");

        return $this->signedIn()->post('/organisations/'.$this->organisation->id.'/cost-records/'.$suffix, ['file' => $file], ['Accept' => 'application/json']);
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        $rows = [];

        foreach (self::TABLES as $table) {
            $rows[$table] = DB::table($table)->orderBy('id')->get()->map(fn (object $row) => (array) $row)->all();
        }

        return $rows;
    }
}
