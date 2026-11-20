<?php

namespace App\Services\Structure;

use App\Enums\ElementKind;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class QuickEntry
{
    use WritableGuard;

    public function __construct(private readonly QuickEntryParser $parser, private readonly ElementTree $tree, private readonly SerialisedWrite $writes) {}

    /** @return array{lines: list<array<string, mixed>>, errors: list<array{line: int, message: string}>} */
    public function preview(Canvas $canvas, Area $area, string $text): array
    {
        return $this->parse($canvas, $area, $text)[0];
    }

    public function commit(Canvas $canvas, Area $area, string $text): void
    {
        $this->writes->run([$canvas], function () use ($canvas, $area, $text) {
            $this->assertStructureWritable($canvas);

            [$result, $existing] = $this->parse($canvas, $area, $text);

            if ($result['errors'] !== []) {
                throw ValidationException::withMessages(['rule' => $result['errors'][0]['message']]);
            }

            $created = $existing->all();

            foreach ($result['lines'] as $line) {
                if ($line['status'] === 'exists') {
                    continue;
                }

                $parent = $line['level'] === 0 ? null : $created[$this->parentPath($line['path'])];

                $created[$line['path']] = $this->tree->create($canvas, $area, ElementKind::from($line['kind']), $parent?->id, ['name' => $line['name']]);
            }
        });
    }

    /** @return array{0: array{lines: list<array<string, mixed>>, errors: list<array{line: int, message: string}>}, 1: Collection<string, Element>} */
    private function parse(Canvas $canvas, Area $area, string $text): array
    {
        if ($area->kind === 'text') {
            throw ValidationException::withMessages(['rule' => __('rule.direction_no_elements')]);
        }

        $existing = $this->existing($canvas, $area);

        return [$this->parser->parse($text, $area->kind, $existing->map(fn (Element $element) => $element->kind->value)->all()), $existing];
    }

    /** @return Collection<string, Element> */
    private function existing(Canvas $canvas, Area $area): Collection
    {
        $elements = Element::query()->where('canvas_id', $canvas->id)->where('area_id', $area->id)->whereNull('archived_at')->get()->keyBy('id');

        return $elements->mapWithKeys(fn (Element $element) => [$this->pathOf($element, $elements) => $element]);
    }

    /** @param Collection<string, Element> $elements */
    private function pathOf(Element $element, Collection $elements): string
    {
        $parent = $element->parent_id === null ? null : $elements->get($element->parent_id);

        return ($parent === null ? '' : $this->pathOf($parent, $elements).'>').Element::normalise((string) $element->name);
    }

    private function parentPath(string $path): string
    {
        return substr($path, 0, (int) strrpos($path, '>'));
    }
}
