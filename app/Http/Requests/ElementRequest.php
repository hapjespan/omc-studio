<?php

namespace App\Http\Requests;

use App\Models\Area;
use Illuminate\Validation\Rule;

class ElementRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'kind' => [$creating ? 'required' : 'prohibited', Rule::in(['domain', 'cluster', 'activity'])],
            'area' => [$creating ? 'required' : 'sometimes', Rule::in(Area::KEYS)],
            'parent_id' => ['nullable', 'uuid'],
            'name' => [$creating ? 'required' : 'sometimes', 'string'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.required' => __('rule.name_required'), 'name.string' => __('rule.name_required')];
    }
}
