<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class NoOutboundChannelsTest extends TestCase
{
    public function test_no_log_channel_leaves_the_machine(): void
    {
        $drivers = array_values(array_unique(array_map(fn (array $channel) => $channel['driver'] ?? '', (array) config('logging.channels'))));

        $this->assertSame([], array_values(array_intersect($drivers, ['slack', 'papertrail'])));
        $this->assertArrayNotHasKey('papertrail', config('logging.channels'));
        $this->assertArrayNotHasKey('slack', config('logging.channels'));
    }

    public function test_no_mailer_or_service_credential_for_an_outside_provider_is_configured(): void
    {
        $transports = array_map(fn (array $mailer) => $mailer['transport'], (array) config('mail.mailers'));

        $this->assertSame([], array_values(array_intersect($transports, ['smtp', 'ses', 'ses-v2', 'postmark', 'resend', 'mailgun', 'sendmail', 'roundrobin'])));
        $this->assertSame([], array_values(array_intersect(array_keys(config('services')), ['postmark', 'resend', 'ses', 'slack', 'mailgun'])));
    }
}
