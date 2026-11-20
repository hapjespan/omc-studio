<?php

namespace App\Services\Layout;

use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Layout;
use App\Services\Structure\ElementTree;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Validation\ValidationException;

class DrawingMove
{
    use WritableGuard;

    private const FORMAT_VERSION = 1;

    public function __construct(
        private readonly SerialisedWrite $writes,
        private readonly LayoutVersions $versions,
        private readonly ElementTree $tree,
        private readonly LayoutRows $rows,
        private readonly LayoutAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $request
     * @return array{structure_version: int, layout_version: int}
     */
    public function move(Canvas $canvas, array $request): array
    {
        return $this->writes->run([$canvas], function () use ($canvas, $request): array {
            $this->assertWritable($canvas);
            $this->versions->assertCurrent($canvas, $request);

            $element = $this->element($canvas, (string) $request['element_id']);
            $target = $this->element($canvas, (string) $request['target_id']);
            $layout = $this->layout($request);

            $before = $this->audit->current($canvas);

            $this->tree->move($element, $target);
            $this->order($element, $request['position'] ?? null);
            $this->assertFits($canvas, $element, $layout);
            $this->store($canvas, $element, $layout);

            $this->audit->record($canvas, $before, $this->audit->current($canvas));

            return $this->storedVersions($canvas);
        });
    }

    /**
     * @param  array<string, mixed>  $request
     * @return ?array<string, mixed>
     */
    private function layout(array $request): ?array
    {
        $layout = $request['layout'] ?? null;

        return is_array($layout) ? $layout : null;
    }

    private function element(Canvas $canvas, string $id): Element
    {
        $element = Element::query()->where('canvas_id', $canvas->getKey())->whereNull('archived_at')->find($id);

        if ($element === null) {
            throw ValidationException::withMessages(['rule' => __('layout.rule.unknown_element', ['id' => $id])]);
        }

        return $element;
    }

    private function order(Element $element, ?int $position): void
    {
        if ($position === null) {
            return;
        }

        $siblings = Element::query()
            ->where('canvas_id', $element->canvas_id)
            ->where('area_id', $element->area_id)
            ->where('parent_id', $element->parent_id)
            ->where('kind', $element->kind)
            ->where('is_unknown_allocation', false)
            ->whereNull('archived_at')
            ->whereKeyNot($element->getKey())
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->all();

        array_splice($siblings, min($position, count($siblings)), 0, [$element]);

        foreach ($siblings as $index => $sibling) {
            if ($sibling->position !== $index) {
                $sibling->update(['position' => $index]);
            }
        }
    }

    /** @param ?array<string, mixed> $layout */
    private function assertFits(Canvas $canvas, Element $element, ?array $layout): void
    {
        if ($layout === null) {
            return;
        }

        $refusals = (new LayoutValidator($this->rows->elements($canvas)))->refusals($this->payload($canvas, $element, $layout));
        $messages = array_column(array_filter($refusals, fn (array $refusal): bool => $refusal['element'] === (string) $element->getKey()), 'message');

        if ($messages !== []) {
            throw ValidationException::withMessages(['rule' => $messages]);
        }
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return array{areas: array<string, mixed>, elements: array<string, mixed>}
     */
    private function payload(Canvas $canvas, Element $element, array $layout): array
    {
        $areaKeys = Area::query()->pluck('key', 'id');
        $areas = [];
        $manual = [];

        foreach (Layout::query()->where('canvas_id', $canvas->getKey())->get() as $row) {
            $box = ['x' => $row->x, 'y' => $row->y, 'width' => $row->width, 'height' => $row->height, 'z' => $row->z, 'style' => $row->style];

            if ($row->element_id === null) {
                $areas[(string) $areaKeys[$row->area_id]] = $box;
            } else {
                $manual[(string) $row->element_id] = ['mode' => 'manual', ...$box];
            }
        }

        $manual[(string) $element->getKey()] = [...$layout, 'mode' => 'manual'];
        $elements = [];

        foreach ($this->rows->elements($canvas) as $row) {
            $elements[$row['id']] = $manual[$row['id']] ?? ['mode' => 'automatic'];
        }

        return ['areas' => $areas, 'elements' => $elements];
    }

    /** @param ?array<string, mixed> $layout */
    private function store(Canvas $canvas, Element $element, ?array $layout): void
    {
        $id = (string) $element->getKey();

        if ($layout === null) {
            $this->rows->remove($canvas, [$id]);

            return;
        }

        Layout::query()->updateOrCreate(['element_id' => $id], [
            'canvas_id' => $canvas->getKey(),
            'area_id' => null,
            'x' => $layout['x'],
            'y' => $layout['y'],
            'width' => $layout['width'],
            'height' => $layout['height'],
            'z' => $layout['z'] ?? 0,
            'style' => $layout['style'] ?? [],
            'format_version' => self::FORMAT_VERSION,
        ]);

        $canvas->raiseLayoutVersion();
    }

    /** @return array{structure_version: int, layout_version: int} */
    private function storedVersions(Canvas $canvas): array
    {
        $stored = Canvas::query()->whereKey($canvas->getKey())->firstOrFail(['structure_version', 'layout_version']);

        return ['structure_version' => (int) $stored->structure_version, 'layout_version' => (int) $stored->layout_version];
    }
}
