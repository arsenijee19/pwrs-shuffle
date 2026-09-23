<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use PDO;
use PlayWorld\MysteryBox\Support\Database;
use Throwable;

final class RewardClaimService
{
    /** @return array<string,mixed> */
    public function create(array $draw, string $email, string $phone): array
    {
        $email = strtolower(trim($email));
        $phone = $this->normalizePhone($phone);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            throw new \InvalidArgumentException('Unesite ispravnu email adresu.');
        }
        if ($phone === null) {
            throw new \InvalidArgumentException('Unesite ispravan broj telefona ili Viber broj.');
        }

        $drawId = (string) ($draw['draw_id'] ?? '');
        $prizeName = (string) ($draw['prize_name'] ?? '');
        if (!preg_match('/^PW-[A-F0-9]{10}$/', $drawId) || $prizeName === '') {
            throw new \InvalidArgumentException('Rezultat nije validan.');
        }

        $pdo = Database::pdo();
        Database::beginImmediate($pdo);
        try {
            $existing = $this->findByDrawId($drawId);
            if ($existing) {
                if ($existing['recipient_email'] !== $email || $existing['recipient_phone'] !== $phone) {
                    throw new \RuntimeException('Za ovaj rezultat je već poslat zahtev sa drugim kontaktom.');
                }
                Database::commit($pdo);
                return $this->public($existing, true);
            }

            $now = date('c');
            $pdo->prepare(
                'INSERT INTO reward_claims
                 (draw_id, prize_name, recipient_email, recipient_phone, status, notification_status, created_at, updated_at)
                 VALUES (:draw_id, :prize_name, :email, :phone, "New", "Pending", :created_at, :updated_at)'
            )->execute([
                'draw_id' => $drawId,
                'prize_name' => $prizeName,
                'email' => $email,
                'phone' => $phone,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $id = (int) $pdo->lastInsertId();
            Database::commit($pdo);
            return $this->public($this->findById($id) ?? throw new \RuntimeException('Zahtev nije kreiran.'), false);
        } catch (Throwable $error) {
            Database::rollback($pdo);
            throw $error;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 300): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM reward_claims ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function forDraw(string $drawId): ?array
    {
        $claim = $this->findByDrawId($drawId);
        return $claim ? $this->public($claim, true) : null;
    }

    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['New', 'InProgress', 'Delivered', 'Cancelled'], true)) {
            return false;
        }
        $claim = $this->findById($id);
        if (!$claim) {
            return false;
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE reward_claims SET status = :status, updated_at = :updated_at,
             completed_at = CASE WHEN :status = "Delivered" THEN COALESCE(completed_at, :completed_at) ELSE completed_at END
             WHERE id = :id'
        );
        $stmt->execute(['status' => $status, 'updated_at' => date('c'), 'completed_at' => date('c'), 'id' => $id]);
        if ($stmt->rowCount() !== 1) {
            return false;
        }
        Database::pdo()->prepare(
            'INSERT INTO reward_claim_events (claim_id, admin_id, event_name, old_status, new_status, created_at)
             VALUES (:claim_id, :admin_id, "admin_status_change", :old_status, :new_status, :created_at)'
        )->execute([
            'claim_id' => $id,
            'admin_id' => isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null,
            'old_status' => $claim['status'],
            'new_status' => $status,
            'created_at' => date('c'),
        ]);
        return true;
    }

    /** @return array<int,array<string,mixed>> */
    public function lease(int $limit, string $worker): array
    {
        $limit = min(50, max(1, $limit));
        $worker = trim($worker);
        if ($worker === '' || strlen($worker) > 80) {
            throw new \InvalidArgumentException('Worker nije validan.');
        }

        $pdo = Database::pdo();
        Database::beginImmediate($pdo);
        try {
            $now = date('c');
            $stmt = $pdo->prepare(
                'SELECT * FROM reward_claims
                 WHERE notification_status IN ("Pending", "Failed")
                    OR (notification_status = "Sending" AND notification_lease_until < :now)
                 ORDER BY created_at ASC LIMIT :limit'
            );
            $stmt->bindValue(':now', $now);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            $leaseUntil = (new \DateTimeImmutable())->modify('+5 minutes')->format('c');
            $update = $pdo->prepare(
                'UPDATE reward_claims SET notification_status = "Sending", notification_attempts = notification_attempts + 1,
                 notification_lease_until = :lease_until, notification_worker = :worker, updated_at = :updated_at
                 WHERE id = :id'
            );
            foreach ($rows as &$row) {
                $update->execute(['lease_until' => $leaseUntil, 'worker' => $worker, 'updated_at' => $now, 'id' => $row['id']]);
                $row['notification_status'] = 'Sending';
                $row['notification_worker'] = $worker;
            }
            unset($row);
            Database::commit($pdo);

            return array_map(static fn (array $row): array => [
                'claim_id' => (int) $row['id'],
                'draw_id' => (string) $row['draw_id'],
                'prize_name' => (string) $row['prize_name'],
                'email' => (string) $row['recipient_email'],
                'phone' => (string) $row['recipient_phone'],
                'created_at' => (string) $row['created_at'],
                'admin_url' => url('/admin/reward-claims/' . (int) $row['id']),
                'worker' => $worker,
            ], $rows);
        } catch (Throwable $error) {
            Database::rollback($pdo);
            throw $error;
        }
    }

    /** @return array<string,mixed> */
    public function notificationResult(int $id, string $worker, string $status, ?string $providerId, ?string $error): array
    {
        if (!in_array($status, ['Sent', 'Failed'], true)) {
            throw new \InvalidArgumentException('Status obaveštenja nije validan.');
        }
        $claim = $this->findById($id);
        if (!$claim) {
            throw new \RuntimeException('Zahtev nije pronađen.');
        }
        if ($claim['notification_status'] === 'Sent') {
            return $claim;
        }
        if ($claim['notification_status'] !== 'Sending' || $claim['notification_worker'] !== $worker) {
            throw new \RuntimeException('Lease za ovaj zahtev više nije važeći.');
        }

        $stmt = Database::pdo()->prepare(
            'UPDATE reward_claims SET notification_status = :status, provider_message_id = :provider_id,
             notification_error = :error, notification_lease_until = NULL, notification_worker = NULL, updated_at = :updated_at
             WHERE id = :id AND notification_status = "Sending" AND notification_worker = :worker'
        );
        $stmt->execute([
            'status' => $status,
            'provider_id' => $providerId,
            'error' => $status === 'Failed' ? substr((string) $error, 0, 500) : null,
            'updated_at' => date('c'),
            'id' => $id,
            'worker' => $worker,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Status obaveštenja nije moguće potvrditi.');
        }
        return $this->findById($id) ?? $claim;
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM reward_claims WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    private function findByDrawId(string $drawId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM reward_claims WHERE draw_id = :draw_id LIMIT 1');
        $stmt->execute(['draw_id' => $drawId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function normalizePhone(string $phone): ?string
    {
        $phone = trim($phone);
        if (!preg_match('/^\+?[0-9][0-9\s()\-]{6,19}$/', $phone)) {
            return null;
        }
        $normalized = preg_replace('/[^0-9+]/', '', $phone) ?: '';
        return strlen($normalized) >= 7 ? $normalized : null;
    }

    /** @return array<string,mixed> */
    private function public(array $claim, bool $replay): array
    {
        return [
            'claim_id' => (int) $claim['id'],
            'draw_id' => (string) $claim['draw_id'],
            'status' => (string) $claim['status'],
            'notification_status' => (string) $claim['notification_status'],
            'masked_email' => $this->maskEmail((string) $claim['recipient_email']),
            'idempotent_replay' => $replay,
        ];
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        return ($local !== '' ? substr($local, 0, 1) . '***' : '***') . '@' . $domain;
    }
}
