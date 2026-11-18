<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class HistoryEncryptionTest extends TestCase
{
    public function test_a_page_response_asks_the_browser_to_encrypt_its_history(): void
    {
        $this->withoutVite();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('"encryptHistory":true', false);
    }
}
