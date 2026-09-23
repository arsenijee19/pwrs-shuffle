<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use PDO;
use PlayWorld\MysteryBox\Support\ClientIpResolver;
use PlayWorld\MysteryBox\Support\Database;
use RuntimeException;
use Throwable;

final class DrawService
{
    public function resultForToken(int $tokenId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT d.*, COALESCE(d.snapshot_name, p.name) prize_name,
                COALESCE(d.snapshot_description, p.description) prize_description,
                COALESCE(d.snapshot_instructions, p.instructions) prize_instructions,
                COALESCE(d.snapshot_cover_path, p.cover_path) cover_path,
                COALESCE(d.snapshot_rarity, p.rarity) rarity,
                CASE WHEN d.snapshot_name IS NOT NULL THEN d.snapshot_bonus_box_count ELSE COALESCE(p.bonus_box_count, 0) END bonus_box_count,
                CASE WHEN d.snapshot_name IS NOT NULL THEN d.snapshot_discount_percent ELSE p.discount_percent END discount_percent,
                CASE WHEN d.snapshot_name IS NOT NULL THEN d.snapshot_manual_claim ELSE COALESCE(p.manual_claim, 0) END manual_claim
             FROM draws d JOIN prizes p ON p.id = d.prize_id WHERE d.token_id = :token_id LIMIT 1'
        );
        $stmt->execute(['token_id' => $tokenId]);
        $draw = $stmt->fetch();
        return $draw ?: null;
    }

    public function findByDrawId(string $drawId): ?array
    {
        $drawId = strtoupper(trim($drawId));
        if (!preg_match('/^PW-[A-F0-9]{10}$/', $drawId)) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT d.*, COALESCE(d.snapshot_name, p.name) prize_name,
                COALESCE(d.snapshot_description, p.description) prize_description,
                COALESCE(d.snapshot_instructions, p.instructions) prize_instructions,
                COALESCE(d.snapshot_cover_path, p.cover_path) cover_path,
                COALESCE(d.snapshot_rarity, p.rarity) rarity,
                CASE WHEN d.snapshot_name IS NOT NULL THEN d.snapshot_bonus_box_count ELSE COALESCE(p.bonus_box_count, 0) END bonus_box_count,
                CASE WHEN d.snapshot_name IS NOT NULL THEN d.snapshot_discount_percent ELSE p.discount_percent END discount_percent,
                CASE WHEN d.snapshot_name IS NOT NULL THEN d.snapshot_manual_claim ELSE COALESCE(p.manual_claim, 0) END manual_claim
             FROM draws d JOIN prizes p ON p.id = d.prize_id WHERE d.draw_id = :draw_id LIMIT 1'
        );
        $stmt->execute(['draw_id' => $drawId]);
        $draw = $stmt->fetch();
        return $draw ?: null;
    }

    public function recent(int $limit = 8): array
    {
        $stmt = Database::pdo()->prepare('SELECT d.*, COALESCE(d.snapshot_name, p.name) prize_name FROM draws d JOIN prizes p ON p.id = d.prize_id ORDER BY d.created_at DESC LIMIT :limit');
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
        $tokenService = new TokenService();
        $plainToken = $tokenService->normalizePlain($plainToken);
        $tokenHash = hash('sha256', $plainToken);
        Database::beginImmediate($pdo);

        try {
            $tokenStmt = $pdo->prepare('SELECT * FROM tokens WHERE token_hash = :hash LIMIT 1');
            $tokenStmt->execute(['hash' => $tokenHash]);
            $token = $tokenStmt->fetch();
            if (!$token) {
                throw new RuntimeException('Token nije pronađen.');
            }

            $existing = $this->resultForToken((int) $token['id']);
            if ($existing) {
                Database::commit($pdo);
                return $existing;
            }
            if ($token['status'] !== 'Active') {
                throw new RuntimeException('Token nije aktivan.');
            }
            if (!empty($token['expires_at']) && new \DateTimeImmutable($token['expires_at']) < new \DateTimeImmutable()) {
                $pdo->prepare('UPDATE tokens SET status = "Expired", updated_at = :now WHERE id = :id')->execute(['now' => date('c'), 'id' => $token['id']]);
                throw new RuntimeException('Token je istekao.');
            }

            $allPrizes = $pdo->query('SELECT * FROM prizes WHERE active = 1 AND won_count < max_quantity AND weight > 0')->fetchAll();
            $codeService = new DiscountCodeService();
            $prizes = array_values(array_filter($allPrizes, static function (array $prize) use ($codeService, $pdo): bool {
                $percentage = (int) ($prize['discount_percent'] ?? 0);
                return $percentage < 1 || $codeService->hasAvailable($percentage, $pdo);
            }));
            if (!$prizes) {
                throw new RuntimeException('Trenutno nema dostupnih nagrada.');
            }

            $identity = (new ClientIpResolver())->identity();
            $fingerprint = $identity['fingerprint'];
            $identityApplied = $identity['applied'];
            $windowMinutes = max(1, (int) env_value('ANTI_REPEAT_WINDOW_MINUTES', 60));
            $antiRepeatApplied = false;
            if ($identityApplied && $fingerprint !== null) {
                $since = (new \DateTimeImmutable())->modify('-' . $windowMinutes . ' minutes')->format('c');
                $recentStmt = $pdo->prepare('SELECT DISTINCT prize_id FROM draws WHERE client_fingerprint = :fingerprint AND created_at >= :since');
                $recentStmt->execute(['fingerprint' => $fingerprint, 'since' => $since]);
                $recentIds = array_map('intval', $recentStmt->fetchAll(PDO::FETCH_COLUMN));
                $filtered = array_values(array_filter($prizes, static fn (array $prize): bool => !in_array((int) $prize['id'], $recentIds, true)));
                if ($filtered !== []) {
                    $antiRepeatApplied = count($filtered) !== count($prizes);
                    $prizes = $filtered;
                }
            }

            $prize = $this->pickPrize($prizes);
            $drawId = 'PW-' . strtoupper(bin2hex(random_bytes(5)));
            $now = date('c');
            $updatePrize = $pdo->prepare('UPDATE prizes SET won_count = won_count + 1, updated_at = :now WHERE id = :id AND won_count < max_quantity');
            $updatePrize->execute(['now' => $now, 'id' => $prize['id']]);
            if ($updatePrize->rowCount() < 1) {
                throw new RuntimeException('Nagrada više nije dostupna.');
            }

            $pdo->prepare(
                'INSERT INTO draws (draw_id, token_id, prize_id, token_mask, created_at,
                 snapshot_name, snapshot_description, snapshot_instructions, snapshot_cover_path, snapshot_rarity,
                 snapshot_bonus_box_count, snapshot_discount_percent, snapshot_manual_claim,
                 client_fingerprint, anti_repeat_applied, anti_repeat_window_minutes)
                VALUES (:draw_id, :token_id, :prize_id, :token_mask, :created_at,
                 :snapshot_name, :snapshot_description, :snapshot_instructions, :snapshot_cover_path, :snapshot_rarity,
                 :snapshot_bonus_box_count, :snapshot_discount_percent, :snapshot_manual_claim,
                 :client_fingerprint, :anti_repeat_applied, :anti_repeat_window_minutes)'
            )->execute([
                'draw_id' => $drawId, 'token_id' => $token['id'], 'prize_id' => $prize['id'],
                'token_mask' => $tokenService->mask($plainToken), 'created_at' => $now,
                'snapshot_name' => $prize['name'], 'snapshot_description' => $prize['description'],
                'snapshot_instructions' => $prize['instructions'], 'snapshot_cover_path' => $prize['cover_path'],
                'snapshot_rarity' => $prize['rarity'], 'snapshot_bonus_box_count' => (int) ($prize['bonus_box_count'] ?? 0),
                'snapshot_discount_percent' => $prize['discount_percent'] !== null ? (int) $prize['discount_percent'] : null,
                'snapshot_manual_claim' => (int) ($prize['manual_claim'] ?? 0), 'client_fingerprint' => $fingerprint,
                'anti_repeat_applied' => $antiRepeatApplied ? 1 : 0,
                'anti_repeat_window_minutes' => $identityApplied ? $windowMinutes : null,
            ]);

            if ((int) ($prize['discount_percent'] ?? 0) > 0 && !$codeService->assignAvailable((int) $prize['discount_percent'], $drawId, $pdo)) {
                throw new RuntimeException('Kod za popust više nije dostupan.');
            }

            $updatedToken = $pdo->prepare('UPDATE tokens SET status = "Used", used_at = :used, updated_at = :updated WHERE id = :id AND status = "Active"');
            $updatedToken->execute(['used' => $now, 'updated' => $now, 'id' => $token['id']]);
            if ($updatedToken->rowCount() !== 1) {
                throw new RuntimeException('Token status nije moguće sačuvati.');
            }
            Database::commit($pdo);
            return $this->resultForToken((int) $token['id']) ?? throw new RuntimeException('Rezultat nije pronađen.');
        } catch (Throwable $e) {
            Database::rollback($pdo);
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
