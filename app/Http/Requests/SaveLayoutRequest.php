<?php

namespace App\Http\Requests;

class SaveLayoutRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'structure_version' => ['required', 'integer', 'min:0'],
            'layout_version' => ['required', 'integer', 'min:0'],
            'format_version' => ['required', 'integer', 'in:1'],
            'areas' => ['present', 'array'],
            'areas.*' => ['array'],
            'elements' => ['present', 'array'],
            'elements.*' => ['array'],
        ];
    }
}
