<?php

namespace App\Services\Exchange;

class CanvasImportShape
{
    private const RECORD = ['code', 'name', 'supplier', 'owner', 'cost_centre', 'application', 'description', 'archived_at'];

    private const FIGURE = ['forecast_cents', 'run_bp', 'change_bp', 'fte_hundredths', 'reference_date', 'remark'];

    private const ELEMENT = ['name', 'description', 'modality', 'rationale', 'icon', 'parent_id', 'archived_at'];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function apply(array $data): array
    {
        $data['organisation'] = self::tidy($data['organisation'] ?? null, ['name']);
        $data['organisation'] = self::with($data['organisation'], 'owners', fn (mixed $owner) => self::blank($owner));
        $data['organisation'] = self::with($data['organisation'], 'applications', fn (mixed $application) => self::tidy($application, ['name', 'supplier']));

        $data['cost_records'] = self::each($data['cost_records'] ?? null, function (mixed $record) {
            $record = self::tidy($record, self::RECORD);

            return self::with($record, 'yearly_figures', fn (mixed $figure) => self::tidy($figure, self::FIGURE));
        });

        $canvas = self::tidy($data['canvas'] ?? null, ['name']);
        $canvas = self::with($canvas, 'statements', fn (mixed $statement) => self::tidy($statement, ['title', 'body']));
        $canvas = self::with($canvas, 'elements', fn (mixed $element) => self::tidy($element, self::ELEMENT));
        $data['canvas'] = self::with($canvas, 'finalised_years', fn (mixed $year) => self::tidy($year, ['finalised_at']));

        return $data;
    }

    private static function blank(mixed $value): mixed
    {
        return is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value;
    }

    /**
     * @param  list<string>  $keys
     */
    private static function tidy(mixed $node, array $keys): mixed
    {
        if (! is_array($node) || ($node !== [] && array_is_list($node))) {
            return $node;
        }

        foreach ($keys as $key) {
            $node[$key] = self::blank($node[$key] ?? null);
        }

        return $node;
    }

    private static function each(mixed $list, callable $tidy): mixed
    {
        return is_array($list) && array_is_list($list) ? array_map($tidy, $list) : $list;
    }

    private static function with(mixed $node, string $key, callable $tidy): mixed
    {
        if (is_array($node) && isset($node[$key])) {
            $node[$key] = self::each($node[$key], $tidy);
        }

        return $node;
    }
}
