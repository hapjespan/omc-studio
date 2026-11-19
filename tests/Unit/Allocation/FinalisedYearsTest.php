<?php

namespace Tests\Unit\Allocation;

use App\Models\Canvas;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Services\Allocation\FinalisedYears;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinalisedYearsTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private FiscalYear $year;

    private FinalisedYears $years;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->year = FiscalYear::factory()->for($this->organisation)->create(['year' => 2026]);
        $this->years = new FinalisedYears;
    }

    private function canvas(string $name): Canvas
    {
        return Canvas::factory()->for($this->organisation)->create(['name' => $name]);
    }

    private function refusal(callable $call): string
    {
        try {
            $call();
        } catch (ValidationException $exception) {
            return $exception->errors()['rule'][0];
        }

        $this->fail('the guard accepted a finalised year');
    }

    public function test_a_year_is_finalised_in_the_canvas_that_finalised_it_only(): void
    {
        $finalised = $this->canvas('Canvas A');
        $other = $this->canvas('Canvas B');
        FinalisedYear::factory()->for($finalised)->for($this->year, 'fiscalYear')->create();

        $this->assertTrue($this->years->finalisedInCanvas($finalised, $this->year));
        $this->assertFalse($this->years->finalisedInCanvas($other, $this->year));
    }

    public function test_an_open_year_names_no_canvas_in_the_organisation(): void
    {
        $this->canvas('Canvas A');

        $this->assertNull($this->years->finalisedInOrganisation($this->year));
    }

    public function test_the_organisation_wide_answer_names_the_oldest_canvas_holding_the_year(): void
    {
        $first = $this->canvas('Zebra');
        $second = $this->canvas('Alfa');
        FinalisedYear::factory()->for($second)->for($this->year, 'fiscalYear')->create();
        FinalisedYear::factory()->for($first)->for($this->year, 'fiscalYear')->create();

        $this->assertSame('Zebra', $this->years->finalisedInOrganisation($this->year));
    }

    public function test_another_organisation_does_not_leak_into_the_answer(): void
    {
        $elsewhere = Canvas::factory()->create();
        $otherYear = FiscalYear::factory()->for($elsewhere->organisation)->create(['year' => 2026]);
        FinalisedYear::factory()->for($elsewhere)->for($otherYear, 'fiscalYear')->create();

        $this->assertNull($this->years->finalisedInOrganisation($this->year));
        $this->assertFalse($this->years->finalisedInCanvas($this->canvas('Canvas A'), $this->year));
    }

    public function test_the_per_canvas_refusal_carries_the_year(): void
    {
        $canvas = $this->canvas('Canvas A');
        FinalisedYear::factory()->for($canvas)->for($this->year, 'fiscalYear')->create();

        $this->assertSame(
            '2026 is definitief. Heropen het jaar eerst',
            $this->refusal(fn () => $this->years->assertOpenInCanvas($canvas, $this->year)),
        );
    }

    public function test_the_organisation_wide_refusal_carries_the_year_and_the_canvas(): void
    {
        $canvas = $this->canvas('Canvas A');
        FinalisedYear::factory()->for($canvas)->for($this->year, 'fiscalYear')->create();

        $this->assertSame(
            '2026 is definitief in canvas Canvas A. Heropen het jaar eerst',
            $this->refusal(fn () => $this->years->assertOpenInOrganisation($this->year)),
        );
    }

    public function test_an_open_year_passes_both_guards(): void
    {
        $this->expectNotToPerformAssertions();

        $canvas = $this->canvas('Canvas A');

        $this->years->assertOpenInCanvas($canvas, $this->year);
        $this->years->assertOpenInOrganisation($this->year);
    }

    public function test_both_answers_take_one_query_whatever_the_number_of_canvases(): void
    {
        $canvas = $this->canvas('Canvas A');
        foreach (['Canvas B', 'Canvas C', 'Canvas D'] as $name) {
            FinalisedYear::factory()->for($this->canvas($name))->for($this->year, 'fiscalYear')->create();
        }

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->years->finalisedInCanvas($canvas, $this->year);
        $this->years->finalisedInOrganisation($this->year);

        $this->assertSame(2, $queries);
    }
}
