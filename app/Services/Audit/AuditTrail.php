<?php

namespace App\Services\Audit;

use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditTrail
{
    public function __construct(private readonly AuditContext $context) {}

    /** @param array<string, array{mixed, mixed}> $changes */
    public function record(Model $subject, string $action, array $changes = [], ?Canvas $canvas = null): AuditEntry
    {
        return AuditEntry::create([
            'occurred_at' => now(),
            'actor_type' => $this->context->actorType(),
            'actor_id' => $this->context->actorId(),
            'action' => $action,
            'subject_type' => $this->kind($subject),
            'subject_id' => (string) $subject->getKey(),
            'subject_label' => Str::limit($this->label($subject), 150, ''),
            'organisation_id' => $subject instanceof Organisation ? $subject->getKey() : ($subject->getAttribute('organisation_id') ?? $canvas?->organisation_id),
            'canvas_id' => $subject instanceof Canvas ? $subject->getKey() : ($subject->getAttribute('canvas_id') ?? $canvas?->getKey()),
            'changes' => $changes,
        ]);
    }

    public function kind(Model $subject): string
    {
        return Str::snake(class_basename($subject));
    }

    private function label(Model $subject): string
    {
        $label = $subject->getAttribute('name') ?? $subject->getAttribute('title') ?? $subject->getAttribute('key') ?? $subject->getKey();

        return (string) $label;
    }
}
