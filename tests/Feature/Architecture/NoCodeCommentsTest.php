<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class NoCodeCommentsTest extends TestCase
{
    private const TAGS = '/^@(param|return|var|throws|template(-[a-z]+)?|extends|implements|use|phpstan-[a-z-]+|psalm-[a-z-]+|property(-read|-write)?|method|mixin)\b/';

    private const SHAPES = [
        'param' => '/^@param\s+\S+\s+(\.\.\.)?\$\w+$/',
        'return' => '/^@return\s+\S+$/',
        'var' => '/^@var\s+\S+(\s+\$\w+)?$/',
        'throws' => '/^@throws\s+\S+$/',
    ];

    private const DIRECTIVE = '/^@?(phpstan-[a-z-]+|psalm-[a-z-]+|phpcs:[a-z]+|codeCoverageIgnore[a-zA-Z]*)\b/';

    public function test_every_tracked_php_file_holds_only_toolchain_directives_and_type_annotations(): void
    {
        $offences = [];
        $files = $this->files();

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            foreach (token_get_all((string) file_get_contents(base_path($file))) as $token) {
                if (! is_array($token) || ! in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                foreach ($this->offences($token[1], $token[0] === T_DOC_COMMENT) as $line) {
                    $offences[] = $file.':'.$token[2].' '.$line;
                }
            }
        }

        $this->assertSame([], $offences);
    }

    public function test_planted_comments_are_caught(): void
    {
        $this->assertSame(['explains'], $this->offences('// explains', false));
        $this->assertSame(['@todo explain this'], $this->offences('// @todo explain this', false));
        $this->assertSame(['@see Foo'], $this->offences("/**\n * @see Foo\n */", true));
        $this->assertSame(['explains'], $this->offences("/**\n * @var int\n * explains\n */", true));
        $this->assertSame(['@var int'], $this->offences('// @var int', false));
        $this->assertSame([], $this->offences("/**\n * @var int\n * @return list<string>\n */", true));
        $this->assertSame(['@param int $x the explanation'], $this->offences("/**\n * @param int \$x the explanation\n */", true));
        $this->assertSame([], $this->offences("/**\n * @param  array<string, array{a: int, b: string}>  \$x\n * @return array{a: int, b: string}\n */", true));
        $this->assertSame([''], $this->offences('//', false));
        $this->assertSame([], $this->offences('// @phpstan-ignore-next-line', false));
    }

    /**
     * @return list<string>
     */
    private function offences(string $comment, bool $docblock): array
    {
        $offences = [];

        foreach (preg_split('/\R/', $comment) ?: [] as $raw) {
            $text = trim((string) preg_replace('/^\s*(\/\*\*?|\/\/|#|\*\/|\*)|\*\/\s*$/', '', trim($raw)));

            if ($text === '') {
                $docblock || $offences[] = $text;

                continue;
            }

            $allowed = $docblock ? $this->annotation($text) : preg_match(self::DIRECTIVE, $text) === 1;

            $allowed || $offences[] = $text;
        }

        return $offences;
    }

    private function annotation(string $text): bool
    {
        if (preg_match(self::TAGS, $text, $tag) !== 1) {
            return false;
        }

        $shape = self::SHAPES[$tag[1]] ?? null;

        if ($shape === null) {
            return true;
        }

        foreach (['/<[^<>]*>|\{[^{}]*\}/', '/\([^()]*\)(?::\s*[^\s$]+)?/'] as $group) {
            do {
                $text = preg_replace($group, '', $text, -1, $count);
            } while ($count > 0);
        }

        return preg_match($shape, (string) $text) === 1;
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $listing = (string) shell_exec('git -C '.escapeshellarg(base_path()).' ls-files '.escapeshellarg('*.php'));

        return array_values(array_filter(explode("\n", $listing), fn (string $file) => $file !== '' && is_file(base_path($file))));
    }
}
