<?php

namespace App\Services\Canvas;

use App\Enums\CanvasKind;
use App\Models\Canvas;
use App\Services\Support\SerialisedWrite;
use Illuminate\Validation\ValidationException;

class TemplateService
{
    public function __construct(
        private readonly SerialisedWrite $writes,
        private readonly CanvasLifecycle $lifecycle,
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
