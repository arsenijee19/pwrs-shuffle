<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Tests;

use PlayWorld\MysteryBox\Services\SettingsService;

final class SettingsServiceTest extends TestCase
{
    public function testEmailSettingsCanBeSavedWithoutEnvFile(): void
    {
        $service = new SettingsService();
        $service->saveEmailSettings([
            'mail_enabled' => '1',
            'smtp_host' => 'smtp-relay.brevo.com',
            'smtp_port' => '587',
            'smtp_username' => 'login@example.com',
            'smtp_password' => 'xsmtpsib-test',
            'mail_from' => 'usluge@playworld.rs',
            'mail_from_name' => 'PlayWorld - Mystery Box',
            'mail_timeout' => '15',
        ]);

        $config = $service->emailConfig();

        $this->assertTrue($config['enabled']);
        $this->assertSame('smtp-relay.brevo.com', $config['host']);
        $this->assertSame('login@example.com', $config['username']);
        $this->assertSame('xsmtpsib-test', $config['password']);
        $this->assertSame('usluge@playworld.rs', $config['from']);
    }
}
