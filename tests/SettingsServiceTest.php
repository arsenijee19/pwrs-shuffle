<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Tests;

use PlayWorld\MysteryBox\Services\SettingsService;
use PlayWorld\MysteryBox\Support\Database;

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

    public function testApiSettingsAreSavedEncryptedAndUsedByProtectedServices(): void
    {
        $service = new SettingsService();
        $key = 'test-n8n-key-with-more-than-24-chars';

        $service->saveApiSettings([
            'api_enabled' => '1',
            'n8n_api_key' => $key,
            'n8n_api_max_quantity' => '40',
            'n8n_api_rate_limit_per_minute' => '70',
        ]);

        $config = $service->apiConfig();
        $this->assertTrue($config['enabled']);
        $this->assertSame($key, $config['key']);
        $this->assertSame(40, $config['max_quantity']);
        $this->assertSame(70, $config['rate_limit_per_minute']);
        $stored = Database::pdo()->query("SELECT value FROM app_settings WHERE key = 'n8n_api_key_ciphertext'")->fetchColumn();
        $this->assertNotSame($key, $stored);
        $this->assertStringStartsWith('v1.', (string) $stored);
    }
}
