<?php

namespace App\Http\Requests;

use App\Enums\CanvasKind;
use App\Rules\TrimmedText;
use Illuminate\Validation\Rule;

class CanvasRequest extends AppFormRequest
{
    public const SOURCES = ['template', 'copy'];

    public function authorize(): bool
    {
        return $this->user()?->can('administer') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $name = ['required', 'string', new TrimmedText(__('fields.name'), 150)];

        return match ($this->method()) {
            'POST' => [
                'source' => ['required', Rule::in(static::SOURCES)],
                'name' => $name,
                'organisation_id' => ['required', 'integer', Rule::exists('organisations', 'id')],
                'template_id' => [Rule::requiredIf($this->input('source') === 'template'), 'integer', Rule::exists('canvases', 'id')->where('kind', CanvasKind::Template->value)],
                'canvas_id' => [Rule::requiredIf($this->input('source') === 'copy'), 'integer', Rule::exists('canvases', 'id')->where('kind', CanvasKind::Canvas->value)],
            ],
            'DELETE' => ['confirmation' => ['required', 'string']],
            default => ['name' => $name],
        };
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.required' => __('canvas_lifecycle.name_required'), 'confirmation.required' => __('rule.canvas_confirmation_mismatch')];
    }
}
