<?php

namespace App\Services\Layout;

use App\Models\Area;
use App\Rules\LayoutStyle;
use App\Support\Colour;
use Illuminate\Validation\ValidationException;

class LayoutValidator
{
    private const MIN_WIDTH = 40;

    private const MIN_HEIGHT = 24;

    private const DIRECTION_HEIGHT = 180;

    private const MINIMUM_CONTRAST = 4.5;

    private const COLUMN_MIN = -2147483648;

    private const COLUMN_MAX = 2147483647;

    private const BOX_KEYS = ['x', 'y', 'width', 'height', 'z', 'style'];

    /** @var array<string, array{id: string, area: string, parent: ?string, kind: string, name: string}> */
    private array $byId;

    /** @param list<array{id: string, area: string, parent: ?string, kind: string, name: string}> $elements */
    public function __construct(private array $elements)
    {
        $this->byId = array_column($elements, null, 'id');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{rule: string, element: ?string, message: string}>
     */
    public function refusals(array $payload): array
    {
        $entries = is_array($payload['elements'] ?? null) ? $payload['elements'] : [];
        $areas = is_array($payload['areas'] ?? null) ? $payload['areas'] : [];

        $refused = [...$this->membership($entries), ...$this->shape($entries, $areas)];

        if ($refused !== []) {
            return $refused;
        }

        $styles = $this->styles($entries, $areas);

        return [
            ...$this->parents($entries),
            ...$this->containment($entries, $areas),
            ...$this->areaBounds($areas),
            ...$this->minimumSizes($entries, $areas),
            ...$styles,
            ...($styles === [] ? $this->contrast($entries, $areas) : []),
        ];
    }

    /** @param array<string, mixed> $payload */
    public function assertValid(array $payload): void
    {
        $refused = $this->refusals($payload);

        if ($refused !== []) {
            throw ValidationException::withMessages(['rule' => array_column($refused, 'message')]);
        }
    }

    /**
     * @param  array<array-key, mixed>  $entries
     * @return list<array{rule: string, element: ?string, message: string}>
     */
    private function membership(array $entries): array
    {
        $refused = [];

        foreach (array_keys($entries) as $id) {
            if (! isset($this->byId[(string) $id])) {
                $refused[] = $this->refusal('unknown_element', null, ['id' => (string) $id]);
            }
        }

        foreach ($this->byId as $id => $element) {
            if (! array_key_exists($id, $entries)) {
                $refused[] = $this->refusal('missing_element', (string) $id, ['name' => $element['name']]);
            }
        }

        return $refused;
    }

    /**
     * @param  array<array-key, mixed>  $entries
     * @param  array<array-key, mixed>  $areas
     * @return list<array{rule: string, element: ?string, message: string}>
     */
    private function shape(array $entries, array $areas): array
    {
        $refused = [];

        foreach ($entries as $id => $entry) {
            $mode = is_array($entry) ? ($entry['mode'] ?? null) : null;

            if ($mode === 'automatic' && array_diff(array_keys($entry), ['mode', 'parent']) === []) {
                continue;
            }

            if ($mode !== 'manual' || ! $this->isBox($entry, [...self::BOX_KEYS, 'mode', 'parent'])) {
                $refused[] = $this->refusal('invalid_layout', (string) $id, ['name' => $this->name((string) $id)]);
            }
        }

        foreach ($areas as $key => $area) {
            if (! $this->isBox($area, self::BOX_KEYS)) {
                $refused[] = $this->refusal('invalid_layout', null, ['name' => $this->areaName((string) $key)]);
            }
        }

        return $refused;
    }

    /**
     * @param  array<array-key, mixed>  $entries
     * @return list<array{rule: string, element: ?string, message: string}>
     */
    private function parents(array $entries): array
    {
        $refused = [];

        foreach ($entries as $id => $entry) {
            if (is_array($entry) && array_key_exists('parent', $entry) && $entry['parent'] !== $this->byId[(string) $id]['parent']) {
                $refused[] = $this->refusal('parent_mismatch', (string) $id, ['name' => $this->name((string) $id)]);
            }
        }

        return $refused;
    }

    /**
     * @param  array<array-key, mixed>  $entries
     * @param  array<array-key, mixed>  $areas
     * @return list<array{rule: string, element: ?string, message: string}>
     */
    private function containment(array $entries, array $areas): array
    {
        $areaWidths = $this->areaWidths($areas);
        $widths = AutomaticWidths::compute($this->elements, $areaWidths, $this->elementWidths($entries));
        $refused = [];

        foreach ($this->manualEntries($entries) as $id => $entry) {
            $element = $this->byId[$id];
            $parentWidth = $element['parent'] === null
                ? ($areaWidths[$element['area']] ?? AutomaticWidths::areaWidth($element['area']))
                : $widths[$element['parent']];

            if ($entry['x'] < 0 || $entry['y'] < 0 || $parentWidth < $entry['x'] + $entry['width']) {
                $refused[] = $this->refusal('outside_parent', $id, ['name' => $element['name'], 'parent' => $this->parentName($element)]);
            }
        }

        return $refused;
    }

    /**
     * @param  array<array-key, mixed>  $areas
     * @return list<array{rule: string, element: ?string, message: string}>
     */
    private function areaBounds(array $areas): array
    {
        $refused = [];

        foreach ($areas as $key => $area) {
            $key = (string) $key;

            if ($key === 'direction') {
                $refused[] = $this->refusal('direction_layout', null, []);
            } elseif (! in_array($key, Area::KEYS, true)) {
                $refused[] = $this->refusal('unknown_area', null, ['key' => $key]);
            } elseif ($area['x'] < 0 || $area['y'] < self::DIRECTION_HEIGHT || $area['x'] + $area['width'] > Artboard::WIDTH || $area['y'] + $area['height'] > Artboard::HEIGHT) {
                $refused[] = $this->refusal('area_outside', null, ['name' => $this->areaName($key)]);
            }
        }

        return $refused;
    }

    /**
     * @param  array<array-key, mixed>  $entries
     * @param  array<array-key, mixed>  $areas
     * @return list<array{rule: string, element: ?string, message: string}>
     */
    private function minimumSizes(array $entries, array $areas): array
    {
        $refused = [];

        foreach ($this->manualEntries($entries) as $id => $entry) {
            if ($this->tooSmall($entry)) {
                $refused[] = $this->refusal('too_small', $id, ['name' => $this->name($id)]);
            }
        }

        foreach ($areas as $key => $area) {
            if ($this->tooSmall($area)) {
                $refused[] = $this->refusal('too_small', null, ['name' => $this->areaName((string) $key)]);
            }
        }

        return $refused;
    }

    /**
     * @param  array<array-key, mixed>  $entries
     * @param  array<array-key, mixed>  $areas
     * @return list<array{rule: string, element: ?string, message: string}>
     */
    private function styles(array $entries, array $areas): array
    {
        $refused = [];

        foreach ($this->manualEntries($entries) as $id => $entry) {
            foreach ((new LayoutStyle($this->name($id)))->violations($entry['style'] ?? []) as $message) {
                $refused[] = ['rule' => 'style', 'element' => $id, 'message' => $message];
            }
        }

        foreach ($areas as $key => $area) {
            foreach ((new LayoutStyle($this->areaName((string) $key)))->violations($area['style'] ?? []) as $message) {
                $refused[] = ['rule' => 'style', 'element' => null, 'message' => $message];
            }
        }

        return $refused;
    }

    /**
     * @param  array<array-key, mixed>  $entries
     * @param  array<array-key, mixed>  $areas
     * @return list<array{rule: string, element: ?string, message: string}>
     */
    private function contrast(array $entries, array $areas): array
    {
        $manual = $this->manualEntries($entries);
        $refused = [];

        foreach ($manual as $id => $entry) {
            if (isset($entry['style']['text_colour'])) {
                $refused = [...$refused, ...$this->contrastRefusal($entry['style']['text_colour'], $this->elementFill($id, $manual, $areas), $this->name($id), $id)];
            }
        }

        foreach ($areas as $key => $area) {
            if (isset($area['style']['text_colour'])) {
                $refused = [...$refused, ...$this->contrastRefusal($area['style']['text_colour'], $this->areaFill((string) $key, $areas), $this->areaName((string) $key), null)];
            }
        }

        return $refused;
    }

    /** @return list<array{rule: string, element: ?string, message: string}> */
    private function contrastRefusal(string $text, string $fill, string $name, ?string $element): array
    {
        $ratio = Colour::contrast($text, $fill);

        if ($ratio >= self::MINIMUM_CONTRAST) {
            return [];
        }

        return [$this->refusal('contrast', $element, ['name' => $name, 'ratio' => number_format(floor($ratio * 10) / 10, 1, ',', '')])];
    }

    /**
     * @param  array<string, array<string, mixed>>  $manual
     * @param  array<array-key, mixed>  $areas
     */
    private function elementFill(string $id, array $manual, array $areas): string
    {
        $element = $this->byId[$id];
        $behind = $element['parent'] === null
            ? $this->areaFill($element['area'], $areas)
            : $this->elementFill($element['parent'], $manual, $areas);
        $style = $manual[$id]['style'] ?? [];
        $own = $style['fill'] ?? Colour::defaults()['areas'][$element['area']][$element['kind']] ?? null;

        return $own === null ? $behind : Colour::effectiveFill($own, $style['fill_opacity'] ?? 100, $behind);
    }

    /** @param array<array-key, mixed> $areas */
    private function areaFill(string $key, array $areas): string
    {
        $white = Colour::defaults()['white'];
        $style = $areas[$key]['style'] ?? [];

        return isset($style['fill']) ? Colour::effectiveFill($style['fill'], $style['fill_opacity'] ?? 100, $white) : $white;
    }

    /**
     * @param  array<array-key, mixed>  $entries
     * @return array<string, array<string, mixed>>
     */
    private function manualEntries(array $entries): array
    {
        $manual = [];

        foreach ($entries as $id => $entry) {
            if (($entry['mode'] ?? null) === 'manual') {
                $manual[(string) $id] = $entry;
            }
        }

        return $manual;
    }

    /**
     * @param  array<array-key, mixed>  $entries
     * @return array<string, int>
     */
    private function elementWidths(array $entries): array
    {
        return array_map(fn (array $entry): int => $entry['width'], $this->manualEntries($entries));
    }

    /**
     * @param  array<array-key, mixed>  $areas
     * @return array<string, int>
     */
    private function areaWidths(array $areas): array
    {
        $widths = [];

        foreach ($areas as $key => $area) {
            if (in_array((string) $key, Area::KEYS, true)) {
                $widths[(string) $key] = $area['width'];
            }
        }

        return $widths;
    }

    /** @param list<string> $allowed */
    private function isBox(mixed $box, array $allowed): bool
    {
        if (! is_array($box) || array_diff(array_keys($box), $allowed) !== []) {
            return false;
        }

        foreach (['x', 'y', 'width', 'height', 'z'] as $key) {
            $value = $box[$key] ?? ($key === 'z' ? 0 : null);

            if (! is_int($value) || $value < self::COLUMN_MIN || $value > self::COLUMN_MAX) {
                return false;
            }
        }

        return ! array_key_exists('style', $box) || is_array($box['style']);
    }

    /** @param array<string, mixed> $box */
    private function tooSmall(array $box): bool
    {
        return $box['width'] < self::MIN_WIDTH || $box['height'] < self::MIN_HEIGHT;
    }

    private function name(string $id): string
    {
        return $this->byId[$id]['name'] ?? $id;
    }

    private function areaName(string $key): string
    {
        return in_array($key, Area::KEYS, true) ? (string) __('areas.'.$key.'.name') : $key;
    }

    /** @param array{area: string, parent: ?string} $element */
    private function parentName(array $element): string
    {
        return $element['parent'] === null ? $this->areaName($element['area']) : $this->name($element['parent']);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{rule: string, element: ?string, message: string}
     */
    private function refusal(string $code, ?string $element, array $values): array
    {
        return ['rule' => $code, 'element' => $element, 'message' => (string) __('layout.rule.'.$code, $values)];
    }
}
