<?php

namespace App\Http\Controllers;

use App\Calculation\Figures;
use App\Enums\CanvasKind;
use App\Enums\CostKind;
use App\Http\Resources\CanvasFigures;
use App\Http\Resources\SnapshotVersions;
use App\Models\Area;
use App\Models\Modality;
use App\Models\Snapshot;
use App\Services\Snapshot\Snapshots;
use App\Support\Format;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SnapshotViewController extends Controller
{
    private const OVERLAYS = ['none', 'modality', 'financial'];

    public function __construct(private readonly Snapshots $snapshots) {}

    public function __invoke(Request $request, Snapshot $snapshot): Response
    {
        $canvas = $snapshot->canvas;
        abort_if($canvas->kind === CanvasKind::Template, 404);

        $content = $this->snapshots->read($snapshot);
        $figures = $content === null ? null : $this->figures($snapshot, $content);
        $damaged = $content === null || $figures === null;
        $content = $damaged ? ['elements' => [], 'statements' => [], 'cost_records' => [], 'layout' => []] : $content;
        $overlay = in_array($request->query('overlay'), self::OVERLAYS, true) ? $request->query('overlay') : 'none';
        $element = $request->query('element');
        $modalities = Modality::orderBy('position')->get();
        $letters = $modalities->pluck('letter', 'key');

        return Inertia::render('Canvas/SnapshotView', [
            'snapshot' => [
                'id' => $snapshot->getKey(),
                'name' => $snapshot->name,
                'date' => Format::formatDate($snapshot->created_at->toIso8601String()),
                'year' => $snapshot->fiscal_year,
            ],
            'versions' => SnapshotVersions::for($canvas, $snapshot),
            'layouts' => array_map(fn (array $box) => [...$box, 'style' => ($box['style'] ?? []) === [] ? new \stdClass : $box['style']], $content['layout']),
            'overlay' => $overlay,
            'year' => $snapshot->fiscal_year,
            'costKinds' => array_map(fn (CostKind $kind) => $kind->value, CostKind::cases()),
            'damaged' => $damaged,
            'figures' => $overlay === 'financial' && ! $damaged ? CanvasFigures::from($figures) : null,
            'figuresMessage' => $damaged ? __('snap.damaged') : null,
            'contributions' => Inertia::optional(fn () => $damaged || ! is_string($element) ? [] : $this->contributions($figures, $content, $element)),
            'layout' => fn () => [
                'areas' => Area::orderBy('position')->get()->map(fn (Area $area) => [
                    'key' => $area->key,
                    'kind' => $area->kind,
                    'position' => $area->position,
                    'label' => __('areas.'.$area->key.'.name'),
                ])->values(),
                'directionLabels' => ['mission' => __('overview.mission'), 'vision' => __('overview.vision'), 'goals' => __('direction_entry.goals')],
            ],
            'elements' => array_map(fn (array $stored) => [
                'id' => $stored['id'],
                'area' => $stored['area'],
                'parent' => $stored['parent_id'],
                'kind' => $stored['kind'],
                'name' => $stored['name'],
                'position' => $stored['position'],
                'modality' => $letters[$stored['modality']] ?? null,
                'unknownAllocation' => $stored['is_unknown_allocation'],
                'description' => $stored['description'],
                'rationale' => $stored['rationale'],
            ], $content['elements']),
            'statements' => $content['statements'],
            'modalities' => $modalities->map(fn (Modality $modality) => ['key' => $modality->key, 'letter' => $modality->letter, 'colour' => $modality->colour])->values(),
        ]);
    }

    /** @param array<string, mixed> $content */
    private function figures(Snapshot $snapshot, array $content): ?Figures
    {
        try {
            return new Figures(...$content['figures']);
        } catch (Throwable) {
            $this->snapshots->damaged($snapshot);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<array{name: string, id: string|int, amountCents: int, basisPoints: int}>
     */
    private function contributions(Figures $figures, array $content, string $element): array
    {
        $names = array_column($content['cost_records'], 'name', 'id');

        return array_map(fn (array $row) => ['name' => $names[$row['id']] ?? ''] + $row, CanvasFigures::contributions($figures, $element));
    }
}
