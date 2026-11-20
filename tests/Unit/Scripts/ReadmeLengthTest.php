<?php

namespace Tests\Unit\Scripts;

use PHPUnit\Framework\TestCase;

class ReadmeLengthTest extends TestCase
{
    private const LINES_PER_PAGE = 50;

    private const CHARACTERS_PER_LINE = 95;

    private const WORDS_PER_PAGE = 500;

    private function readme(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 3).'/README.md');
    }

    public function test_the_readme_fits_two_printed_pages_by_lines_and_words(): void
    {
        $printed = 0;
        foreach (explode("\n", $this->readme()) as $line) {
            $printed += max(1, (int) ceil(mb_strlen($line) / self::CHARACTERS_PER_LINE));
        }

        $this->assertLessThanOrEqual(2 * self::LINES_PER_PAGE, $printed, sprintf('%d printed lines', $printed));
        $this->assertLessThanOrEqual(2 * self::WORDS_PER_PAGE, str_word_count($this->readme()), 'words');
    }

    public function test_the_readme_names_every_topic_of_the_manual(): void
    {
        $readme = $this->readme();

        foreach (['## Starten', '## Eerste keer inloggen', '## Wachtwoord kwijt', '## Back-up en terugzetten', '## Importeren', '## Als de browser niet wil inloggen', '## Veelgestelde vragen'] as $heading) {
            $this->assertStringContainsString($heading, $readme);
        }
        foreach (['cp env.example .env', 'docker compose up -d', 'docker compose logs app', 'app:reset-admin-password', 'make backup', 'make restore', 'SESSION_SECURE_COOKIE=false', 'internet', 'sleutel'] as $needle) {
            $this->assertStringContainsString($needle, $readme);
        }
    }

    public function test_the_readme_is_the_manual_and_not_the_framework_text(): void
    {
        $this->assertStringNotContainsString('laravel.com', $this->readme());
        $this->assertStringNotContainsString("\u{2014}", $this->readme());
    }
}
