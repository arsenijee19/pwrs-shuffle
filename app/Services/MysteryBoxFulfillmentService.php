<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use PDO;
use PlayWorld\MysteryBox\Support\Database;
use Throwable;

final class MysteryBoxFulfillmentService
{
    /** @var callable(string, string, string): void */
    private $mailSender;

    public function __construct(
        ?callable $mailSender = null,
        private readonly ?TokenService $tokens = null,
        private readonly ?EmailDeliveryService $deliveries = null
    ) {
        $this->mailSender = $mailSender ?? static function (string $email, string $name, string $link): void {
            (new MailService())->sendMysteryBoxLink($email, $name, $link);
        };
    }

    /**
     * Shared single-link delivery used by the admin panel and the API fulfillment flow.
     *
     * @return array{id: int, token: string, link: string}
     */
    public function createAndSend(string $email, string $name = ''): array
    {
        $email = strtolower(trim($email));
        $name = trim($name);
        $created = $this->tokenService()->generate(1)[0];

        try {
            ($this->mailSender)($email, $name, $created['link']);
            try {
                $this->recordDelivery((int) $created['id'], $email, $name, 'Sent');
            } catch (Throwable) {
                // The email is already sent; audit storage must not cause a retry.
            }
            return $created;
        } catch (Throwable $error) {
            try {
                $this->recordDelivery(
                    (int) $created['id'],
                    $email,
                    $name,
                    'Failed',
                    $this->safeFailureMessage($error)
                );
            } catch (Throwable) {
                // Preserve the original delivery error without exposing audit-storage details.
            }
            throw $error;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 200): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM mystery_box_fulfillments ORDER BY created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['items'] = $this->items((int) $row['id']);
            if (!empty($row['cancelled_at'])) {
                $row['status'] = 'Cancelled';
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Revoke every still-active token in an n8n fulfillment package.
     * Already-used tokens remain used and are never rewritten.
     *
     * @return array{id:int,order_id:string,status:string,requested_quantity:int,revoked_quantity:int,used_quantity:int,already_cancelled:bool}
     */
    public function cancel(int $fulfillmentId): array
    {
        if ($fulfillmentId < 1) {
            throw new \InvalidArgumentException('Fulfillment ID nije validan.');
        }

        $pdo = Database::pdo();
        $this->beginImmediate($pdo);
        try {
            $fulfillment = $this->findFulfillmentById($fulfillmentId);
            if (!$fulfillment) {
                throw new \InvalidArgumentException('n8n isporuka nije pronađena.');
            }

            $alreadyCancelled = !empty($fulfillment['cancelled_at']);
            $revoked = 0;
            $used = 0;
            $revoke = $pdo->prepare(
                'UPDATE tokens SET status = "Revoked", updated_at = :updated_at
                 WHERE id = :id AND status = "Active"'
            );

            foreach ($this->items($fulfillmentId) as $item) {
                if ((string) ($item['token_status'] ?? '') === 'Used') {
                    $used++;
                }

                if ($alreadyCancelled || empty($item['token_id'])) {
                    continue;
                }

                $revoke->execute(['updated_at' => date('c'), 'id' => $item['token_id']]);
                $revoked += $revoke->rowCount();
            }

            if (!$alreadyCancelled) {
                $pdo->prepare(
                    'UPDATE mystery_box_fulfillments
                     SET cancelled_at = :cancelled_at, updated_at = :updated_at
                     WHERE id = :id AND cancelled_at IS NULL'
                )->execute([
                    'cancelled_at' => date('c'),
                    'updated_at' => date('c'),
                    'id' => $fulfillmentId,
                ]);
            }

            $this->commit($pdo);
        } catch (Throwable $error) {
            $this->rollback($pdo);
            throw $error;
        }

        return [
            'id' => $fulfillmentId,
            'order_id' => (string) $fulfillment['order_id'],
            'status' => 'Cancelled',
            'requested_quantity' => (int) $fulfillment['requested_quantity'],
            'revoked_quantity' => $revoked,
            'used_quantity' => $used,
            'already_cancelled' => $alreadyCancelled,
        ];
    }

    /**
     * Fulfill one order exactly once, resuming only items that are known to have failed.
     * A Sending item is deliberately never retried automatically because SMTP may have
     * accepted the message before the process stopped.
     *
     * @return array{
     *   order_id: string,
     *   status: string,
     *   requested_quantity: int,
     *   sent_quantity: int,
     *   idempotent_replay: bool,
     *   resumed: bool,
     *   items: array<int, array{sequence: int, status: string}>
     * }
     */
    public function fulfill(string $orderId, string $email, string $name, int $quantity, int $maxQuantity): array
    {
        $orderId = trim($orderId);
        $email = strtolower(trim($email));
        $name = trim($name);

        $this->validateInput($orderId, $email, $name, $quantity, $maxQuantity);

        [$fulfillment, $replay, $resumed] = $this->prepareFulfillment($orderId, $email, $name, $quantity);

        if ($replay || $fulfillment['status'] === 'NeedsReview') {
            return $this->summary((int) $fulfillment['id'], $replay, $resumed);
        }

        $this->processItems((int) $fulfillment['id']);
        return $this->summary((int) $fulfillment['id'], false, $resumed);
    }

    private function validateInput(string $orderId, string $email, string $name, int $quantity, int $maxQuantity): void
    {
        if ($orderId === '' || strlen($orderId) > 100 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/', $orderId)) {
            throw new FulfillmentException('Order ID nije validan.', 'invalid_order_id', 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            throw new FulfillmentException('Email adresa nije validna.', 'invalid_email', 422);
        }

        if ($quantity < 1) {
            throw new FulfillmentException('Quantity mora biti najmanje 1.', 'invalid_quantity', 422);
        }

        if ($maxQuantity < 1 || $quantity > $maxQuantity) {
            throw new FulfillmentException('Quantity prelazi dozvoljeni limit.', 'quantity_exceeds_limit', 422);
        }

        if (strlen($name) > 160 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $name)) {
            throw new FulfillmentException('Ime kupca nije validno.', 'invalid_name', 422);
        }
    }

    /**
     * @return array{0: array<string, mixed>, 1: bool, 2: bool}
     */
    private function prepareFulfillment(string $orderId, string $email, string $name, int $quantity): array
    {
        $pdo = Database::pdo();
        $this->beginImmediate($pdo);

        try {
            $existing = $this->findFulfillment($orderId);
            if ($existing) {
                if ($existing['recipient_email'] !== $email || (int) $existing['requested_quantity'] !== $quantity) {
                    $this->rollback($pdo);
                    throw new FulfillmentException(
                        'Order ID je već povezan sa drugom email adresom ili quantity vrednošću.',
                        'fulfillment_conflict',
                        409
                    );
                }

                if (!empty($existing['cancelled_at'])) {
                    $this->commit($pdo);
                    return [$existing, true, true];
                }

                $items = $this->items((int) $existing['id']);
                $hasSending = false;
                $hasPending = false;
                $hasFailed = false;
                $allSent = $items !== [];
                foreach ($items as $item) {
                    if ($item['status'] === 'Sending') {
                        $hasSending = true;
                    }
                    if ($item['status'] === 'Pending') {
                        $hasPending = true;
                    }
                    if ($item['status'] === 'Failed') {
                        $hasFailed = true;
                    }
                    if ($item['status'] !== 'Sent') {
                        $allSent = false;
                    }
                }

                if ($hasSending) {
                    $this->markSendingItemsForReview((int) $existing['id']);
                    $existing = $this->findFulfillmentById((int) $existing['id']) ?? $existing;
                } elseif ($existing['status'] === 'Processing' && $hasPending && !$hasFailed && !$allSent) {
                    $this->rollback($pdo);
                    throw new FulfillmentException(
                        'Isporuka ovog order-a je već u toku.',
                        'fulfillment_in_progress',
                        409
                    );
                }

                $this->commit($pdo);

                if ($existing['status'] === 'Completed' || $existing['status'] === 'NeedsReview') {
                    return [$existing, $existing['status'] === 'Completed', true];
                }

                return [$existing, false, true];
            }

            $now = date('c');
            $pdo->prepare(
                'INSERT INTO mystery_box_fulfillments
                 (order_id, recipient_email, recipient_name, requested_quantity, status, sent_quantity, created_at, updated_at)
                 VALUES (:order_id, :email, :name, :quantity, "Processing", 0, :created_at, :updated_at)'
            )->execute([
                'order_id' => $orderId,
                'email' => $email,
                'name' => $name,
                'quantity' => $quantity,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $fulfillmentId = (int) $pdo->lastInsertId();
            $itemStmt = $pdo->prepare(
                'INSERT INTO mystery_box_fulfillment_items
                 (fulfillment_id, sequence_number, status, updated_at)
                 VALUES (:fulfillment_id, :sequence_number, "Pending", :updated_at)'
            );

            for ($sequence = 1; $sequence <= $quantity; $sequence++) {
                $itemStmt->execute([
                    'fulfillment_id' => $fulfillmentId,
                    'sequence_number' => $sequence,
                    'updated_at' => $now,
                ]);
            }

            $created = $this->findFulfillmentById($fulfillmentId);
            $this->commit($pdo);

            if (!$created) {
                throw new FulfillmentException('Fulfillment nije kreiran.', 'fulfillment_creation_failed', 500);
            }

            return [$created, false, false];
        } catch (Throwable $error) {
            $this->rollback($pdo);
            throw $error;
        }
    }

    private function processItems(int $fulfillmentId): void
    {
        $items = $this->items($fulfillmentId);

        foreach ($items as $item) {
            if ($item['status'] === 'Sent') {
                continue;
            }

            if ($item['status'] === 'NeedsReview' || $item['status'] === 'Sending') {
                $this->markItemNeedsReview((int) $item['id'], 'unknown_delivery_state', 'Slanje je ostalo u nepoznatom stanju; potrebna je ručna provera.');
                break;
            }

            $claimed = $this->claimItem((int) $item['id']);
            if (!$claimed) {
                continue;
            }

            $created = null;
            try {
                $created = $this->prepareToken((int) $item['id'], $item['status'] === 'Failed');
                ($this->mailSender)($this->fulfillmentEmail($fulfillmentId), $this->fulfillmentName($fulfillmentId), $created['link']);

                try {
                    $this->markItemSent((int) $item['id']);
                } catch (Throwable) {
                    $this->markItemNeedsReview((int) $item['id'], 'database_commit_unknown', 'Email je možda prihvaćen, ali status nije moguće pouzdano potvrditi.');
                    break;
                }

                try {
                    $this->recordDelivery((int) $created['id'], $this->fulfillmentEmail($fulfillmentId), $this->fulfillmentName($fulfillmentId), 'Sent');
                } catch (Throwable) {
                    // Item status is the idempotency source of truth; do not retry for an audit failure.
                }
            } catch (MailDeliveryException $error) {
                if ($error->outcomeUnknown) {
                    $this->markItemNeedsReview((int) $item['id'], $error->errorCode, 'Ishod email slanja nije moguće pouzdano potvrditi.');
                    break;
                }

                $this->markItemFailed((int) $item['id'], $error->errorCode, $this->safeFailureMessage($error));
                if ($created) {
                    try {
                        $this->recordDelivery((int) $created['id'], $this->fulfillmentEmail($fulfillmentId), $this->fulfillmentName($fulfillmentId), 'Failed', $this->safeFailureMessage($error));
                    } catch (Throwable) {
                    }
                }
            } catch (Throwable $error) {
                $this->markItemFailed((int) $item['id'], 'smtp_failed', $this->safeFailureMessage($error));
                if ($created) {
                    try {
                        $this->recordDelivery((int) $created['id'], $this->fulfillmentEmail($fulfillmentId), $this->fulfillmentName($fulfillmentId), 'Failed', $this->safeFailureMessage($error));
                    } catch (Throwable) {
                    }
                }
            }
        }

        $this->refreshFulfillment($fulfillmentId);
    }

    /**
     * @return array{id: int, token: string, link: string}
     */
    private function prepareToken(int $itemId, bool $replaceExisting): array
    {
        $pdo = Database::pdo();
        $item = $this->itemById($itemId);
        if (!$item) {
            throw new FulfillmentException('Fulfillment item nije pronađen.', 'item_not_found', 500);
        }

        if ($replaceExisting && $item['token_id']) {
            $pdo->prepare('UPDATE tokens SET status = "Revoked", updated_at = :updated WHERE id = :id AND status = "Active"')
                ->execute(['updated' => date('c'), 'id' => $item['token_id']]);
        }

        $created = $this->tokenService()->generate(1)[0];
        $this->beginImmediate($pdo);
        try {
            $updated = $pdo->prepare(
                'UPDATE mystery_box_fulfillment_items
                 SET token_id = :token_id, updated_at = :updated_at
                 WHERE id = :id AND status = "Sending"
                   AND fulfillment_id IN (
                       SELECT id FROM mystery_box_fulfillments WHERE id = fulfillment_id AND cancelled_at IS NULL
                   )'
            );
            $updated->execute([
                'token_id' => $created['id'],
                'updated_at' => date('c'),
                'id' => $itemId,
            ]);
            if ($updated->rowCount() !== 1) {
                $this->commit($pdo);
                $this->tokenService()->revoke((int) $created['id']);
                throw new FulfillmentException('Fulfillment item više nije u očekivanom stanju.', 'item_state_changed', 500);
            }
            $this->commit($pdo);
        } catch (Throwable $error) {
            $this->rollback($pdo);
            throw $error;
        }

        return $created;
    }

    private function claimItem(int $itemId): bool
    {
        $pdo = Database::pdo();
        $this->beginImmediate($pdo);
        try {
            $item = $this->itemById($itemId);
            if (!$item) {
                $this->rollback($pdo);
                return false;
            }

            if ($item['status'] === 'Sending') {
                $this->markSendingItemForReview((int) $item['id']);
                $this->commit($pdo);
                return false;
            }

            if (!in_array($item['status'], ['Pending', 'Failed'], true)) {
                $this->commit($pdo);
                return false;
            }

            $now = date('c');
            $pdo->prepare(
                'UPDATE mystery_box_fulfillment_items
                 SET status = "Sending", started_at = :started_at, error_code = NULL, error_message = NULL, updated_at = :updated_at
                 WHERE id = :id'
            )->execute(['started_at' => $now, 'updated_at' => $now, 'id' => $itemId]);
            $this->commit($pdo);
            return true;
        } catch (Throwable $error) {
            $this->rollback($pdo);
            throw $error;
        }
    }

    private function markItemSent(int $itemId): void
    {
        $pdo = Database::pdo();
        $this->beginImmediate($pdo);
        try {
            $now = date('c');
            $updated = $pdo->prepare(
                'UPDATE mystery_box_fulfillment_items
                 SET status = "Sent", sent_at = :sent_at, updated_at = :updated_at, error_code = NULL, error_message = NULL
                 WHERE id = :id AND status = "Sending"'
            );
            $updated->execute(['sent_at' => $now, 'updated_at' => $now, 'id' => $itemId]);
            if ($updated->rowCount() !== 1) {
                throw new FulfillmentException('Status slanja nije moguće potvrditi.', 'item_status_unknown', 500);
            }
            $this->commit($pdo);
        } catch (Throwable $error) {
            $this->rollback($pdo);
            throw $error;
        }
    }

    private function markItemFailed(int $itemId, string $errorCode, string $message): void
    {
        $this->updateItemStatus($itemId, 'Failed', $errorCode, $message);
    }

    private function markItemNeedsReview(int $itemId, string $errorCode, string $message): void
    {
        $this->updateItemStatus($itemId, 'NeedsReview', $errorCode, $message);
    }

    private function updateItemStatus(int $itemId, string $status, ?string $errorCode, ?string $message): void
    {
        $pdo = Database::pdo();
        $this->beginImmediate($pdo);
        try {
            $pdo->prepare(
                'UPDATE mystery_box_fulfillment_items
                 SET status = :status, error_code = :error_code, error_message = :error_message, updated_at = :updated_at
                 WHERE id = :id AND status <> "Sent"'
            )->execute([
                'status' => $status,
                'error_code' => $errorCode,
                'error_message' => $message,
                'updated_at' => date('c'),
                'id' => $itemId,
            ]);
            $this->commit($pdo);
        } catch (Throwable $error) {
            $this->rollback($pdo);
            throw $error;
        }
    }

    private function refreshFulfillment(int $fulfillmentId): void
    {
        $pdo = Database::pdo();
        $this->beginImmediate($pdo);
        try {
            $items = $this->items($fulfillmentId);
            $sent = count(array_filter($items, static fn (array $item): bool => $item['status'] === 'Sent'));
            $needsReview = count(array_filter($items, static fn (array $item): bool => $item['status'] === 'NeedsReview')) > 0;
            $failed = count(array_filter($items, static fn (array $item): bool => $item['status'] === 'Failed')) > 0;
            $allSent = $items !== [] && $sent === count($items);
            $status = $allSent ? 'Completed' : ($needsReview ? 'NeedsReview' : ($failed ? 'Partial' : 'Processing'));
            $errorCode = null;
            $errorMessage = null;
            foreach ($items as $item) {
                if ($item['status'] === 'NeedsReview' || $item['status'] === 'Failed') {
                    $errorCode = $item['error_code'];
                    $errorMessage = $item['error_message'];
                    break;
                }
            }

            $pdo->prepare(
                'UPDATE mystery_box_fulfillments
                 SET status = :status, sent_quantity = :sent_quantity, error_code = :error_code,
                     error_message = :error_message, updated_at = :updated_at,
                     completed_at = CASE WHEN :status = "Completed" THEN COALESCE(completed_at, :completed_at) ELSE completed_at END
                 WHERE id = :id'
            )->execute([
                'status' => $status,
                'sent_quantity' => $sent,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'updated_at' => date('c'),
                'completed_at' => date('c'),
                'id' => $fulfillmentId,
            ]);
            $this->commit($pdo);
        } catch (Throwable $error) {
            $this->rollback($pdo);
            throw $error;
        }
    }

    /** @return array<string, mixed> */
    private function findFulfillment(string $orderId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM mystery_box_fulfillments WHERE order_id = :order_id LIMIT 1');
        $stmt->execute(['order_id' => $orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function findFulfillmentById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM mystery_box_fulfillments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    private function items(int $fulfillmentId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT mystery_box_fulfillment_items.*, tokens.status AS token_status
             FROM mystery_box_fulfillment_items
             LEFT JOIN tokens ON tokens.id = mystery_box_fulfillment_items.token_id
             WHERE mystery_box_fulfillment_items.fulfillment_id = :fulfillment_id
             ORDER BY sequence_number ASC'
        );
        $stmt->execute(['fulfillment_id' => $fulfillmentId]);
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    private function itemById(int $itemId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM mystery_box_fulfillment_items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $itemId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function fulfillmentEmail(int $fulfillmentId): string
    {
        $fulfillment = $this->findFulfillmentById($fulfillmentId);
        return (string) ($fulfillment['recipient_email'] ?? '');
    }

    private function fulfillmentName(int $fulfillmentId): string
    {
        $fulfillment = $this->findFulfillmentById($fulfillmentId);
        return (string) ($fulfillment['recipient_name'] ?? '');
    }

    /** @return array{order_id: string, status: string, requested_quantity: int, sent_quantity: int, idempotent_replay: bool, resumed: bool, items: array<int, array{sequence: int, status: string}>} */
    private function summary(int $fulfillmentId, bool $replay, bool $resumed): array
    {
        $fulfillment = $this->findFulfillmentById($fulfillmentId);
        if (!$fulfillment) {
            throw new FulfillmentException('Fulfillment nije pronađen.', 'fulfillment_not_found', 500);
        }

        return [
            'order_id' => (string) $fulfillment['order_id'],
            'status' => !empty($fulfillment['cancelled_at']) ? 'Cancelled' : (string) $fulfillment['status'],
            'requested_quantity' => (int) $fulfillment['requested_quantity'],
            'sent_quantity' => (int) $fulfillment['sent_quantity'],
            'idempotent_replay' => $replay,
            'resumed' => $resumed,
            'items' => array_map(
                static fn (array $item): array => [
                    'sequence' => (int) $item['sequence_number'],
                    'status' => (string) $item['status'],
                ],
                $this->items($fulfillmentId)
            ),
        ];
    }

    private function markSendingItemsForReview(int $fulfillmentId): void
    {
        Database::pdo()->prepare(
            'UPDATE mystery_box_fulfillment_items
             SET status = "NeedsReview", error_code = "unknown_delivery_state",
                 error_message = "Slanje je ostalo u nepoznatom stanju; potrebna je ručna provera.", updated_at = :updated_at
             WHERE fulfillment_id = :fulfillment_id AND status = "Sending"'
        )->execute(['updated_at' => date('c'), 'fulfillment_id' => $fulfillmentId]);
    }

    private function markSendingItemForReview(int $itemId): void
    {
        Database::pdo()->prepare(
            'UPDATE mystery_box_fulfillment_items
             SET status = "NeedsReview", error_code = "unknown_delivery_state",
                 error_message = "Slanje je ostalo u nepoznatom stanju; potrebna je ručna provera.", updated_at = :updated_at
             WHERE id = :id AND status = "Sending"'
        )->execute(['updated_at' => date('c'), 'id' => $itemId]);
    }

    private function recordDelivery(int $tokenId, string $email, string $name, string $status, ?string $error = null): void
    {
        ($this->deliveries ?? new EmailDeliveryService())->record($tokenId, $email, $name, $status, $error);
    }

    private function safeFailureMessage(Throwable $error): string
    {
        if ($error instanceof MailDeliveryException) {
            return match ($error->errorCode) {
                'smtp_connection_failed' => 'SMTP server nije dostupan.',
                'smtp_unknown' => 'SMTP server nije pouzdano potvrdio prijem emaila.',
                default => 'SMTP server nije prihvatio email.',
            };
        }

        return 'Email slanje nije uspelo. Pokušaj se može bezbedno ponoviti.';
    }

    private function tokenService(): TokenService
    {
        return $this->tokens ?? new TokenService();
    }

    private function beginImmediate(PDO $pdo): void
    {
        Database::beginImmediate($pdo);
    }

    private function commit(PDO $pdo): void
    {
        Database::commit($pdo);
    }

    private function rollback(PDO $pdo): void
    {
        Database::rollback($pdo);
    }
}
