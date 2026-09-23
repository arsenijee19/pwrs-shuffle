<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Support;

use RuntimeException;

final class SecretBox
{
    public static function encrypt(string $value): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($value, $nonce, self::key());
        return 'v1.' . base64_encode($nonce . $cipher);
    }

    public static function decrypt(string $value): string
    {
        if (!str_starts_with($value, 'v1.')) {
            throw new RuntimeException('Nepoznat format zaštićenog podatka.');
        }

        $raw = base64_decode(substr($value, 3), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Zaštićeni podatak nije validan.');
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
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
}
