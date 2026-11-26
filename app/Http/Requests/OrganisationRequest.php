<?php

namespace App\Http\Requests;

use App\Rules\TrimmedText;
use App\Support\FieldLimits;

class OrganisationRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $label = __('fields.name');

        return [
            'name' => ['required', 'string', new TrimmedText($label, FieldLimits::NAME)],
            'logo' => ['nullable', 'file'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.required' => __('organisations.name_required'), 'logo.file' => __('organisations.logo_invalid')];
    }
}
