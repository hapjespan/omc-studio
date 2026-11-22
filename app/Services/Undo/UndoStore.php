<?php

namespace App\Services\Undo;

use App\Models\Application;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\Statement;
use App\Models\UndoRecord;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Layout\LayoutRows;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Support\Str;

class UndoStore
{
    use WritableGuard;

    public const ELEMENT_DELETION = 'element_deletion';

    public const GOAL_DELETION = 'goal_deletion';

    public const OWNER_DELETION = 'owner_deletion';

    public const APPLICATION_DELETION = 'application_deletion';

    public const COST_RECORD_DELETION = 'cost_record_deletion';

    public const YEARLY_FIGURES_DELETION = 'yearly_figures_deletion';

    public const ALLOCATION_SET_CLEARED = 'allocation_set_cleared';

    private const LIFETIME_MINUTES = 10;

    private const TOKEN_LENGTH = 40;

    public function __construct(
        private readonly SerialisedWrite $writes,
        private readonly AuditContext $context,
        private readonly LayoutRows $layouts,
        private readonly CostRecordUndo $costRecords,
        private readonly AllocationSetUndo $allocationSets,
    ) {}

    /** @param array<string, mixed> $payload */
    public function remember(string $kind, array $payload, User $user): string
    {
        $this->prune();

        $token = Str::random(self::TOKEN_LENGTH);

        UndoRecord::create([
            'token' => $token,
            'user_id' => $user->id,
            'kind' => $kind,
            'payload' => $payload,
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES),
        ]);

        return $token;
    }

    public function restore(string $token, User $user): void
    {
        $this->prune();

        $record = UndoRecord::query()
            ->whereKey($token)
            ->where('user_id', $user->getKey())
            ->where('expires_at', '>', now())
            ->first();

        if ($record === null) {
            $this->refuse('undo_unavailable');
        }

        match ($record->kind) {
            self::ELEMENT_DELETION => $this->restoreElements($record),
            self::GOAL_DELETION => $this->restoreGoal($record),
            self::OWNER_DELETION => $this->restoreListEntry($record, Owner::class),
            self::APPLICATION_DELETION => $this->restoreListEntry($record, Application::class),
            self::COST_RECORD_DELETION, self::YEARLY_FIGURES_DELETION => $this->costRecords->restore($record),
            self::ALLOCATION_SET_CLEARED => $this->allocationSets->restore($record),
            default => $this->refuse('undo_unavailable'),
        };
    }

    private function restoreElements(UndoRecord $record): void
    {
        $rows = $this->rows($record);

        $canvas = Canvas::query()->whereKey($rows[0]['canvas_id'] ?? null)->first();

        if ($canvas === null) {
            $this->refuse('undo_parent_gone');
        }

        $this->writes->run([$canvas], function () use ($canvas, $record, $rows) {
            $this->assertStructureWritable($canvas);
            $this->assertEveryRuleStillHolds($rows);
            $this->context->during('undone', fn () => $this->insert($rows));

            $canvas->raiseStructureVersion();

            $this->layouts->restore($canvas, $this->storedLayouts($record));

            $record->delete();
        });
    }

    /** @param class-string<Application|Owner> $model */
    private function restoreListEntry(UndoRecord $record, string $model): void
    {
        /** @var array<string, mixed> $row */
        $row = ((array) $record->payload)['entry'];

        $organisation = Organisation::query()->whereKey($row['organisation_id'] ?? null)->first();

        if ($organisation === null) {
            $this->refuse('undo_parent_gone');
        }

        $this->writes->run([$organisation], function () use ($model, $organisation, $record, $row) {
            $this->assertWritable($organisation);

            $taken = $model::query()
                ->where('organisation_id', $organisation->getKey())
                ->where('name_normalised', $model::normalise((string) $row['name']))
                ->exists();

            if ($taken) {
                $this->refuse('list_name_taken');
            }

            $this->context->during('undone', fn () => (new $model)->forceFill($row)->save());

            $record->delete();
        });
    }

    private function restoreGoal(UndoRecord $record): void
    {
        /** @var array<string, mixed> $row */
        $row = ((array) $record->payload)['statements'][0];

        $canvas = Canvas::query()->whereKey($row['canvas_id'])->first();

        if ($canvas === null) {
            $this->refuse('undo_parent_gone');
        }

        $this->writes->run([$canvas], function () use ($canvas, $record, $row) {
            $this->assertStructureWritable($canvas);
            $this->context->during('undone', function () use ($canvas, $row) {
                $later = Statement::where('canvas_id', $canvas->id)->where('kind', $row['kind'])->where('position', '>=', $row['position'])->get();

                foreach ($later as $statement) {
                    $statement->update(['position' => $statement->position + 1]);
                }

                (new Statement)->forceFill($row)->save();
            });

            $record->delete();
        });
    }

    public function prune(): void
    {
        UndoRecord::query()->where('expires_at', '<', now())->delete();
    }

    /** @return non-empty-list<array<string, mixed>> */
    private function rows(UndoRecord $record): array
    {
        /** @var non-empty-list<array<string, mixed>> $rows */
        $rows = ((array) $record->payload)['elements'];

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function storedLayouts(UndoRecord $record): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = ((array) $record->payload)['layouts'] ?? [];

        return $rows;
    }

    /** @param list<array<string, mixed>> $rows */
    private function assertEveryRuleStillHolds(array $rows): void
    {
        $restored = array_column($rows, 'id');

        foreach ($rows as $row) {
            if (in_array($row['parent_id'], $restored, true)) {
                continue;
            }

            if ($row['parent_id'] !== null && Element::query()->whereKey($row['parent_id'])->whereNull('archived_at')->doesntExist()) {
                $this->refuse('undo_parent_gone');
            }

            $taken = Element::query()
                ->where('canvas_id', $row['canvas_id'])
                ->where('area_id', $row['area_id'])
                ->where('parent_id', $row['parent_id'])
                ->where('name_normalised', Element::normalise((string) $row['name']))
                ->whereNull('archived_at')
                ->exists();

            if ($taken) {
                $this->refuse('element_name_taken');
            }
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function insert(array $rows): void
    {
        foreach ($rows as $row) {
            (new Element)->forceFill($row)->save();
        }
    }
}
