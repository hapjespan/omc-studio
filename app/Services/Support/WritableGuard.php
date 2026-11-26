<?php

namespace App\Services\Support;

use App\Enums\CanvasKind;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Organisation;
use Illuminate\Validation\ValidationException;

trait WritableGuard
{
    protected function assertWritable(Organisation|Canvas $aggregate): void
    {
        if ($aggregate instanceof Organisation) {
            $this->assertOrganisationActive($aggregate);

            return;
        }

        $this->assertStructureWritable($aggregate);

        if ($aggregate->kind === CanvasKind::Template) {
            $this->refuse('template_write');
        }
    }

    protected function assertCostRecordActive(CostRecord $record): void
    {
        if ($record->refresh()->archived_at !== null) {
            $this->refuse('cost_record_archived');
        }
    }

    protected function assertStructureWritable(Canvas $canvas): void
    {
        if ($canvas->is_blank_template) {
            $this->refuse('blank_template_write');
        }

        if ($canvas->archived_at !== null) {
            $this->refuse('canvas_archived');
        }

        $this->assertOrganisationActive($canvas->organisation);
    }

    private function assertOrganisationActive(?Organisation $organisation): void
    {
        if ($organisation?->archived_at !== null) {
            $this->refuse('organisation_archived');
        }
    }

    private function refuse(string $rule): never
    {
        throw ValidationException::withMessages(['rule' => __('rule.'.$rule)]);
    }
}
