<?php

namespace App\Services\Canvas;

use App\Enums\CanvasKind;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Organisation;
use App\Models\Statement;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditRemoval;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Validation\ValidationException;

class CanvasLifecycle
{
    use WritableGuard;

    public function __construct(
        private readonly SerialisedWrite $writes,
        private readonly AuditContext $context,
        private readonly AuditRemoval $removal,
    ) {}

    public function createFromTemplate(Organisation $organisation, Canvas $template, string $name): Canvas
    {
        return $this->writes->run([$organisation, $template], function () use ($organisation, $template, $name) {
            $this->assertWritable($organisation);

            return $this->clone($template, $organisation, $name);
        });
    }

    public function copy(Canvas $source, Organisation $target, string $name): Canvas
    {
        return $this->writes->run([$target, $source], function () use ($source, $target, $name) {
            $this->assertWritable($target);

            return $this->clone($source, $target, $name);
        });
    }

    public function rename(Canvas $canvas, string $name): Canvas
    {
        return $this->writes->run(array_filter([$canvas->organisation, $canvas]), function () use ($canvas, $name) {
            $this->assertWritable($canvas);
            $this->assertNameFree($canvas->organisation, $name, $canvas);
            $canvas->update(['name' => trim($name)]);

            return $canvas;
        });
    }

    public function archive(Canvas $canvas): Canvas
    {
        return $this->stamp($canvas, 'archived', now(), fn () => $canvas->archived_at === null);
    }

    public function restore(Canvas $canvas): Canvas
    {
        return $this->stamp($canvas, 'restored', null, fn () => $canvas->archived_at !== null);
    }

    public function delete(Canvas $canvas, string $typedName): void
    {
        $this->writes->run([$canvas], function () use ($canvas, $typedName) {
            if ($canvas->kind === CanvasKind::Template) {
                $this->refuse('template_write');
            }

            $this->assertOrganisationActive($canvas->organisation);

            if ($typedName !== $canvas->name) {
                throw ValidationException::withMessages(['confirmation' => __('rule.canvas_confirmation_mismatch')]);
            }

            $counts = $this->deletionCounts($canvas);

            $canvas->deleteQuietly();
            $this->removal->purge($canvas, $counts);
        });
    }

    /** @return array<string, int> */
    public function deletionCounts(Canvas $canvas): array
    {
        return [
            'elements' => Element::where('canvas_id', $canvas->getKey())->count(),
            'statements' => Statement::where('canvas_id', $canvas->getKey())->count(),
        ];
    }

    private function stamp(Canvas $canvas, string $action, mixed $value, callable $applies): Canvas
    {
        return $this->writes->run([$canvas], function () use ($canvas, $action, $value, $applies) {
            if ($canvas->kind === CanvasKind::Template) {
                $this->refuse('template_write');
            }

            $this->assertOrganisationActive($canvas->organisation);

            if ($applies()) {
                $this->context->during($action, fn () => $canvas->update(['archived_at' => $value]));
            }

            return $canvas;
        });
    }

    protected function clone(Canvas $source, Organisation $organisation, string $name): Canvas
    {
        $this->assertNameFree($organisation, $name);

        $canvas = Canvas::create(['organisation_id' => $organisation->getKey(), 'kind' => CanvasKind::Canvas, 'name' => trim($name)]);

        $this->cloneElements($source, $canvas);
        $this->cloneStatements($source, $canvas);
        UnknownAllocationDomain::ensure($canvas);

        return $canvas;
    }

    protected function cloneElements(Canvas $source, Canvas $canvas, ?string $parentId = null, ?string $newParentId = null): void
    {
        $elements = Element::where('canvas_id', $source->getKey())
            ->where('is_unknown_allocation', false)
            ->whereNull('archived_at')
            ->where('parent_id', $parentId)
            ->orderBy('position')
            ->get();

        foreach ($elements as $element) {
            $copy = Element::create([
                ...$element->only(['area_id', 'kind', 'name', 'description', 'modality_id', 'rationale', 'icon', 'position']),
                'canvas_id' => $canvas->getKey(),
                'parent_id' => $newParentId,
            ]);

            $this->cloneElements($source, $canvas, $element->getKey(), $copy->getKey());
        }
    }

    protected function cloneStatements(Canvas $source, Canvas $canvas): void
    {
        foreach (Statement::where('canvas_id', $source->getKey())->orderBy('position')->get() as $statement) {
            Statement::create([...$statement->only(['kind', 'title', 'body', 'position']), 'canvas_id' => $canvas->getKey()]);
        }
    }

    private function assertNameFree(Organisation $organisation, string $name, ?Canvas $except = null): void
    {
        $taken = Canvas::where('scope_key', 'organisation:'.$organisation->getKey())
            ->where('name_normalised', Canvas::normalise($name))
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => __('rule.canvas_name_taken')]);
        }
    }
}
