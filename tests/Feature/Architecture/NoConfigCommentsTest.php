<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class NoConfigCommentsTest extends TestCase
{
    public function test_config_files_carry_no_comments(): void
    {
        $this->assertSame([], $this->commented([config_path()]));
    }

    public function test_source_files_carry_no_comments(): void
    {
        $roots = array_map('base_path', ['app', 'bootstrap', 'routes', 'database', 'public', 'tests']);

        $this->assertSame([], $this->commented($roots));
    }

    public function test_the_console_entry_point_carries_no_comments(): void
    {
        $tokens = token_get_all((string) file_get_contents(base_path('artisan')));

        $this->assertSame([], array_filter($tokens, fn ($token) => is_array($token) && $token[0] === T_COMMENT));
    }

    /**
     * @param  list<string>  $roots
     * @return list<string>
     */
    private function commented(array $roots): array
    {
        $commented = [];

        foreach ($roots as $root) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/cache/')) {
                    continue;
                }

                foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
                    if (is_array($token) && $this->isComment($token)) {
                        $commented[] = substr($file->getPathname(), strlen(base_path()) + 1).':'.$token[2];
                    }
                }
            }
        }

        return $commented;
    }

    /** @param  array{0: int, 1: string, 2: int}  $token */
    private function isComment(array $token): bool
    {
        if ($token[0] === T_COMMENT) {
            return true;
        }

        if ($token[0] !== T_DOC_COMMENT) {
            return false;
        }

        $lines = preg_split('/\R/', $token[1]) ?: [];

        foreach ($lines as $line) {
            $content = trim(preg_replace('/^\s*\/?\*+\/?|\*\/\s*$/', '', trim($line)) ?? '');

            if ($content !== '' && ! str_starts_with($content, '@')) {
                return true;
            }
        }

        return false;
    }
}
