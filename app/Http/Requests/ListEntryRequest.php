<?php

namespace App\Http\Requests;

use App\Rules\TrimmedText;

class ListEntryRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', new TrimmedText(__('fields.name'), 150)],
            'supplier' => ['nullable', 'string', new TrimmedText(__('fields.supplier'), 150)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.required' => __('lists.name_required')];
    }
}
