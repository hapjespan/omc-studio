<?php

namespace App\Services\Canvas;

use App\Enums\CanvasKind;
use App\Models\Canvas;
use App\Services\Audit\AuditRemoval;
use App\Services\Support\SerialisedWrite;
use Illuminate\Validation\ValidationException;

class TemplateService
{
    public function __construct(
        private readonly SerialisedWrite $writes,
        private readonly CanvasLifecycle $lifecycle,
        private readonly AuditRemoval $removal,
    ) {}

    public function saveAsTemplate(Canvas $source, string $name): Canvas
    {
        return $this->writes->run([$source], function () use ($source, $name) {
            $this->assertNameFree($name);

            $template = Canvas::create(['kind' => CanvasKind::Template, 'name' => trim($name)]);

            $this->lifecycle->cloneElements($source, $template);
            $this->lifecycle->cloneStatements($source, $template);

            return $template;
        });
    }

    public function rename(Canvas $template, string $name): Canvas
    {
        return $this->writes->run([$template], function () use ($template, $name) {
            $this->assertEditable($template);
            $this->assertNameFree($name, $template);
            $template->update(['name' => trim($name)]);

            return $template;
        });
    }

    public function delete(Canvas $template, string $typedName): void
    {
        $this->writes->run([$template], function () use ($template, $typedName) {
            $this->assertEditable($template);

            if ($typedName !== $template->name) {
                throw ValidationException::withMessages(['confirmation' => __('rule.canvas_confirmation_mismatch')]);
            }

            $counts = $this->lifecycle->deletionCounts($template);

            $template->deleteQuietly();
            $this->removal->purge($template, $counts);
        });
    }

    private function assertEditable(Canvas $template): void
    {
        if ($template->is_blank_template) {
            throw ValidationException::withMessages(['rule' => __('rule.blank_template_write')]);
        }
    }

    private function assertNameFree(string $name, ?Canvas $except = null): void
    {
        $taken = Canvas::where('scope_key', 'template')
            ->where('name_normalised', Canvas::normalise($name))
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => __('templates.name_taken')]);
        }
    }
}
