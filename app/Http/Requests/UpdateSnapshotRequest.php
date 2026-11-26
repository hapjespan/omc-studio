<?php

namespace App\Http\Requests;

use App\Rules\TrimmedText;
use App\Support\FieldLimits;
use Illuminate\Validation\Validator;

class UpdateSnapshotRequest extends AppFormRequest
{
    private const EDITABLE = ['name', 'remark'];

    public function authorize(): bool
    {
        return $this->user()?->can('administer') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', new TrimmedText(__('fields.name'), FieldLimits::NAME)],
            'remark' => ['sometimes', 'nullable', 'string', new TrimmedText(__('fields.remark'), FieldLimits::TEXT)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.required' => __('snap.name_required'), 'name.string' => __('snap.name_required')];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->post()), self::EDITABLE) !== []) {
                $validator->errors()->add('rule', __('snap.field_not_editable'));
            }
        }];
    }
}
