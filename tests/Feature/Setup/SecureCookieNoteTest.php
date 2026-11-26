<?php

namespace Tests\Feature\Setup;

use Tests\TestCase;

class SecureCookieNoteTest extends TestCase
{
    public function test_the_example_environment_names_the_variable_that_turns_secure_cookies_off(): void
    {
        $example = (string) file_get_contents(base_path('env.example'));

        $this->assertMatchesRegularExpression('/^SESSION_SECURE_COOKIE=true$/m', $example);
        $this->assertMatchesRegularExpression('/^#.*SESSION_SECURE_COOKIE=false/m', $example);
    }

    public function test_the_secure_cookie_spec_writes_one_line_per_engine_to_the_evidence_file(): void
    {
        $spec = (string) file_get_contents(base_path('tests/e2e/security/secure-cookie.spec.ts'));

        $this->assertStringContainsString('browserName', $spec);
        $this->assertStringContainsString('EVIDENCE_FILE', $spec);
    }
}
