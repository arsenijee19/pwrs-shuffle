<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/migrate.php'), $migrateCode);
if ($migrateCode !== 0) {
    exit($migrateCode);
}

passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/create-admin.php'), $adminCode);
exit($adminCode);
