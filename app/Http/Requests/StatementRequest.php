<?php

namespace App\Http\Requests;

class StatementRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $kind = $this->route('kind');

        if (is_string($kind)) {
            return ['body' => ['nullable', 'string', 'max:2000']];
        }

        return [
            'title' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $label = __('direction_entry.kind_'.($this->route('kind') ?? 'goal'));

        return [
            'body.max' => __('direction_entry.too_long', ['label' => $label]),
            'title.required' => __('direction_entry.title_required'),
            'title.string' => __('direction_entry.title_required'),
            'title.max' => __('direction_entry.title_too_long'),
        ];
    }
}
