<?php

namespace App\Services\Canvas;

use App\Enums\CanvasKind;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Organisation;
use App\Models\Statement;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Validation\ValidationException;

class CanvasLifecycle
{
    use WritableGuard;

    public function __construct(private readonly SerialisedWrite $writes) {}

    public function createFromTemplate(Organisation $organisation, Canvas $template, string $name): Canvas
    {
        return $this->writes->run([$organisation, $template], function () use ($organisation, $template, $name) {
            $this->assertWritable($organisation);

            return $this->clone($template, $organisation, $name);
        });
    }

    protected function clone(Canvas $source, Organisation $organisation, string $name): Canvas
    {
        $this->assertNameFree($organisation, $name);

        $canvas = Canvas::create(['organisation_id' => $organisation->getKey(), 'kind' => CanvasKind::Canvas, 'name' => trim($name)]);

        $this->cloneElements($source, $canvas);
        $this->cloneStatements($source, $canvas);
        UnknownAllocationDomain::ensure($canvas);

        return $canvas;
    }

    protected function cloneElements(Canvas $source, Canvas $canvas, ?string $parentId = null, ?string $newParentId = null): void
    {
        $elements = Element::where('canvas_id', $source->getKey())
            ->where('is_unknown_allocation', false)
            ->whereNull('archived_at')
            ->where('parent_id', $parentId)
            ->orderBy('position')
            ->get();

        foreach ($elements as $element) {
            $copy = Element::create([
                ...$element->only(['area_id', 'kind', 'name', 'description', 'modality_id', 'rationale', 'icon', 'position']),
                'canvas_id' => $canvas->getKey(),
                'parent_id' => $newParentId,
            ]);

            $this->cloneElements($source, $canvas, $element->getKey(), $copy->getKey());
        }
    }

    protected function cloneStatements(Canvas $source, Canvas $canvas): void
    {
        foreach (Statement::where('canvas_id', $source->getKey())->orderBy('position')->get() as $statement) {
            Statement::create([...$statement->only(['kind', 'title', 'body', 'position']), 'canvas_id' => $canvas->getKey()]);
        }
    }

    private function assertNameFree(Organisation $organisation, string $name, ?Canvas $except = null): void
    {
        $taken = Canvas::where('scope_key', 'organisation:'.$organisation->getKey())
            ->where('name_normalised', Canvas::normalise($name))
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => __('rule.canvas_name_taken')]);
        }
    }
}
