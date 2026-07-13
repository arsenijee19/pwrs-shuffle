<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use PDO;
use RuntimeException;
use PlayWorld\MysteryBox\Support\Database;

final class DrawService
{
    public function resultForToken(int $tokenId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT d.*, p.name prize_name, p.description prize_description, p.instructions prize_instructions,
                p.cover_path, p.rarity
             FROM draws d JOIN prizes p ON p.id = d.prize_id WHERE d.token_id = :token_id LIMIT 1'
        );
        $stmt->execute(['token_id' => $tokenId]);
        $draw = $stmt->fetch();
        return $draw ?: null;
    }

    public function recent(int $limit = 8): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT d.*, p.name prize_name FROM draws d JOIN prizes p ON p.id = d.prize_id ORDER BY d.created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function prizeCounts(): array
    {
        return Database::pdo()->query('SELECT name, won_count FROM prizes ORDER BY won_count DESC, name ASC')->fetchAll();
    }

    public function open(string $plainToken): array
    {
        $pdo = Database::pdo();
        $tokenHash = hash('sha256', $plainToken);

        $pdo->beginTransaction();
        try {
            $tokenStmt = $pdo->prepare('SELECT * FROM tokens WHERE token_hash = :hash LIMIT 1');
            $tokenStmt->execute(['hash' => $tokenHash]);
            $token = $tokenStmt->fetch();

            if (!$token) {
                throw new RuntimeException('Token not found.');
            }

            $existing = $this->resultForToken((int) $token['id']);
            if ($existing) {
                $pdo->commit();
                return $existing;
            }

            if ($token['status'] !== 'Active') {
                throw new RuntimeException('Token is not active.');
            }

            if (!empty($token['expires_at']) && new \DateTimeImmutable($token['expires_at']) < new \DateTimeImmutable()) {
                $pdo->prepare('UPDATE tokens SET status = "Expired", updated_at = :now WHERE id = :id')
                    ->execute(['now' => date('c'), 'id' => $token['id']]);
                throw new RuntimeException('Token expired.');
            }

            $prizes = $pdo->query(
                'SELECT * FROM prizes WHERE active = 1 AND won_count < max_quantity AND weight > 0'
            )->fetchAll();

            if (!$prizes) {
                throw new RuntimeException('No prizes available.');
            }

            $prize = $this->pickPrize($prizes);
            $drawId = 'PW-' . strtoupper(bin2hex(random_bytes(5)));
            $now = date('c');

            $updatePrize = $pdo->prepare('UPDATE prizes SET won_count = won_count + 1, updated_at = :now WHERE id = :id AND won_count < max_quantity');
            $updatePrize->execute(['now' => $now, 'id' => $prize['id']]);

            if ($updatePrize->rowCount() < 1) {
                throw new RuntimeException('Prize update failed.');
            }

            $pdo->prepare(
                'INSERT INTO draws (draw_id, token_id, prize_id, token_mask, created_at)
                VALUES (:draw_id, :token_id, :prize_id, :token_mask, :created_at)'
            )->execute([
                'draw_id' => $drawId,
                'token_id' => $token['id'],
                'prize_id' => $prize['id'],
                'token_mask' => (new TokenService())->mask($plainToken),
                'created_at' => $now,
            ]);

            $pdo->prepare('UPDATE tokens SET status = "Used", used_at = :used, updated_at = :updated WHERE id = :id')
                ->execute(['used' => $now, 'updated' => $now, 'id' => $token['id']]);

            $pdo->commit();

            return $this->resultForToken((int) $token['id']) ?? throw new RuntimeException('Result missing.');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function isVisible(array $draw): bool
    {
        $days = (int) env_value('RESULT_RETENTION_DAYS', 5);
        $expires = (new \DateTimeImmutable($draw['created_at']))->modify('+' . $days . ' days');
        return $expires >= new \DateTimeImmutable();
    }

    private function pickPrize(array $prizes): array
    {
        $total = array_sum(array_map(fn (array $prize): int => (int) $prize['weight'], $prizes));
        $roll = random_int(1, max(1, $total));
        $cursor = 0;
        foreach ($prizes as $prize) {
            $cursor += (int) $prize['weight'];
            if ($roll <= $cursor) {
                return $prize;
            }
        }
        return $prizes[array_key_last($prizes)];
    }
}
