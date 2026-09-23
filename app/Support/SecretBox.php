<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Support;

use RuntimeException;

final class SecretBox
{
    // Libsodium's secretbox nonce is always 24 bytes. Keeping the size local
    // also works on hosting builds that expose the functions but not the
    // Sodium constants inside namespaced PHP code.
    private const NONCE_BYTES = 24;

    public static function encrypt(string $value): string
    {
        self::ensureAvailable();
        $nonce = random_bytes(self::NONCE_BYTES);
        $cipher = sodium_crypto_secretbox($value, $nonce, self::key());
        return 'v1.' . base64_encode($nonce . $cipher);
    }

    public static function decrypt(string $value): string
    {
        if (!str_starts_with($value, 'v1.')) {
            throw new RuntimeException('Nepoznat format zaštićenog podatka.');
        }

        self::ensureAvailable();
        $raw = base64_decode(substr($value, 3), true);
        if ($raw === false || strlen($raw) <= self::NONCE_BYTES) {
            throw new RuntimeException('Zaštićeni podatak nije validan.');
        }

        $nonce = substr($raw, 0, self::NONCE_BYTES);
        $cipher = substr($raw, self::NONCE_BYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, self::key());
        if ($plain === false) {
            throw new RuntimeException('Zaštićeni podatak nije moguće otvoriti.');
        }

        return $plain;
    }

    private static function key(): string
    {
        $appKey = trim((string) env_value('APP_KEY', ''));
        if ($appKey === '') {
            throw new RuntimeException('APP_KEY nije podešen.');
        }

        return hash('sha256', $appKey, true);
    }

    private static function ensureAvailable(): void
    {
        if (!function_exists('sodium_crypto_secretbox') || !function_exists('sodium_crypto_secretbox_open')) {
            throw new RuntimeException('PHP Sodium ekstenzija nije uključena na serveru.');
        }
    }
}
