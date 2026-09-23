<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use PDO;
use PlayWorld\MysteryBox\Support\Database;
use PlayWorld\MysteryBox\Support\SecretBox;
use Throwable;

final class RewardDeliveryService
{
    /** @var callable(string,string,array<int,string>): void */
    private $mailSender;

    public function __construct(?callable $mailSender = null, private readonly ?TokenService $tokens = null)
    {
        $this->mailSender = $mailSender ?? static function (string $email, string $name, array $links): void {
            (new MailService())->sendMysteryBoxLinks($email, $name, $links);
        };
    }

    /** @return array<string,mixed> */
    public function claim(array $draw, string $email, string $name = ''): array
    {
        $email = strtolower(trim($email));
        $name = trim($name);
        $this->validateEmail($email);
        $drawId = (string) ($draw['draw_id'] ?? '');
        $quantity = (int) ($draw['snapshot_bonus_box_count'] ?? $draw['bonus_box_count'] ?? 0);
        if (!preg_match('/^PW-[A-F0-9]{10}$/', $drawId) || $quantity < 1 || $quantity > 50) {
            throw new \InvalidArgumentException('Ova nagrada nema validnu bonus isporuku.');
        }

        $pdo = Database::pdo();
        Database::beginImmediate($pdo);
        try {
            $existing = $this->findByDrawId($drawId);
            if ($existing) {
                if ((string) $existing['recipient_email'] !== $email) {
                    throw new \RuntimeException('Za ovaj rezultat je već unet drugi email.');
                }

                if ($existing['status'] === 'Sending') {
                    $pdo->prepare('UPDATE reward_deliveries SET status = "NeedsReview", error_message = :error, updated_at = :updated_at WHERE id = :id')
                        ->execute(['error' => 'Prethodno slanje je ostalo u nepoznatom stanju.', 'updated_at' => date('c'), 'id' => $existing['id']]);
                } elseif ($existing['status'] === 'Failed') {
                    $pdo->prepare('UPDATE reward_deliveries SET status = "Sending", error_message = NULL, updated_at = :updated_at WHERE id = :id')
                        ->execute(['updated_at' => date('c'), 'id' => $existing['id']]);
                }

                $delivery = $this->findByDrawId($drawId) ?: $existing;
                $this->commit($pdo);
                if (in_array($delivery['status'], ['Sent', 'NeedsReview'], true)) {
                    return $this->summary($delivery, true);
                }
                return $this->sendExisting($delivery, $name);
            }

            $now = date('c');
            $pdo->prepare(
                'INSERT INTO reward_deliveries
                 (draw_id, prize_name, recipient_email, requested_quantity, status, created_at, updated_at)
                 VALUES (:draw_id, :prize_name, :email, :quantity, "Sending", :created_at, :updated_at)'
            )->execute([
                'draw_id' => $drawId,
                'prize_name' => (string) ($draw['prize_name'] ?? 'PlayWorld nagrada'),
                'email' => $email,
                'quantity' => $quantity,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $deliveryId = (int) $pdo->lastInsertId();
            $insert = $pdo->prepare(
                'INSERT INTO reward_delivery_items
                 (delivery_id, sequence_number, token_id, link_ciphertext, created_at)
                 VALUES (:delivery_id, :sequence, :token_id, :link_ciphertext, :created_at)'
            );
            $links = [];
            for ($sequence = 1; $sequence <= $quantity; $sequence++) {
                $created = $this->tokenService()->generate(1)[0];
                $links[] = $created['link'];
                $insert->execute([
                    'delivery_id' => $deliveryId,
                    'sequence' => $sequence,
                    'token_id' => $created['id'],
                    'link_ciphertext' => SecretBox::encrypt($created['link']),
                    'created_at' => $now,
                ]);
            }
            $delivery = $this->findById($deliveryId);
            $this->commit($pdo);
            if (!$delivery) {
                throw new \RuntimeException('Bonus isporuka nije kreirana.');
            }
        } catch (Throwable $error) {
            Database::rollback($pdo);
            throw $error;
        }

        return $this->send($delivery, $links, $name);
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 200): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM reward_deliveries ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['items'] = $this->items((int) $row['id'], true);
        }
        unset($row);
        return $rows;
    }

    /** @return array<string,mixed>|null */
    public function forDraw(string $drawId): ?array
    {
        $delivery = $this->findByDrawId($drawId);
        return $delivery ? $this->summary($delivery, true) : null;
    }

    /** @return array<string,mixed> */
    private function sendExisting(array $delivery, string $name): array
    {
        return $this->send($delivery, array_column($this->items((int) $delivery['id'], true), 'link'), $name);
    }

    /** @param array<int,string> $links */
    private function send(array $delivery, array $links, string $name): array
    {
        try {
            ($this->mailSender)((string) $delivery['recipient_email'], $name, $links);
            Database::pdo()->prepare(
                'UPDATE reward_deliveries SET status = "Sent", sent_at = :sent_at, updated_at = :updated_at, error_message = NULL WHERE id = :id AND status = "Sending"'
            )->execute(['sent_at' => date('c'), 'updated_at' => date('c'), 'id' => $delivery['id']]);
        } catch (MailDeliveryException $error) {
            $status = $error->outcomeUnknown ? 'NeedsReview' : 'Failed';
            Database::pdo()->prepare('UPDATE reward_deliveries SET status = :status, error_message = :error, updated_at = :updated_at WHERE id = :id AND status = "Sending"')
                ->execute(['status' => $status, 'error' => $this->safeError($error), 'updated_at' => date('c'), 'id' => $delivery['id']]);
        } catch (Throwable $error) {
            Database::pdo()->prepare('UPDATE reward_deliveries SET status = "Failed", error_message = :error, updated_at = :updated_at WHERE id = :id AND status = "Sending"')
                ->execute(['error' => 'Email slanje nije uspelo.', 'updated_at' => date('c'), 'id' => $delivery['id']]);
        }

        $fresh = $this->findById((int) $delivery['id']) ?: $delivery;
        return $this->summary($fresh, false);
    }

    /** @return array<string,mixed> */
    private function summary(array $delivery, bool $replay): array
    {
        return [
            'id' => (int) $delivery['id'],
            'draw_id' => (string) $delivery['draw_id'],
            'status' => (string) $delivery['status'],
            'recipient_email' => (string) $delivery['recipient_email'],
            'masked_email' => $this->maskEmail((string) $delivery['recipient_email']),
            'requested_quantity' => (int) $delivery['requested_quantity'],
            'idempotent_replay' => $replay,
        ];
    }

    /** @return array<string,mixed>|null */
    private function findByDrawId(string $drawId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM reward_deliveries WHERE draw_id = :draw_id LIMIT 1');
        $stmt->execute(['draw_id' => $drawId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    private function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM reward_deliveries WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    private function items(int $deliveryId, bool $decrypt = false): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT rdi.*, t.status token_status, t.used_at
             FROM reward_delivery_items rdi JOIN tokens t ON t.id = rdi.token_id
             WHERE rdi.delivery_id = :delivery_id ORDER BY rdi.sequence_number ASC'
        );
        $stmt->execute(['delivery_id' => $deliveryId]);
        $items = $stmt->fetchAll();
        foreach ($items as &$item) {
            if ($decrypt) {
                try {
                    $item['link'] = SecretBox::decrypt($item['link_ciphertext']);
                } catch (Throwable) {
                    $item['link'] = '[zaštićen link]';
                }
            }
            unset($item['link_ciphertext']);
        }
        unset($item);
        return $items;
    }

    private function validateEmail(string $email): void
    {
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Unesite ispravnu email adresu.');
        }
    }

    private function tokenService(): TokenService
    {
        return $this->tokens ?? new TokenService();
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        return ($local !== '' ? substr($local, 0, 1) . '***' : '***') . '@' . $domain;
    }

    private function safeError(Throwable $error): string
    {
        return $error instanceof MailDeliveryException && $error->outcomeUnknown
            ? 'SMTP prijem emaila nije pouzdano potvrđen; potrebna je ručna provera.'
            : 'Email slanje nije uspelo; moguće je ponoviti nakon provere.';
    }

    private function commit(PDO $pdo): void
    {
        Database::commit($pdo);
    }
}
