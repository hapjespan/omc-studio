<?php

namespace App\Http\Requests;

use App\Models\Area;
use Illuminate\Validation\Rule;

class QuickEntryRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer') ?? false;
    }

    protected function prepareForValidation(): void {}

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'area' => ['required', Rule::in(Area::KEYS)],
            'text' => ['nullable', 'string', 'max:100000'],
        ];
    }
}
