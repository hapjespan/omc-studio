<?php

namespace App\Http\Requests;

use App\Models\Canvas;
use App\Models\FiscalYear;
use App\Rules\TrimmedText;
use App\Support\FieldLimits;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;

class StoreSnapshotRequest extends AppFormRequest
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
            'fiscal_year' => ['required', 'integer', Rule::exists('fiscal_years', 'year')->where('organisation_id', $this->canvas()->organisation_id)],
            'remark' => ['nullable', 'string', new TrimmedText(__('fields.remark'), FieldLimits::TEXT)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('snap.name_required'),
            'name.string' => __('snap.name_required'),
            'fiscal_year.required' => __('snap.fiscal_year_invalid'),
            'fiscal_year.integer' => __('snap.fiscal_year_invalid'),
            'fiscal_year.exists' => __('snap.fiscal_year_invalid'),
        ];
    }

    public function fiscalYear(): FiscalYear
    {
        return FiscalYear::where('organisation_id', $this->canvas()->organisation_id)->where('year', $this->integer('fiscal_year'))->firstOrFail();
    }

    private function canvas(): Canvas
    {
        $canvas = $this->route('canvas');

        return $canvas instanceof Canvas ? $canvas : throw new ModelNotFoundException;
    }
}
