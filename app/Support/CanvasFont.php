<?php

namespace App\Support;

enum CanvasFont: string
{
    case Inter = 'Inter';
    case SpaceGrotesk = 'Space Grotesk';

    /** @return list<int> */
    public static function weights(): array
    {
        return [400, 500, 600, 700];
    }

    public function supportsItalic(): bool
    {
        return $this === self::Inter;
    }

    /** @return list<string> */
    public function files(): array
    {
        $slug = strtolower(str_replace(' ', '-', $this->value));
        $styles = $this->supportsItalic() ? ['normal', 'italic'] : ['normal'];

        return collect(self::weights())
            ->flatMap(fn (int $weight) => array_map(fn (string $style) => "$slug-latin-$weight-$style.woff2", $styles))
            ->all();
    }
}
