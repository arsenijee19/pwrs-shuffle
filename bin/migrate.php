<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PlayWorld\MysteryBox\Support\App;
use PlayWorld\MysteryBox\Support\Database;

App::bootstrap(dirname(__DIR__));

$pdo = Database::pdo();
foreach (glob(app_path('database/migrations/*.sql')) as $migration) {
    $pdo->exec((string) file_get_contents($migration));
    echo 'Migrated ' . basename($migration) . PHP_EOL;
}
