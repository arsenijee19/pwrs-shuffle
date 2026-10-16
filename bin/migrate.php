<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PlayWorld\MysteryBox\Support\App;
use PlayWorld\MysteryBox\Support\Database;

App::bootstrap(dirname(__DIR__));

Database::migrate();
echo 'Database is up to date.' . PHP_EOL;
