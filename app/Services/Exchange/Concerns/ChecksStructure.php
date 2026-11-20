<?php

namespace App\Services\Exchange\Concerns;

use App\Enums\ElementKind;
use App\Rules\BasisPoints;

trait ChecksStructure
{
    /** @var array<string, string> */
    private array $areaKinds = [];

    /** @var array<string, bool> */
    private array $carriers = [];

    /** @var list<string> */
    private array $importableAreas = [];

    /** @var list<string> */
    private array $modalities = [];

    /** @var list<string> */
    private array $icons = [];

    /** @var array<string, bool> */
    private array $recordIds = [];

    /** @var array<string, array<int, bool>> */
    private array $recordYears = [];

    /** @var array<string, array<int, bool>> */
    private array $setYears = [];

    /** @var array<string, string> */
    private array $recordLabels = [];

    /** @return array<string, array<string, mixed>> */
    private function elements(mixed $node): array
    {
        $index = [];
        $ids = [];
        $names = [];

        foreach ($this->listOf($node, 'canvas.elements') as $position => $element) {
            $at = 'canvas.elements['.$position.']';
            $element = $this->object($element, $at);

            $id = $this->value($element['id'] ?? null, $at.'.id', self::NAME, true);
            $area = $this->oneOf($element['area'] ?? null, $at.'.area', $this->importableAreas);
            $areaKind = $area === null ? null : $this->areaKinds[$area];
            $kind = $this->oneOf($element['kind'] ?? null, $at.'.kind', array_column(ElementKind::cases(), 'value'));
            $name = $this->text($element, 'name', $at.'.name', self::NAME, true, true);
            $this->text($element, 'description', $at.'.description', self::TEXT);
            $modality = $this->modality($element['modality'] ?? null, $at.'.modality', $areaKind);

            if ($this->text($element, 'rationale', $at.'.rationale', self::TEXT) !== null && $modality === null) {
                $this->fail($at.'.rationale', 'jsonx.rationale_without_modality');
            }

            $this->icon($element['icon'] ?? null, $at.'.icon', $areaKind);
            $this->integer($element, 'position', $at.'.position', 0, self::POSITION, true);

            $unknown = $element['is_unknown_allocation'] ?? null;

            if (! is_bool($unknown)) {
                $this->fail($at.'.is_unknown_allocation', 'jsonx.not_boolean');
            }

            $archived = $element['archived_at'] ?? null;
            $this->date($archived, $at.'.archived_at');

            $parent = $element['parent_id'] ?? null;

            if ($parent !== null && ! is_string($parent)) {
                $this->fail($at.'.parent_id', 'jsonx.not_text');

                $parent = null;
            }

            if ($name !== null && $area !== null && $archived === null && $unknown !== true) {
                $this->unique([$area, $parent, mb_strtolower(trim($name))], $at.'.name', $names, 'jsonx.name_taken');
            }

            if ($id === null || ! $this->unique($id, $at.'.id', $ids)) {
                continue;
            }

            $index[$id] = [
                'at' => $at,
                'area' => $area,
                'kind' => $kind,
                'parent' => $parent,
                'modality' => $modality,
                'archived' => $archived !== null,
                'unknown' => $unknown === true,
            ];
        }

        return $index;
    }

    private function modality(mixed $value, string $location, ?string $areaKind): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || ! in_array($value, $this->modalities, true)) {
            $this->fail($location, 'jsonx.unknown_value');

            return null;
        }

        if ($areaKind !== 'activities') {
            $this->fail($location, 'jsonx.modality_not_allowed');
        }

        return $value;
    }

    private function icon(mixed $value, string $location, ?string $areaKind): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value) || ! in_array($value, $this->icons, true)) {
            $this->fail($location, 'jsonx.unknown_value');

            return;
        }

        if ($areaKind !== 'list') {
            $this->fail($location, 'jsonx.icon_not_allowed');
        }
    }

    /** @param array<string, array<string, mixed>> $index */
    private function structure(array $index): void
    {
        foreach ($index as $element) {
            $at = $element['at'].'.parent_id';
            $kind = $element['kind'];

            if ($kind === null) {
                continue;
            }

            if ($kind === ElementKind::Domain->value) {
                if ($element['parent'] !== null) {
                    $this->structureFail($at, 'domain_parent');
                }

                continue;
            }

            $cluster = $kind === ElementKind::Cluster->value;
            $reason = $cluster ? 'cluster_parent' : 'activity_parent';

            if ($cluster && $element['area'] !== null && $this->areaKinds[$element['area']] !== 'activities') {
                $this->structureFail($at, 'cluster_area');

                continue;
            }

            $parent = $element['parent'] === null ? null : ($index[$element['parent']] ?? null);

            if ($element['parent'] !== null && $parent === null) {
                $this->fail($at, 'jsonx.unknown_reference');

                continue;
            }

            if ($parent === null || $parent['area'] !== $element['area'] || ! $this->parentFits($cluster, $parent['kind'])) {
                $this->structureFail($at, $reason);

                continue;
            }

            if ($parent['archived'] && ! $element['archived']) {
                $this->fail($at, 'jsonx.archived_parent');
            }
        }
    }

    private function parentFits(bool $cluster, ?string $parentKind): bool
    {
        return $cluster ? $parentKind === ElementKind::Domain->value : $parentKind === ElementKind::Domain->value || $parentKind === ElementKind::Cluster->value;
    }

    private function structureFail(string $location, string $reason): void
    {
        $this->fail($location, 'jsonx.structure', ['reason' => __('jsonx.reason_'.$reason)]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $index
     * @return array<string, bool>
     */
    private function sets(mixed $node, array $index): array
    {
        $targeted = [];
        $seen = [];

        foreach ($this->listOf($node, 'canvas.allocation_sets') as $position => $set) {
            $at = 'canvas.allocation_sets['.$position.']';
            $set = $this->object($set, $at);
            $recordId = $set['cost_record_id'] ?? null;
            $year = $set['year'] ?? null;

            if (! is_string($recordId) || ! isset($this->recordIds[$recordId])) {
                $this->fail($at.'.cost_record_id', 'jsonx.unknown_reference');
            }

            if (! in_array($year, $this->years, true)) {
                $this->fail($at.'.year', 'jsonx.year_unknown');
            }

            $this->unique([$recordId, $year], $at, $seen);

            if (is_string($recordId) && in_array($year, $this->years, true) && isset($this->recordIds[$recordId]) && ! isset($this->recordYears[$recordId][$year])) {
                $this->fail($at.'.year', 'jsonx.set_without_figures');
            }

            is_string($recordId) && is_int($year) && $this->setYears[$recordId][$year] = true;

            $lines = $this->listOf($set['lines'] ?? null, $at.'.lines');

            if ($lines === []) {
                ($set['lines'] ?? null) === [] && $this->fail($at.'.lines', 'jsonx.required');

                continue;
            }

            $sum = 0;
            $sound = true;
            $elements = [];

            foreach ($lines as $number => $line) {
                $location = $at.'.lines['.$number.']';
                $line = $this->object($line, $location);
                $elementId = $line['element_id'] ?? null;
                $target = is_string($elementId) ? ($index[$elementId] ?? null) : null;

                if ($target === null) {
                    $this->fail($location.'.element_id', 'jsonx.unknown_reference');

                    $sound = false;
                } elseif (! $this->carries($target)) {
                    $this->fail($location.'.element_id', 'jsonx.target_invalid');

                    $sound = false;
                } elseif (! $this->unique($elementId, $location.'.element_id', $elements)) {
                    $sound = false;
                } else {
                    $targeted[$elementId] = true;
                }

                $points = $this->integer($line, 'basis_points', $location.'.basis_points', 1, BasisPoints::MAX, true);

                if ($points === null) {
                    $sound = false;

                    continue;
                }

                $sum += $points;
            }

            if ($sound && $sum !== BasisPoints::MAX) {
                $this->fail($at, 'jsonx.hundred_percent');
            }
        }

        return $targeted;
    }

    /** @param array<string, mixed> $target */
    private function carries(array $target): bool
    {
        return ! $target['archived'] && ! $target['unknown'] && $target['area'] !== null && ($this->carriers[$target['area']] ?? false);
    }

    /**
     * @param  array<string, array<string, mixed>>  $index
     * @param  array<string, bool>  $targeted
     */
    private function unknownAllocation(array $index, array $targeted): void
    {
        $seen = false;

        foreach ($index as $id => $element) {
            if (! $element['unknown']) {
                continue;
            }

            if ($seen) {
                $this->fail($element['at'], 'jsonx.unknown_repeated');

                continue;
            }

            $seen = true;

            $sound = $element['kind'] === ElementKind::Domain->value
                && $element['area'] === 'supporting_activities'
                && $element['parent'] === null
                && $element['modality'] === null
                && ! isset($targeted[$id])
                && ! $this->hasChildren($index, $id);

            if (! $sound) {
                $this->fail($element['at'], 'jsonx.unknown_invalid');
            }
        }
    }

    /** @param array<string, array<string, mixed>> $index */
    private function hasChildren(array $index, string $id): bool
    {
        foreach ($index as $element) {
            if ($element['parent'] === $id) {
                return true;
            }
        }

        return false;
    }
}
