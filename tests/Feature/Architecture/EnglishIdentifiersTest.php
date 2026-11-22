<?php

namespace Tests\Feature\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class EnglishIdentifiersTest extends TestCase
{
    private const DUTCH = '/\w*(invoeren|tekenen|bekijken|beheer|kostenpost|toewijzen|versies|koers|organisatie|gebruiker|wachtwoord|opslaan)\w*/i';

    public function test_no_identifier_in_php_and_scripts_contains_a_dutch_word(): void
    {
        $found = [];

        foreach (['app', 'tests', 'resources/js'] as $dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                $extension = $file->getExtension();

                if (! in_array($extension, ['php', 'vue', 'ts'], true) || $file->getPathname() === __FILE__) {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());

                foreach ($extension === 'php' ? $this->phpIdentifiers($source) : $this->scriptIdentifiers($source, $extension) as $identifier) {
                    $found[] = substr($file->getPathname(), strlen(base_path()) + 1).' '.$identifier;
                }
            }
        }

        sort($found);
        $this->assertSame([], $found);
    }

    public function test_the_scanners_catch_planted_identifiers_and_skip_strings_and_comments(): void
    {
        $this->assertSame(['test_beheer_entry'], $this->phpIdentifiers("<?php function test_beheer_entry() { \$a = 'beheer'; // beheer\n}"));
        $this->assertSame(['beheer'], $this->scriptIdentifiers("const beheer = 'beheer'; seed({ Beheer: 1 }); // beheer\n/* beheer */ test('beheer', () => `beheer`);", 'ts'));
        $this->assertSame([], $this->scriptIdentifiers('<template><p>beheer</p></template><script setup lang="ts">const a = 1;</script>', 'vue'));
    }

    /**
     * @return list<string>
     */
    private function phpIdentifiers(string $source): array
    {
        $found = [];

        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_STRING, T_VARIABLE], true) && preg_match(self::DUTCH, $token[1])) {
                $found[] = $token[1];
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private function scriptIdentifiers(string $source, string $extension): array
    {
        if ($extension === 'vue') {
            preg_match_all('/<script\b[^>]*>(.*?)<\/script>/s', $source, $blocks);
            $source = implode("\n", $blocks[1]);
        }

        $code = preg_replace('~/\*.*?\*/|(?<![:\\w])//[^\n]*|\'(?:\\\\.|[^\'\\\\\n])*\'|"(?:\\\\.|[^"\\\\\n])*"|`(?:\\\\.|[^`\\\\])*`|[(,=:]\\s*/(?![/*])(?:\\\\.|\\[(?:\\\\.|[^\\]])*\\]|[^/\\\\\n\\[])+/[a-z]*~s', ' ', $source);
        preg_match_all('/\b(?!(?-i:[A-Z])\w*\s*:)'.substr(self::DUTCH, 1), (string) $code, $matches);

        return $matches[0];
    }
}
