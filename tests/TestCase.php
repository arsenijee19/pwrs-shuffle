<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use PlayWorld\MysteryBox\Support\App;
use PlayWorld\MysteryBox\Support\Database;

abstract class TestCase extends BaseTestCase
{
    protected string $dbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbPath = 'storage/database/test-' . bin2hex(random_bytes(6)) . '.sqlite';
        $_ENV['APP_URL'] = 'https://example.test';
        $_ENV['DB_PATH'] = $this->dbPath;
        $_ENV['SESSION_SECURE'] = 'false';
        $_ENV['RESULT_RETENTION_DAYS'] = '5';

        App::bootstrap(dirname(__DIR__));
        Database::reset();
        foreach (glob(app_path('database/migrations/*.sql')) as $migration) {
            Database::pdo()->exec((string) file_get_contents($migration));
        }
    }

    protected function tearDown(): void
    {
        Database::reset();
        $path = app_path($this->dbPath);
        if (file_exists($path)) {
            unlink($path);
        }
        parent::tearDown();
    }
}
