<?php

namespace App\Rules;

use App\Support\LayoutLimits;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class LayoutStyle implements ValidationRule
{
    private const COLOUR = '/^#[0-9A-Fa-f]{6}$/';

    private const CHOICES = [
        'border_style' => ['solid', 'dashed'],
        'font' => ['inter', 'space_grotesk'],
        'font_weight' => ['normal', 'bold'],
        'align_horizontal' => ['left', 'center', 'right'],
        'align_vertical' => ['top', 'middle', 'bottom'],
    ];

    private const COLOURS = ['fill', 'border_colour', 'text_colour'];

    public function __construct(private string $name) {}

    /** @return list<string> */
    public function violations(mixed $style): array
    {
        if (! is_array($style)) {
            return [__('layout.style.invalid', ['name' => $this->name])];
        }

        $messages = [];

        foreach ($style as $key => $value) {
            if (! $this->accepts((string) $key, $value)) {
                $messages[] = __('layout.style.'.(array_key_exists($key, $this->known()) ? $key : 'unknown'), ['name' => $this->name, 'key' => $key]);
            }
        }

        return $messages;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach ($this->violations($value) as $message) {
            $fail($message);
        }
    }

    /** @return array<string, true> */
    private function known(): array
    {
        return array_fill_keys([...self::COLOURS, ...array_keys(LayoutLimits::style()), ...array_keys(self::CHOICES), 'italic'], true);
    }

    private function accepts(string $key, mixed $value): bool
    {
        return match (true) {
            in_array($key, self::COLOURS, true) => is_string($value) && preg_match(self::COLOUR, $value) === 1,
            isset(LayoutLimits::style()[$key]) => is_int($value) && $value >= LayoutLimits::style()[$key][0] && $value <= LayoutLimits::style()[$key][1],
            isset(self::CHOICES[$key]) => in_array($value, self::CHOICES[$key], true),
            $key === 'italic' => is_bool($value),
            default => false,
        };
    }
}
