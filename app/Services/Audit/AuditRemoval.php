<?php

namespace App\Services\Audit;

use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\Organisation;

class AuditRemoval
{
    public function __construct(private readonly AuditTrail $trail) {}

    /** @param array<string, int> $counts */
    public function purge(Organisation|Canvas $subject, array $counts = []): AuditEntry
    {
        $column = $subject instanceof Organisation ? 'organisation_id' : 'canvas_id';
        $counts['audit_entries'] = AuditEntry::where($column, $subject->getKey())->count();
        AuditEntry::where($column, $subject->getKey())->delete();

        $changes = ['name' => [$subject->name, null]];

        foreach ($counts as $key => $count) {
            $changes[$key] = [$count, null];
        }

        return $this->trail->record($subject, 'deleted', $changes);
    }
}
