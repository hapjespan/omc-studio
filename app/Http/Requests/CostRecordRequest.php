<?php

namespace App\Http\Requests;

use App\Enums\CostKind;
use App\Rules\TrimmedText;
use Illuminate\Validation\Rule;

class CostRecordRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', new TrimmedText(__('fields.code'), 50)],
            'name' => ['required', 'string', new TrimmedText(__('fields.name'), 150)],
            'cost_kind' => ['required', Rule::enum(CostKind::class)],
            'supplier' => ['nullable', 'string', new TrimmedText(__('fields.supplier'), 150)],
            'owner_id' => ['required', 'integer'],
            'cost_centre' => ['nullable', 'string', new TrimmedText(__('fields.cost_centre'), 50)],
            'application_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', new TrimmedText(__('fields.description'), 2000)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('cost_records.name_required'),
            'cost_kind.required' => __('cost_records.cost_kind_required'),
            'cost_kind.enum' => __('cost_records.cost_kind_required'),
            'owner_id.required' => __('cost_records.owner_required'),
            'owner_id.integer' => __('cost_records.owner_required'),
            'application_id.integer' => __('cost_records.application_invalid'),
        ];
    }
}
