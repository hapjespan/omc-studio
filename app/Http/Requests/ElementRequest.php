<?php

namespace App\Http\Requests;

use App\Models\Area;
use App\Models\Modality;
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
            'modality' => ['sometimes', 'string', Rule::in([...Modality::pluck('key')->all(), 'none'])],
            'rationale' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'confirm_rationale_clear' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.required' => __('rule.name_required'), 'name.string' => __('rule.name_required')];
    }
}
