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
        if (!self::check()) {
            redirect('/admin/login');
        }
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public function changePassword(string $current, string $new): bool
    {
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

        return true;
    }
}

