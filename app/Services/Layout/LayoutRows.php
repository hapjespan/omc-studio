<?php

namespace App\Services\Layout;

use App\Models\Canvas;
use App\Models\Layout;
use Illuminate\Database\Eloquent\Builder;

class LayoutRows
{
    /** @param list<string> $elementIds */
    public function remove(Canvas $canvas, array $elementIds): void
    {
        if ($elementIds === []) {
            return;
        }

        if ($this->rows($canvas, $elementIds)->delete() > 0) {
            $this->raise($canvas);
        }
    }

    /**
     * @param  list<string>  $elementIds
     * @return list<array<string, mixed>>
     */
    public function snapshot(Canvas $canvas, array $elementIds): array
    {
        if ($elementIds === []) {
            return [];
        }

        return $this->rows($canvas, $elementIds)->get()->map(fn (Layout $row) => $row->getAttributes())->all();
    }

    /** @param list<array<string, mixed>> $rows */
    public function restore(Canvas $canvas, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach ($rows as $row) {
            (new Layout)->setRawAttributes($row)->save();
        }

        $this->raise($canvas);
    }

    /**
     * @param  list<string>  $elementIds
     * @return Builder<Layout>
     */
    private function rows(Canvas $canvas, array $elementIds): Builder
    {
        return Layout::query()->where('canvas_id', $canvas->getKey())->whereIn('element_id', $elementIds);
    }

    private function raise(Canvas $canvas): void
    {
        Canvas::query()->whereKey($canvas->getKey())->increment('layout_version');

        $canvas->layout_version = (int) $canvas->layout_version + 1;
        $canvas->syncOriginalAttribute('layout_version');
    }
}
