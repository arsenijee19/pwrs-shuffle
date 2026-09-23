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

    public static function beginImmediate(PDO $pdo): void
    {
        // PDO SQLite does not reliably expose a manually issued BEGIN IMMEDIATE
        // through inTransaction() on every hosting build, so pair it with SQL
        // COMMIT/ROLLBACK instead of PDO's transaction methods.
        $pdo->exec('BEGIN IMMEDIATE');
    }

    public static function commit(PDO $pdo): void
    {
        $pdo->exec('COMMIT');
    }

    public static function rollback(PDO $pdo): void
    {
        try {
            $pdo->exec('ROLLBACK');
        } catch (\Throwable) {
            // The transaction may already have been closed after a commit.
        }
    }

    public static function migrate(): void
    {
        $pdo = self::pdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                migration TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL
            )'
        );

        $applied = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN);
        $applied = array_fill_keys($applied, true);
        $migrations = glob(app_path('database/migrations/*.sql')) ?: [];
        sort($migrations, SORT_STRING);

        foreach ($migrations as $migration) {
            $name = basename($migration);
            if (isset($applied[$name])) {
                continue;
            }

            self::beginImmediate($pdo);
            try {
                $pdo->exec((string) file_get_contents($migration));
                $pdo->prepare('INSERT INTO schema_migrations (migration, applied_at) VALUES (:migration, :applied_at)')
                    ->execute(['migration' => $name, 'applied_at' => date('c')]);
                self::commit($pdo);
            } catch (\Throwable $error) {
                self::rollback($pdo);
                throw $error;
            }
        }
    }

    public static function hasAdmins(): bool
    {
        self::migrate();
        $count = self::pdo()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
        return (int) $count > 0;
    }
}
