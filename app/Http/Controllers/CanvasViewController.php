<?php

namespace App\Http\Controllers;

use App\Calculation\Figures;
use App\Enums\CostKind;
use App\Http\Resources\CanvasFigures;
use App\Http\Resources\SnapshotVersions;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\Statement;
use App\Services\Canvas\ViewData;
use App\Services\Finance\FigureService;
use App\Services\Layout\LayoutSnapshot;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CanvasViewController extends Controller
{
    private const OVERLAYS = ['none', 'modality', 'financial'];

    public function __construct(private readonly FigureService $figures, private readonly LayoutSnapshot $layouts, private readonly ViewData $data) {}

    public function __invoke(Request $request, Canvas $canvas): Response
    {
        $organisation = $canvas->organisation;
        $overlay = in_array($request->query('overlay'), self::OVERLAYS, true) ? $request->query('overlay') : 'none';
        $filters = [
            'costKind' => CostKind::tryFrom($this->text($request, 'cost_kind'))?->value,
            'ownerId' => $this->owned($organisation?->owners(), $this->identifier($request, 'owner')),
            'applicationId' => $this->owned($organisation?->applications(), $this->identifier($request, 'application')),
        ];
        $year = $request->attributes->get('fiscal_year');
        $financial = $overlay === 'financial';
        $asked = $financial && $year !== null;
        $figures = $asked ? $this->figures->figures($canvas, $year, $filters) : null;
        $element = $request->query('element');

        return Inertia::render('Canvas/View', [
            'canWrite' => false,
            'layouts' => $this->layouts->for($canvas),
            'versions' => SnapshotVersions::for($canvas),
            'overlay' => $overlay,
            'year' => $year,
            'filters' => $filters,
            'filterOptions' => fn () => [
                'costKinds' => array_map(fn (CostKind $kind) => $kind->value, CostKind::cases()),
                'owners' => $organisation?->owners()->orderBy('name_normalised')->get(['id', 'name'])->all() ?? [],
                'applications' => $organisation?->applications()->orderBy('name_normalised')->get(['id', 'name'])->all() ?? [],
            ],
            'figures' => $figures === null ? null : CanvasFigures::from($figures),
            'figuresMessage' => $financial && $year === null ? __('overlay.no_fiscal_years') : ($asked && $figures === null ? __('overlay.closing_check_failed') : null),
            'contributions' => Inertia::optional(fn () => $figures === null || ! is_string($element) ? [] : $this->contributions($figures, $element)),
            'layout' => fn () => $this->data->layout(),
            'elements' => fn () => $this->data->elements(Element::where('canvas_id', $canvas->id)->whereNull('archived_at')->with('area:id,key')->get()),
            'statements' => fn () => $this->data->statements(Statement::where('canvas_id', $canvas->id)->orderBy('position')->get()),
            'modalities' => fn () => $this->data->modalities(),
        ]);
    }

    private function text(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }

    /** @param HasMany<*, *>|null $relation */
    private function owned(?HasMany $relation, ?int $id): ?int
    {
        return $relation === null || $id === null ? null : $relation->whereKey($id)->value('id');
    }

    private function identifier(Request $request, string $key): ?int
    {
        $value = $this->text($request, $key);

        return ctype_digit($value) && strlen($value) < 19 ? (int) $value : null;
    }

    /** @return list<array{name: string, id: string|int, amountCents: int, basisPoints: int}> */
    private function contributions(Figures $figures, string $element): array
    {
        $rows = CanvasFigures::contributions($figures, $element);
        $names = CostRecord::whereIn('id', array_column($rows, 'id'))->pluck('name', 'id');

        return array_map(fn (array $row) => ['name' => $names[$row['id']] ?? ''] + $row, $rows);
    }
}
