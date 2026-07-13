<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PlayWorld\MysteryBox\Support\App;
use PlayWorld\MysteryBox\Support\Database;

App::bootstrap(dirname(__DIR__));

$email = strtolower(trim((string) env_value('ADMIN_EMAIL', 'admin@example.com')));
$password = (string) env_value('ADMIN_PASSWORD', 'change-this-before-launch');
$now = date('c');

$stmt = Database::pdo()->prepare('SELECT id FROM admins WHERE email = :email');
$stmt->execute(['email' => $email]);

if ($stmt->fetch()) {
    echo "Admin already exists: {$email}" . PHP_EOL;
    exit(0);
}

Database::pdo()->prepare(
    'INSERT INTO admins (email, password_hash, created_at, updated_at) VALUES (:email, :hash, :created, :updated)'
)->execute([
    'email' => $email,
    'hash' => password_hash($password, PASSWORD_DEFAULT),
    'created' => $now,
    'updated' => $now,
]);

echo "Admin created: {$email}" . PHP_EOL;
