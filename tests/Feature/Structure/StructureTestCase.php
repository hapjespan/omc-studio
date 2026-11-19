<?php

namespace Tests\Feature\Structure;

use App\Enums\ElementKind;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Services\Operations\Steps\ReferenceDataStep;
use App\Services\Structure\ElementTree;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

abstract class StructureTestCase extends TestCase
{
    use RefreshDatabase;

    protected Canvas $canvas;

    protected function setUp(): void
    {
        parent::setUp();

        app(ReferenceDataStep::class)();

        $this->canvas = Canvas::factory()->create();
    }

    protected function tree(): ElementTree
    {
        return app(ElementTree::class);
    }

    protected function area(string $key): Area
    {
        return Area::query()->where('key', $key)->firstOrFail();
    }

    protected function domain(string $area = 'primary_activities', string $name = 'Inkoop', ?Canvas $canvas = null): Element
    {
        return $this->tree()->create($canvas ?? $this->canvas, $this->area($area), ElementKind::Domain, null, ['name' => $name]);
    }

    protected function cluster(Element $parent, string $name = 'Werving'): Element
    {
        return $this->tree()->create($this->canvas, $this->area('primary_activities'), ElementKind::Cluster, $parent->id, ['name' => $name]);
    }

    protected function refusal(Closure $write): ?string
    {
        try {
            $write();
        } catch (ValidationException $refusal) {
            return (string) $refusal->errors()['rule'][0];
        }

        return null;
    }
}
