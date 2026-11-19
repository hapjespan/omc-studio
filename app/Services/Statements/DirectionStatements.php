<?php

namespace App\Services\Statements;

use App\Enums\StatementKind;
use App\Models\Canvas;
use App\Models\Statement;
use App\Models\User;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use App\Services\Undo\UndoStore;
use InvalidArgumentException;

class DirectionStatements
{
    use WritableGuard;

    public function __construct(private readonly SerialisedWrite $writes, private readonly UndoStore $undo) {}

    public function setText(Canvas $canvas, StatementKind $kind, ?string $body): void
    {
        if ($kind === StatementKind::Goal) {
            throw new InvalidArgumentException('A goal is not a single text');
        }

        $this->writes->run([$canvas], function () use ($canvas, $kind, $body) {
            $this->assertStructureWritable($canvas);

            $current = Statement::where('canvas_id', $canvas->id)->where('kind', $kind)->first();

            if ($body === null || $body === '') {
                $current?->delete();

                return;
            }

            if ($current !== null) {
                $current->update(['body' => $body]);

                return;
            }

            Statement::create(['canvas_id' => $canvas->id, 'kind' => $kind, 'body' => $body, 'position' => 0]);
        });
    }

    public function addGoal(Canvas $canvas, string $title, ?string $body): Statement
    {
        return $this->writes->run([$canvas], function () use ($canvas, $title, $body) {
            $this->assertStructureWritable($canvas);

            $last = Statement::where('canvas_id', $canvas->id)->where('kind', StatementKind::Goal)->max('position');

            return Statement::create(['canvas_id' => $canvas->id, 'kind' => StatementKind::Goal, 'title' => $title, 'body' => $body ?? '', 'position' => $last === null ? 0 : (int) $last + 1]);
        });
    }

    public function updateGoal(Statement $goal, string $title, ?string $body): void
    {
        $canvas = $goal->canvas;

        $this->writes->run([$canvas], function () use ($canvas, $goal, $title, $body) {
            $this->assertStructureWritable($canvas);

            $goal->update(['title' => $title, 'body' => $body ?? '']);
        });
    }

    public function reorder(Statement $goal, string $direction): void
    {
        $step = match ($direction) {
            'up' => -1,
            'down' => 1,
            default => throw new InvalidArgumentException('Unknown direction '.$direction),
        };

        $canvas = $goal->canvas;

        $this->writes->run([$canvas], function () use ($canvas, $goal, $step) {
            $this->assertStructureWritable($canvas);

            $goals = Statement::where('canvas_id', $canvas->id)->where('kind', StatementKind::Goal)->orderBy('position')->orderBy('id')->get()->values();
            $from = $goals->search(fn (Statement $other) => $other->is($goal));
            $to = $from + $step;

            if (! isset($goals[$to])) {
                return;
            }

            $order = $goals->all();
            [$order[$from], $order[$to]] = [$order[$to], $order[$from]];

            foreach ($order as $index => $each) {
                if ($each->position !== $index) {
                    $each->update(['position' => $index]);
                }
            }
        });
    }

    public function deleteGoal(Statement $goal, User $user): string
    {
        $canvas = $goal->canvas;

        return $this->writes->run([$canvas], function () use ($canvas, $goal, $user) {
            $this->assertStructureWritable($canvas);

            $token = $this->undo->remember(UndoStore::GOAL_DELETION, ['statements' => [$goal->getAttributes()]], $user);

            $goal->delete();

            return $token;
        });
    }
}
