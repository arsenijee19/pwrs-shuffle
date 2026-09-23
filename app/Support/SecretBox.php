<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Support;

use RuntimeException;

final class SecretBox
{
    // Keep cipher sizes local so namespaced PHP builds do not depend on
    // Sodium constants being exposed by the hosting configuration.
    private const SODIUM_NONCE_BYTES = 24;
    private const OPENSSL_CIPHER = 'aes-256-gcm';
    private const OPENSSL_IV_BYTES = 12;
    private const OPENSSL_TAG_BYTES = 16;

    public static function encrypt(string $value): string
    {
        $key = self::key();

        if (self::sodiumAvailable()) {
            $nonce = random_bytes(self::SODIUM_NONCE_BYTES);
            $cipher = sodium_crypto_secretbox($value, $nonce, $key);
            return 'v1.' . base64_encode($nonce . $cipher);
        }

        if (self::opensslAvailable()) {
            $iv = random_bytes(self::OPENSSL_IV_BYTES);
            $tag = '';
            $cipher = openssl_encrypt(
                $value,
                self::OPENSSL_CIPHER,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                '',
                self::OPENSSL_TAG_BYTES
            );

            if ($cipher === false || strlen($tag) !== self::OPENSSL_TAG_BYTES) {
                throw new RuntimeException('Zaštita API ključa trenutno nije dostupna.');
            }

            return 'v2.' . base64_encode($iv . $tag . $cipher);
        }

        throw new RuntimeException('Server nema uključenu PHP Sodium ili OpenSSL ekstenziju za bezbedno čuvanje API ključa. Obratite se hostingu.');
    }

    public static function decrypt(string $value): string
    {
        if (str_starts_with($value, 'v1.')) {
            if (!self::sodiumAvailable()) {
                throw new RuntimeException('Postojeći zaštićeni ključ koristi Sodium format, ali Sodium nije uključen na serveru.');
            }

            $raw = base64_decode(substr($value, 3), true);
            if ($raw === false || strlen($raw) <= self::SODIUM_NONCE_BYTES) {
                throw new RuntimeException('Zaštićeni podatak nije validan.');
            }

            $nonce = substr($raw, 0, self::SODIUM_NONCE_BYTES);
            $cipher = substr($raw, self::SODIUM_NONCE_BYTES);
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, self::key());
            if ($plain === false) {
                throw new RuntimeException('Zaštićeni podatak nije moguće otvoriti.');
            }

            return $plain;
        }

        if (str_starts_with($value, 'v2.')) {
            if (!self::opensslAvailable()) {
                throw new RuntimeException('OpenSSL ekstenzija nije uključena na serveru.');
            }

            $raw = base64_decode(substr($value, 3), true);
            $minimum = self::OPENSSL_IV_BYTES + self::OPENSSL_TAG_BYTES + 1;
            if ($raw === false || strlen($raw) < $minimum) {
                throw new RuntimeException('Zaštićeni podatak nije validan.');
            }

            $iv = substr($raw, 0, self::OPENSSL_IV_BYTES);
            $tag = substr($raw, self::OPENSSL_IV_BYTES, self::OPENSSL_TAG_BYTES);
            $cipher = substr($raw, self::OPENSSL_IV_BYTES + self::OPENSSL_TAG_BYTES);
            $plain = openssl_decrypt($cipher, self::OPENSSL_CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, '');
            if ($plain === false) {
                throw new RuntimeException('Zaštićeni podatak nije moguće otvoriti.');
            }

            return $plain;
        }

        throw new RuntimeException('Nepoznat format zaštićenog podatka.');
    }

    private static function key(): string
    {
        $appKey = trim((string) env_value('APP_KEY', ''));
        if ($appKey === '') {
            throw new RuntimeException('APP_KEY nije podešen.');
        }

        return hash('sha256', $appKey, true);
    }

    private static function sodiumAvailable(): bool
    {
        return function_exists('sodium_crypto_secretbox') && function_exists('sodium_crypto_secretbox_open');
    }

    private static function opensslAvailable(): bool
    {
        return function_exists('openssl_encrypt')
            && function_exists('openssl_decrypt')
            && function_exists('openssl_cipher_iv_length')
            && (int) openssl_cipher_iv_length(self::OPENSSL_CIPHER) === self::OPENSSL_IV_BYTES;
    }
}
