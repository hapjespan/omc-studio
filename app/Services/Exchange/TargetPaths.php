<?php

namespace App\Services\Exchange;

use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;

class TargetPaths
{
    public const SEPARATOR = ' > ';

    /** @var array<string, Element> */
    private array $elements;

    /** @var array<int, string> */
    private array $areas;

    public function __construct(Canvas $canvas)
    {
        $this->elements = Element::query()->with('area')->where('canvas_id', $canvas->getKey())->get()->keyBy(fn (Element $element) => (string) $element->getKey())->all();
        $this->areas = Area::query()->pluck('key', 'id')->map(fn (string $key) => (string) __('areas.'.$key.'.name'))->all();
    }

    public function of(Element $element): string
    {
        $names = [];

        for ($current = $element; $current !== null; $current = $this->elements[(string) $current->parent_id] ?? null) {
            array_unshift($names, (string) $current->name);
        }

        return implode(self::SEPARATOR, [$this->areas[$element->area_id], ...$names]);
    }

    /** @return array<string, Element> */
    public function lookup(): array
    {
        $lookup = [];

        foreach ($this->elements as $element) {
            $element->archived_at === null && $lookup[mb_strtolower($this->of($element))] = $element;
        }

        return $lookup;
    }
}
