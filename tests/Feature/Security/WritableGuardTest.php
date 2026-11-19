<?php

namespace Tests\Feature\Security;

use App\Models\Canvas;
use App\Models\Organisation;
use App\Services\Support\WritableGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WritableGuardSample
{
    use WritableGuard;

    public function write(Organisation|Canvas $aggregate): void
    {
        $this->assertWritable($aggregate);
    }

    public function writeStructure(Canvas $canvas): void
    {
        $this->assertStructureWritable($canvas);
    }
}

class WritableGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_organisation_and_canvas_are_writable(): void
    {
        $organisation = Organisation::factory()->create();
        $canvas = Canvas::factory()->create(['organisation_id' => $organisation->id]);

        $this->assertNull($this->refusal(fn (WritableGuardSample $guard) => $guard->write($organisation)));
        $this->assertNull($this->refusal(fn (WritableGuardSample $guard) => $guard->write($canvas)));
        $this->assertNull($this->refusal(fn (WritableGuardSample $guard) => $guard->writeStructure($canvas)));
    }

    public function test_an_archived_canvas_takes_no_write(): void
    {
        $canvas = Canvas::factory()->create(['archived_at' => now()]);

        $this->assertSame('Dit canvas is gearchiveerd', $this->refusal(fn (WritableGuardSample $guard) => $guard->write($canvas)));
        $this->assertSame('Dit canvas is gearchiveerd', $this->refusal(fn (WritableGuardSample $guard) => $guard->writeStructure($canvas)));
    }

    public function test_an_archived_organisation_takes_no_write_itself_and_none_on_its_canvases(): void
    {
        $organisation = Organisation::factory()->create(['archived_at' => now()]);
        $canvas = Canvas::factory()->create(['organisation_id' => $organisation->id]);

        $this->assertSame('Deze organisatie is gearchiveerd', $this->refusal(fn (WritableGuardSample $guard) => $guard->write($organisation)));
        $this->assertSame('Deze organisatie is gearchiveerd', $this->refusal(fn (WritableGuardSample $guard) => $guard->write($canvas)));
        $this->assertSame('Deze organisatie is gearchiveerd', $this->refusal(fn (WritableGuardSample $guard) => $guard->writeStructure($canvas)));
    }

    public function test_a_template_takes_structure_and_statements_only(): void
    {
        $template = Canvas::factory()->template()->create();

        $this->assertNull($this->refusal(fn (WritableGuardSample $guard) => $guard->writeStructure($template)));
        $this->assertSame('Een sjabloon bevat alleen structuur en Koers', $this->refusal(fn (WritableGuardSample $guard) => $guard->write($template)));
    }

    public function test_the_blank_template_takes_nothing(): void
    {
        $blank = Canvas::factory()->template()->create(['is_blank_template' => true]);

        $this->assertSame('Leeg canvas kan niet worden gewijzigd', $this->refusal(fn (WritableGuardSample $guard) => $guard->write($blank)));
        $this->assertSame('Leeg canvas kan niet worden gewijzigd', $this->refusal(fn (WritableGuardSample $guard) => $guard->writeStructure($blank)));
    }

    /** @param  callable(WritableGuardSample): mixed  $write */
    private function refusal(callable $write): ?string
    {
        try {
            $write(new WritableGuardSample);
        } catch (ValidationException $refusal) {
            return (string) $refusal->errors()['rule'][0];
        }

        return null;
    }
}
