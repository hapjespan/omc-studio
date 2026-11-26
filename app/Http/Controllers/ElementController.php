<?php

namespace App\Http\Controllers;

use App\Enums\ElementKind;
use App\Http\Requests\ElementRequest;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Modality;
use App\Models\User;
use App\Services\Structure\ElementArchive;
use App\Services\Structure\ElementDeletion;
use App\Services\Structure\ElementTree;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ElementController extends Controller
{
    public function __construct(
        private readonly ElementTree $tree,
        private readonly ElementArchive $archive,
        private readonly ElementDeletion $deletion,
    ) {}

    public function store(ElementRequest $request, Canvas $canvas): RedirectResponse
    {
        $this->tree->create(
            $canvas,
            Area::query()->where('key', $request->string('area'))->firstOrFail(),
            ElementKind::from($request->string('kind')->toString()),
            $request->input('parent_id'),
            $request->safe()->only(['name', 'description']),
        );

        return back();
    }

    public function update(ElementRequest $request, Canvas $canvas, Element $element): RedirectResponse
    {
        $this->owned($canvas, $element);

        DB::transaction(function () use ($request, $element) {
            $fields = $request->safe()->only(['name', 'description', 'icon']);

            $fields += $this->modalityFields($request);

            if ($fields !== []) {
                $this->tree->update($element, $fields);
            }

            if ($request->filled('parent_id')) {
                $this->tree->moveUnder($element, Element::query()->where('canvas_id', $element->canvas_id)->findOrFail($request->input('parent_id')));
            } elseif ($request->filled('area')) {
                $this->tree->moveUnder($element, Area::query()->where('key', $request->input('area'))->firstOrFail());
            }
        });

        return back();
    }

    public function reorder(Request $request, Canvas $canvas, Element $element): RedirectResponse
    {
        $this->owned($canvas, $element);

        $this->tree->reorder($element, $request->validate(['direction' => ['required', 'in:up,down']])['direction']);

        return back();
    }

    public function archive(Canvas $canvas, Element $element): RedirectResponse
    {
        $this->owned($canvas, $element);

        $this->archive->archive($element);

        return back();
    }

    public function restore(Canvas $canvas, Element $element): RedirectResponse
    {
        $this->owned($canvas, $element);

        $this->archive->restore($element);

        return back();
    }

    public function destroy(Request $request, Canvas $canvas, Element $element): RedirectResponse
    {
        $this->owned($canvas, $element);

        /** @var User $user */
        $user = $request->user();

        $choice = $request->validate(['cluster_children' => ['nullable', 'in:delete,lift']])['cluster_children'] ?? null;

        return back()->with('undo_token', $this->deletion->delete($element, $user, $choice));
    }

    /** @return array<string, mixed> */
    private function modalityFields(ElementRequest $request): array
    {
        $fields = $request->safe()->only(['rationale', 'confirm_rationale_clear']);

        if ($request->has('modality')) {
            $fields['modality_id'] = $request->input('modality') === 'none' ? null : Modality::where('key', $request->input('modality'))->value('id');
        }

        return $fields;
    }

    private function owned(Canvas $canvas, Element $element): void
    {
        abort_unless($element->canvas_id === $canvas->id, 404);
    }
}
