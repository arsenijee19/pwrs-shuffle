<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Tests;

use PlayWorld\MysteryBox\Services\EmailDeliveryService;
use PlayWorld\MysteryBox\Services\TokenService;

final class EmailDeliveryServiceTest extends TestCase
{
    public function testEmailDeliveryHistoryRecordsSentAndFailedAttempts(): void
    {
        $token = (new TokenService())->generate(1)[0];
        $service = new EmailDeliveryService();

        $service->record((int) $token['id'], 'Kupac@Example.com', 'Kupac', 'Sent');
        $service->record((int) $token['id'], 'drugi@example.com', '', 'Failed', 'SMTP error');

        $recent = $service->recent();
        $stats = $service->stats();

        $this->assertCount(2, $recent);
        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['sent']);
        $this->assertSame(1, $stats['failed']);
        $this->assertSame('drugi@example.com', $recent[0]['recipient_email']);
        $this->assertSame('SMTP error', $recent[0]['error_message']);
    }
}
