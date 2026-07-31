<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use PlayWorld\MysteryBox\Support\Database;

final class SettingsService
{
    public function all(): array
    {
        $rows = Database::pdo()->query('SELECT key, value FROM app_settings')->fetchAll();
        $settings = [];

        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }

        return $settings;
    }

    public function get(string $key, string $default = ''): string
    {
        $stmt = Database::pdo()->prepare('SELECT value FROM app_settings WHERE key = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();

        return is_string($value) ? $value : $default;
    }

    public function saveEmailSettings(array $input): void
    {
        $currentPassword = $this->get('smtp_password');
        $password = trim((string) ($input['smtp_password'] ?? ''));

        $settings = [
            'mail_enabled' => !empty($input['mail_enabled']) ? 'true' : 'false',
            'smtp_host' => trim((string) ($input['smtp_host'] ?? 'smtp-relay.brevo.com')),
            'smtp_port' => (string) max(1, (int) ($input['smtp_port'] ?? 587)),
            'smtp_username' => trim((string) ($input['smtp_username'] ?? '')),
            'smtp_password' => $password !== '' ? $password : $currentPassword,
            'mail_from' => trim((string) ($input['mail_from'] ?? 'usluge@playworld.rs')),
            'mail_from_name' => trim((string) ($input['mail_from_name'] ?? 'PlayWorld - Mystery Box')),
            'mail_timeout' => (string) max(3, (int) ($input['mail_timeout'] ?? 15)),
        ];

        $stmt = Database::pdo()->prepare(
            'INSERT INTO app_settings (key, value, updated_at) VALUES (:key, :value, :updated)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at'
        );

        foreach ($settings as $key => $value) {
            $stmt->execute(['key' => $key, 'value' => $value, 'updated' => date('c')]);
        }
    }

    public function emailConfig(): array
    {
        $settings = $this->all();

        return [
            'enabled' => ($settings['mail_enabled'] ?? 'false') === 'true',
            'host' => $settings['smtp_host'] ?? '',
            'port' => (int) ($settings['smtp_port'] ?? 587),
            'username' => $settings['smtp_username'] ?? '',
            'password' => $settings['smtp_password'] ?? '',
            'from' => $settings['mail_from'] ?? '',
            'from_name' => $settings['mail_from_name'] ?? 'PlayWorld - Mystery Box',
            'timeout' => (int) ($settings['mail_timeout'] ?? 15),
        ];
    }
}
