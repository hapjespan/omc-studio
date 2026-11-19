<?php

namespace App\Http\Requests;

class ListEntryDeleteRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['replace_with' => ['nullable', 'string', 'regex:/^(none|[1-9][0-9]*)$/']];
    }
}
