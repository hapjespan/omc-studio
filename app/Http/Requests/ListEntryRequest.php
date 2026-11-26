<?php

namespace App\Http\Requests;

use App\Rules\TrimmedText;
use App\Support\FieldLimits;

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
            'name' => ['required', 'string', new TrimmedText(__('fields.name'), FieldLimits::NAME)],
            'supplier' => ['nullable', 'string', new TrimmedText(__('fields.supplier'), FieldLimits::NAME)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.required' => __('lists.name_required')];
    }
}
