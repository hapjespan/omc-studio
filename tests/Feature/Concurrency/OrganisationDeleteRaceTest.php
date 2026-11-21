<?php

namespace Tests\Feature\Concurrency;

use App\Models\Canvas;
use App\Models\Organisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RaceDatabase;
use Tests\Support\TwoProcessRunner;
use Tests\TestCase;

class OrganisationDeleteRaceTest extends TestCase
{
    use RefreshDatabase;

    private RaceDatabase $race;

    private string $claim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->race = new RaceDatabase;
        $this->claim = sys_get_temp_dir().'/organisation-delete-claim-'.getmypid();
    }

    protected function tearDown(): void
    {
        $this->race->clean(['audit_entries', 'elements', 'statements', 'canvases', 'organisations']);

        if (file_exists($this->claim)) {
            unlink($this->claim);
        }

        parent::tearDown();
    }

    public function test_deleting_an_organisation_while_a_canvas_is_created_in_it_never_leaves_a_canvas_without_its_organisation(): void
    {
        $environment = $this->race->prepare();
        $organisation = Organisation::on('race')->create(['name' => 'Race organisatie']);
        $template = Canvas::on('race')->create(['name' => 'Race sjabloon', 'kind' => 'template', 'organisation_id' => null]);

        $outcomes = (new TwoProcessRunner)->run(
            base_path('tests/Support/organisation-delete-worker.php'),
            [(string) $organisation->getKey(), (string) $template->getKey(), $this->claim],
            $environment,
        );

        sort($outcomes);
        $existing = Organisation::on('race')->whereKey($organisation->getKey())->exists();
        $canvases = Canvas::on('race')->where('organisation_id', $organisation->getKey())->count();
        $detail = implode(' | ', $outcomes);

        $this->assertSame($existing, $canvases === 1, $detail);
        $this->assertTrue(in_array('delete:ok', $outcomes, true) xor in_array('create:ok', $outcomes, true), $detail);
        $this->assertSame([], Canvas::on('race')->whereNotNull('organisation_id')->whereNotIn('organisation_id', Organisation::on('race')->select('id'))->pluck('id')->all(), $detail);
    }
}
