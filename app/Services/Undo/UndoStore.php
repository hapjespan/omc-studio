<?php

namespace App\Services\Undo;

use App\Models\Canvas;
use App\Models\Element;
use App\Models\Statement;
use App\Models\UndoRecord;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Support\Str;

class UndoStore
{
    use WritableGuard;

    public const ELEMENT_DELETION = 'element_deletion';

    public const GOAL_DELETION = 'goal_deletion';

    private const LIFETIME_MINUTES = 10;

    private const TOKEN_LENGTH = 40;

    public function __construct(private readonly SerialisedWrite $writes, private readonly AuditContext $context) {}

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
        $record = UndoRecord::query()
            ->whereKey($token)
            ->where('user_id', $user->getKey())
            ->where('expires_at', '>', now())
            ->first();

        if ($record === null) {
            $this->refuse('undo_unavailable');
        }

        if ($record->kind === self::GOAL_DELETION) {
            $this->restoreGoal($record);

            return;
        }

        $rows = match ($record->kind) {
            self::ELEMENT_DELETION => $this->rows($record),
            default => $this->refuse('undo_unavailable'),
        };

        $canvas = Canvas::query()->whereKey($rows[0]['canvas_id'] ?? null)->first();

        if ($canvas === null) {
            $this->refuse('undo_parent_gone');
        }

        $this->writes->run([$canvas], function () use ($canvas, $record, $rows) {
            $this->assertStructureWritable($canvas);
            $this->assertEveryRuleStillHolds($rows);
            $this->context->during('undone', fn () => $this->insert($rows));

            $canvas->raiseStructureVersion();

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

    private function prune(): void
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
