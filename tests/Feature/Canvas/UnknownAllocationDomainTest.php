<?php

namespace Tests\Feature\Canvas;

use App\Enums\CanvasKind;
use App\Enums\ElementKind;
use App\Models\Canvas;
use App\Models\Element;
use App\Services\Canvas\UnknownAllocationDomain;
use App\Services\Operations\InstallSteps;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnknownAllocationDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new ReferenceDataStep)();
    }

    public function test_a_canvas_gets_one_unknown_allocation_domain_in_supporting_activities(): void
    {
        $canvas = Canvas::factory()->create();

        $domain = UnknownAllocationDomain::ensure($canvas);

        $this->assertSame('Onbekende allocatie', $domain->name);
        $this->assertSame(ElementKind::Domain, $domain->kind);
        $this->assertTrue($domain->is_unknown_allocation);
        $this->assertNull($domain->parent_id);
        $this->assertNull($domain->modality_id);
        $this->assertSame('supporting_activities', $domain->area->key);
    }

    public function test_a_second_call_creates_none(): void
    {
        $canvas = Canvas::factory()->create();

        $first = UnknownAllocationDomain::ensure($canvas);
        $second = UnknownAllocationDomain::ensure($canvas);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Element::where('canvas_id', $canvas->id)->where('is_unknown_allocation', true)->count());
    }

    public function test_each_canvas_holds_its_own(): void
    {
        UnknownAllocationDomain::ensure(Canvas::factory()->create());
        UnknownAllocationDomain::ensure(Canvas::factory()->create());

        $this->assertSame(2, Element::where('is_unknown_allocation', true)->count());
    }

    public function test_a_template_never_holds_one(): void
    {
        $template = Canvas::factory()->create(['kind' => CanvasKind::Template, 'organisation_id' => null]);

        $this->assertNull(UnknownAllocationDomain::ensure($template));
        $this->assertSame(0, Element::count());
    }

    public function test_the_blank_template_exists_once_after_two_installs(): void
    {
        $steps = $this->app->make(InstallSteps::class);
        $steps->run();
        $steps->run();

        $blank = Canvas::where('is_blank_template', true)->get();

        $this->assertCount(1, $blank);
        $this->assertSame('Leeg canvas', $blank->first()->name);
        $this->assertSame(CanvasKind::Template, $blank->first()->kind);
        $this->assertNull($blank->first()->organisation_id);
        $this->assertSame(0, Element::where('canvas_id', $blank->first()->id)->count());
    }
}
