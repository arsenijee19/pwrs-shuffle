<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use PlayWorld\MysteryBox\Support\Database;

final class AuthService
{
    public function attempt(string $email, string $password): bool
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM admins WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_email'] = $admin['email'];
        return true;
    }

    public static function check(): bool
    {
        return isset($_SESSION['admin_id']);
    }

    public static function requireAdmin(): void
    {
        header('Cache-Control: no-store');
        if (!\PlayWorld\MysteryBox\Support\Database::hasAdmins()) {
            http_response_code(503);
            render('errors/not-found', ['title' => 'Administracija nije dostupna']);
            return;
        }

        if (!self::check()) {
            redirect('/admin/login');
        }
    }

    public function createInitialAdmin(string $email, string $password): bool
    {
        if (\PlayWorld\MysteryBox\Support\Database::hasAdmins()) {
            return false;
        }

        $now = date('c');
        \PlayWorld\MysteryBox\Support\Database::pdo()->prepare(
            'INSERT INTO admins (email, password_hash, created_at, updated_at) VALUES (:email, :hash, :created, :updated)'
        )->execute([
            'email' => strtolower(trim($email)),
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'created' => $now,
            'updated' => $now,
        ]);

        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public function changePassword(string $current, string $new): bool
    {
        if (strlen($new) < 10) {
            return false;
        }

        $stmt = Database::pdo()->prepare('SELECT * FROM admins WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $_SESSION['admin_id'] ?? 0]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($current, $admin['password_hash'])) {
            return false;
        }

        $update = Database::pdo()->prepare('UPDATE admins SET password_hash = :hash, updated_at = :updated WHERE id = :id');
        $update->execute([
            'hash' => password_hash($new, PASSWORD_DEFAULT),
            'updated' => date('c'),
            'id' => $admin['id'],
        ]);

        session_regenerate_id(true);

        return true;
    }
}
