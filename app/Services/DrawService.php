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
    /** @var array<string,int> */
    private const RARITY_PROBABILITIES = [
        'Ultra Rare' => 3,
        'Rare' => 7,
        'Special' => 30,
        'Standard' => 30,
        'Common' => 30,
    ];

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
        return $draw ? $this->normalizeLegacyReward($draw) : null;
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
        return $draw ? $this->normalizeLegacyReward($draw) : null;
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

            // Rarity controls the probability tier. Weight is retained only as
            // legacy/admin data and must not influence this selection.
            $eligiblePrizes = $this->buildEligiblePrizes($pdo);
            $groups = $this->groupEligiblePrizesByRarity($eligiblePrizes);
            if ($groups === []) {
                throw new RuntimeException('Trenutno nema dostupnih nagrada.');
            }

            $selectedRarity = $this->pickRarity(array_keys($groups));
            $prizes = $groups[$selectedRarity];
            $codeService = new DiscountCodeService();

            $identity = (new ClientIpResolver())->identity();
            $fingerprint = $identity['fingerprint'];
            $identityApplied = $identity['applied'];
            $antiRepeatApplied = false;
            if ($identityApplied && $fingerprint !== null) {
                // Only the immediately previous prize is excluded. Older wins
                // become fully eligible again after any different result.
                $previousStmt = $pdo->prepare(
                    'SELECT prize_id FROM draws
                     WHERE client_fingerprint = :fingerprint
                     ORDER BY id DESC LIMIT 1'
                );
                $previousStmt->execute(['fingerprint' => $fingerprint]);
                $previousPrizeId = $previousStmt->fetchColumn();
                $filtered = $previousPrizeId === false
                    ? $prizes
                    : array_values(array_filter($prizes, static fn (array $prize): bool => (int) $prize['id'] !== (int) $previousPrizeId));
                if ($filtered !== []) {
                    $antiRepeatApplied = count($filtered) !== count($prizes);
                    $prizes = $filtered;
                }
            }

            $prize = $this->pickRandomPrize($prizes);
            $drawId = 'PW-' . strtoupper(bin2hex(random_bytes(5)));
            $now = date('c');
            $updatePrize = $pdo->prepare('UPDATE prizes SET won_count = won_count + 1, updated_at = :now WHERE id = :id');
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
                'anti_repeat_window_minutes' => null,
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

    /**
     * Select a rarity using the configured tier probabilities.
     *
     * The optional roll is only for deterministic tests and simulations. The
     * production path always uses random_int(1, total probability).
     *
     * @param array<int,string> $eligibleRarities
     */
    public function pickRarity(array $eligibleRarities, ?int $roll = null): string
    {
        $available = array_fill_keys($eligibleRarities, true);
        $rarities = array_values(array_filter(
            PrizeService::RARITIES,
            static fn (string $rarity): bool => isset($available[$rarity])
        ));
        if ($rarities === []) {
            throw new RuntimeException('Trenutno nema dostupnih rarity grupa.');
        }

        $total = array_sum(array_map(
            static fn (string $rarity): int => self::RARITY_PROBABILITIES[$rarity],
            $rarities
        ));
        $roll ??= random_int(1, $total);
        if ($roll < 1 || $roll > $total) {
            throw new \InvalidArgumentException('Rarity roll nije u dozvoljenom opsegu.');
        }

        $cursor = 0;
        foreach ($rarities as $rarity) {
            $cursor += self::RARITY_PROBABILITIES[$rarity];
            if ($roll <= $cursor) {
                return $rarity;
            }
        }

        return $rarities[array_key_last($rarities)];
    }

    /**
     * Keep results created before bonus metadata was introduced usable without
     * changing the stored draw record or its immutable history.
     *
     * @param array<string,mixed> $draw
     * @return array<string,mixed>
     */
    private function normalizeLegacyReward(array $draw): array
    {
        $name = mb_strtolower(trim((string) ($draw['prize_name'] ?? '')));
        if ($name === 'besplatan shuffle + 5% na sledeću kupovinu') {
            $draw['bonus_box_count'] = max(1, (int) ($draw['bonus_box_count'] ?? 0));
            $draw['discount_percent'] = max(5, (int) ($draw['discount_percent'] ?? 0));
        }

        return $draw;
    }

    /** @return array<int,array<string,mixed>> */
    private function buildEligiblePrizes(PDO $pdo): array
    {
        $allPrizes = $pdo->query('SELECT * FROM prizes WHERE active = 1')->fetchAll();
        $codeService = new DiscountCodeService();

        return array_values(array_filter($allPrizes, static function (array $prize) use ($codeService, $pdo): bool {
            $percentage = (int) ($prize['discount_percent'] ?? 0);
            return $percentage < 1 || $codeService->hasAvailable($percentage, $pdo);
        }));
    }

    /** @param array<int,array<string,mixed>> $prizes
     *  @return array<string,array<int,array<string,mixed>>>
     */
    private function groupEligiblePrizesByRarity(array $prizes): array
    {
        $groups = [];
        foreach ($prizes as $prize) {
            $rarity = trim((string) ($prize['rarity'] ?? ''));
            if (!in_array($rarity, PrizeService::RARITIES, true)) {
                continue;
            }
            $groups[$rarity][] = $prize;
        }

        return $groups;
    }

    /** @param array<int,array<string,mixed>> $prizes */
    private function pickRandomPrize(array $prizes): array
    {
        if ($prizes === []) {
            throw new RuntimeException('Trenutno nema kandidata u izabranoj rarity grupi.');
        }

        return $prizes[random_int(0, count($prizes) - 1)];
    }
}
