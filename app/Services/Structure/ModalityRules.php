<?php

namespace App\Services\Structure;

use App\Models\Element;
use App\Models\Modality;
use Illuminate\Validation\ValidationException;

class ModalityRules
{
    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function changes(Element $element, array $fields): array
    {
        $touched = array_key_exists('modality_id', $fields) || array_key_exists('rationale', $fields);

        if (! $touched) {
            return [];
        }

        $modalityId = array_key_exists('modality_id', $fields) ? $fields['modality_id'] : $element->modality_id;
        $rationale = array_key_exists('rationale', $fields) ? trim((string) $fields['rationale']) : (string) $element->rationale;
        $rationale = $rationale === '' ? null : $rationale;

        if ($element->area->kind !== 'activities') {
            if ($modalityId !== null || $rationale !== null) {
                $this->refuse('list_area');
            }

            return [];
        }

        if ($modalityId !== null && ! Modality::whereKey($modalityId)->exists()) {
            $this->refuse('unknown');
        }

        if ($modalityId === null && $element->modality_id !== null && $element->rationale !== null && empty($fields['confirm_rationale_clear'])) {
            $this->refuse('confirm_clear');
        }

        if ($modalityId === null && $rationale !== null && $element->modality_id === null) {
            $this->refuse('rationale_needs_modality');
        }

        return ['modality_id' => $modalityId, 'rationale' => $modalityId === null ? null : $rationale];
    }

    private function refuse(string $rule): never
    {
        throw ValidationException::withMessages(['rule' => __('modality_entry.'.$rule)]);
    }
}
