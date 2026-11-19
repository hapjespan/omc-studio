<?php

namespace App\Services\Exchange;

use App\Enums\CanvasKind;
use App\Models\Canvas;
use App\Models\Organisation;

class FullExport
{
    public const FORMAT = 'omc-studio-full';

    public function __construct(private readonly CanvasExport $export) {}

    /** @return array<string, mixed> */
    public function build(): array
    {
        return [
            'format' => self::FORMAT,
            'format_version' => 1,
            'exported_at' => now()->toIso8601String(),
            'organisations' => Organisation::orderBy('id')->get()->map(fn (Organisation $organisation) => $this->organisation($organisation))->all(),
            'templates' => Canvas::where('kind', CanvasKind::Template)->where('is_blank_template', false)->orderBy('id')->get()
                ->map(fn (Canvas $template) => $this->export->templateBlock($template))->all(),
        ];
    }

    public function fileName(): string
    {
        return 'full-export-'.now()->format('Y-m-d').'.json';
    }

    /** @return array<string, mixed> */
    private function organisation(Organisation $organisation): array
    {
        $years = $this->export->years($organisation);

        return [
            'archived_at' => $organisation->archived_at?->toIso8601String(),
            'organisation' => $this->export->organisation($organisation, $years),
            'cost_records' => $this->export->costRecords($organisation, $years),
            'canvases' => Canvas::where('organisation_id', $organisation->getKey())->orderBy('id')->get()
                ->map(fn (Canvas $canvas) => ['archived_at' => $canvas->archived_at?->toIso8601String(), ...$this->export->canvasBlock($canvas, $years)])->all(),
        ];
    }
}
