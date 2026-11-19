<?php

namespace App\Services\Structure;

use App\Models\Element;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class ElementFields
{
    private const DESCRIPTION_MAX = 2000;

    /** @param array<string, mixed> $changes */
    public function assertValid(Element $element, array $changes): void
    {
        if (isset($changes['description']) && mb_strlen((string) $changes['description']) > self::DESCRIPTION_MAX) {
            $this->refuse(__('validation.text_max', ['label' => __('fields.description'), 'max' => self::DESCRIPTION_MAX]));
        }

        if (isset($changes['icon'])) {
            $this->assertIcon($element, (string) $changes['icon']);
        }
    }

    private function assertIcon(Element $element, string $icon): void
    {
        if ($element->area->kind !== 'list') {
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
