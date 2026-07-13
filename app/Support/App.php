<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Support;

use Dotenv\Dotenv;

final class App
{
    private static string $basePath;

    public static function bootstrap(string $basePath): void
    {
        self::$basePath = rtrim($basePath, DIRECTORY_SEPARATOR);

        if (file_exists(self::basePath('.env'))) {
            Dotenv::createImmutable(self::$basePath)->safeLoad();
        }

        date_default_timezone_set((string) env_value('APP_TIMEZONE', 'Europe/Belgrade'));

        if ((bool) env_value('APP_DEBUG', false)) {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name((string) env_value('SESSION_NAME', 'playworld_mystery'));
            session_save_path(self::basePath('storage/sessions'));
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => (bool) env_value('SESSION_SECURE', true),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function basePath(string $path = ''): string
    {
        $full = self::$basePath . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $full);
    }
}

