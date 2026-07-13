<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Support;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $path = app_path((string) env_value('DB_PATH', 'storage/database/playworld.sqlite'));
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        self::$pdo = new PDO('sqlite:' . $path);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$pdo->exec('PRAGMA foreign_keys = ON');
        self::$pdo->exec('PRAGMA busy_timeout = 5000');

        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    public static function migrate(): void
    {
        $pdo = self::pdo();
        foreach (glob(app_path('database/migrations/*.sql')) as $migration) {
            $pdo->exec((string) file_get_contents($migration));
        }
    }

    public static function hasAdmins(): bool
    {
        self::migrate();
        $count = self::pdo()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
        return (int) $count > 0;
    }
}
