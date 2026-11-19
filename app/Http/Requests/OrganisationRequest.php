<?php

namespace App\Http\Requests;

use App\Rules\TrimmedText;

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
            'name' => ['required', 'string', new TrimmedText($label, 150)],
            'logo' => ['nullable', 'file'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.required' => __('organisations.name_required'), 'logo.file' => __('organisations.logo_invalid')];
    }
}
