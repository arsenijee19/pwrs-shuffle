<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use PlayWorld\MysteryBox\Support\Database;

final class TokenService
{
    public function generate(int $count): array
    {
        $pdo = Database::pdo();
        $created = [];
        $stmt = $pdo->prepare(
            'INSERT INTO tokens (token_hash, status, created_at, updated_at) VALUES (:hash, "Active", :created, :updated)'
        );

        for ($i = 0; $i < $count; $i++) {
            do {
                $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $hash = hash('sha256', $plain);
                $exists = $this->findByHash($hash) !== null;
            } while ($exists);

            $now = date('c');
            $stmt->execute(['hash' => $hash, 'created' => $now, 'updated' => $now]);
            $created[] = [
                'id' => (int) $pdo->lastInsertId(),
                'token' => $plain,
                'link' => url('/box/' . $plain),
            ];
        }

        return $created;
    }

    public function findPlain(string $plain): ?array
    {
        $plain = $this->normalizePlain($plain);
        return $this->findByHash(hash('sha256', $plain));
    }

    public function normalizePlain(string $plain): string
    {
        $plain = trim(rawurldecode($plain));

        if (str_contains($plain, '/box/')) {
            $path = parse_url($plain, PHP_URL_PATH) ?: $plain;
            $plain = basename($path);
        }

        return trim($plain, " \t\n\r\0\x0B*`_.,;:!?)]}>\"'");
    }

    public function findByHash(string $hash): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM tokens WHERE token_hash = :hash LIMIT 1');
        $stmt->execute(['hash' => $hash]);
        $token = $stmt->fetch();
        return $token ?: null;
    }

    public function mask(string $plain): string
    {
        $plain = $this->normalizePlain($plain);
        return substr($plain, 0, 6) . '...' . substr($plain, -6);
    }

    public function list(int $limit = 200): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM tokens ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function stats(): array
    {
        $rows = Database::pdo()->query('SELECT status, COUNT(*) total FROM tokens GROUP BY status')->fetchAll();
        $stats = ['total' => 0, 'used' => 0, 'unused' => 0];
        foreach ($rows as $row) {
            $stats['total'] += (int) $row['total'];
            if ($row['status'] === 'Used') {
                $stats['used'] = (int) $row['total'];
            }
            if ($row['status'] === 'Active') {
                $stats['unused'] = (int) $row['total'];
            }
        }
        return $stats;
    }

    public function revoke(int $id): void
    {
        Database::pdo()->prepare('UPDATE tokens SET status = "Revoked", updated_at = :now WHERE id = :id AND status = "Active"')
            ->execute(['now' => date('c'), 'id' => $id]);
    }
}
