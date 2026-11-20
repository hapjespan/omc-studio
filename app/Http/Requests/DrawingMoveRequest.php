<?php

namespace App\Http\Requests;

class DrawingMoveRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'element_id' => ['required', 'uuid'],
            'target_id' => ['required', 'uuid'],
            'structure_version' => ['required', 'integer', 'min:0'],
            'layout_version' => ['required', 'integer', 'min:0'],
            'layout' => ['present', 'nullable', 'array'],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
