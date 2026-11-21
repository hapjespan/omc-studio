<?php

namespace App\Services\Structure;

use App\Models\Area;
use App\Support\FieldLimits;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class ElementFields
{
    private const DESCRIPTION_MAX = FieldLimits::TEXT;

    /** @param array<string, mixed> $changes */
    public function assertValid(Area $area, array $changes): void
    {
        if (isset($changes['description']) && mb_strlen((string) $changes['description']) > self::DESCRIPTION_MAX) {
            $this->refuse(__('validation.text_max', ['label' => __('fields.description'), 'max' => self::DESCRIPTION_MAX]));
        }

        if (isset($changes['icon'])) {
            $this->assertIcon($area, (string) $changes['icon']);
        }
    }

    private function assertIcon(Area $area, string $icon): void
    {
        if ($area->kind !== 'list') {
            $this->refuse(__('rule.icon_list_only'));
        }

        if (! in_array($icon, $this->icons(), true)) {
            $this->refuse(__('rule.icon_unknown'));
        }
    }

    /** @return list<string> */
    public function icons(): array
    {
        /** @var list<string> $icons */
        $icons = json_decode(File::get(resource_path('data/icons.json')), true, flags: JSON_THROW_ON_ERROR);

        return $icons;
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['rule' => $message]);
    }
}
