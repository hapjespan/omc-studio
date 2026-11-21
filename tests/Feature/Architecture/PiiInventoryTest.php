<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class PiiInventoryTest extends TestCase
{
    private const TOPICS = [
        'Undo record',
        'error log',
        'first Administrator',
        'account state',
        'user identifiers inside Audit',
        'session payload',
        'removed on the next read or on a cache clear',
    ];

    public function test_the_inventory_and_the_constraints_name_every_known_personal_data_location(): void
    {
        foreach (['specs/001-omc-studio-v1/pii-inventory.md', 'docs/constraints.md'] as $file) {
            $text = (string) file_get_contents(base_path($file));

            foreach (self::TOPICS as $topic) {
                $this->assertStringContainsStringIgnoringCase($topic, $text, $file.': '.$topic);
            }
        }
    }

    public function test_the_constraints_point_to_the_host_sshd_log_for_where_a_session_came_from(): void
    {
        $this->assertStringContainsString('sshd', (string) file_get_contents(base_path('docs/constraints.md')));
    }
}
