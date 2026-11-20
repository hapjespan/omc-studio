<?php

namespace App\Http\Controllers;

use App\Enums\StatementKind;
use App\Http\Requests\StatementRequest;
use App\Models\Canvas;
use App\Models\Statement;
use App\Models\User;
use App\Services\Statements\DirectionStatements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StatementController extends Controller
{
    public function __construct(private readonly DirectionStatements $statements) {}

    public function text(StatementRequest $request, Canvas $canvas, string $kind): RedirectResponse
    {
        $this->statements->setText($canvas, StatementKind::from($kind), $request->input('body'));

        return back();
    }

    public function store(StatementRequest $request, Canvas $canvas): RedirectResponse
    {
        $this->statements->addGoal($canvas, $request->string('title')->toString(), $request->input('body'));

        return back();
    }

    public function update(StatementRequest $request, Canvas $canvas, Statement $goal): RedirectResponse
    {
        $this->owned($canvas, $goal);

        $this->statements->updateGoal($goal, $request->string('title', $goal->title)->toString(), $request->input('body', $goal->body));

        return back();
    }

    public function reorder(Request $request, Canvas $canvas, Statement $goal): RedirectResponse
    {
        $this->owned($canvas, $goal);

        $this->statements->reorder($goal, $request->validate(['direction' => ['required', 'in:up,down']])['direction']);

        return back();
    }

    public function destroy(Request $request, Canvas $canvas, Statement $goal): RedirectResponse
    {
        $this->owned($canvas, $goal);

        /** @var User $user */
        $user = $request->user();

        return back()->with('undo_token', $this->statements->deleteGoal($goal, $user));
    }

    private function owned(Canvas $canvas, Statement $goal): void
    {
        abort_unless($goal->canvas_id === $canvas->id && $goal->kind === StatementKind::Goal, 404);
    }
}
