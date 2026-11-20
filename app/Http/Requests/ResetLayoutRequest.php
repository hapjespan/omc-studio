<?php

namespace App\Http\Requests;

class ResetLayoutRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'scope' => ['required', 'in:element,domain,canvas'],
            'element_id' => ['required_unless:scope,canvas', 'nullable', 'string'],
        ];
    }
}
