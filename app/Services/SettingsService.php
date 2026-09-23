<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use PlayWorld\MysteryBox\Support\Database;
use PlayWorld\MysteryBox\Support\SecretBox;

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

    /** @return array{enabled:bool,key:string,max_quantity:int,rate_limit_per_minute:int} */
    public function apiConfig(): array
    {
        $settings = $this->all();
        $ciphertext = trim((string) ($settings['n8n_api_key_ciphertext'] ?? ''));
        $key = '';

        if ($ciphertext !== '') {
            try {
                $key = SecretBox::decrypt($ciphertext);
            } catch (\Throwable) {
                $key = '';
            }
        }

        return [
            'enabled' => array_key_exists('api_enabled', $settings)
                ? ($settings['api_enabled'] === 'true')
                : (bool) env_value('API_ENABLED', false),
            'key' => $key !== '' ? $key : (string) env_value('N8N_API_KEY', env_value('PWRS_N8N_API_KEY', '')),
            'max_quantity' => max(1, (int) ($settings['n8n_api_max_quantity'] ?? env_value('N8N_API_MAX_QUANTITY', 20))),
            'rate_limit_per_minute' => max(1, (int) ($settings['n8n_api_rate_limit_per_minute'] ?? env_value('N8N_API_RATE_LIMIT_PER_MINUTE', 30))),
        ];
    }

    /** @return array{enabled:bool,key_saved:bool,max_quantity:int,rate_limit_per_minute:int} */
    public function apiSettingsForAdmin(): array
    {
        $config = $this->apiConfig();

        return [
            'enabled' => $config['enabled'],
            'key_saved' => $config['key'] !== '',
            'max_quantity' => $config['max_quantity'],
            'rate_limit_per_minute' => $config['rate_limit_per_minute'],
        ];
    }

    public function saveApiSettings(array $input): void
    {
        $currentCiphertext = $this->get('n8n_api_key_ciphertext');
        $newKey = trim((string) ($input['n8n_api_key'] ?? ''));
        $enabled = !empty($input['api_enabled']);
        $maxQuantity = max(1, min(500, (int) ($input['n8n_api_max_quantity'] ?? 20)));
        $rateLimit = max(1, min(1000, (int) ($input['n8n_api_rate_limit_per_minute'] ?? 30)));

        if ($newKey !== '' && strlen($newKey) < 24) {
            throw new \InvalidArgumentException('API key treba da ima najmanje 24 karaktera.');
        }
        if ($enabled && $newKey === '' && $currentCiphertext === '' && (string) env_value('N8N_API_KEY', env_value('PWRS_N8N_API_KEY', '')) === '') {
            throw new \InvalidArgumentException('Unesite API key pre uključivanja API-ja.');
        }

        $settings = [
            'api_enabled' => $enabled ? 'true' : 'false',
            'n8n_api_max_quantity' => (string) $maxQuantity,
            'n8n_api_rate_limit_per_minute' => (string) $rateLimit,
        ];
        if ($newKey !== '') {
            $settings['n8n_api_key_ciphertext'] = SecretBox::encrypt($newKey);
        } elseif ($currentCiphertext !== '') {
            $settings['n8n_api_key_ciphertext'] = $currentCiphertext;
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO app_settings (key, value, updated_at) VALUES (:key, :value, :updated)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at'
        );

        foreach ($settings as $key => $value) {
            $stmt->execute(['key' => $key, 'value' => $value, 'updated' => date('c')]);
        }
    }
}
