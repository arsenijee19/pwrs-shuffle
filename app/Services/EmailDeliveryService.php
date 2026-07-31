<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use PlayWorld\MysteryBox\Support\Database;

final class EmailDeliveryService
{
    public function record(int $tokenId, string $email, string $name, string $status, ?string $errorMessage = null): void
    {
        Database::pdo()->prepare(
            'INSERT INTO email_deliveries (token_id, recipient_email, recipient_name, status, error_message, created_at)
             VALUES (:token_id, :recipient_email, :recipient_name, :status, :error_message, :created_at)'
        )->execute([
            'token_id' => $tokenId,
            'recipient_email' => strtolower(trim($email)),
            'recipient_name' => trim($name),
            'status' => $status,
            'error_message' => $errorMessage,
            'created_at' => date('c'),
        ]);
    }

    public function recent(int $limit = 200): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT email_deliveries.*, tokens.status token_status, tokens.used_at
             FROM email_deliveries
             LEFT JOIN tokens ON tokens.id = email_deliveries.token_id
             ORDER BY email_deliveries.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function stats(): array
    {
        $rows = Database::pdo()->query('SELECT status, COUNT(*) total FROM email_deliveries GROUP BY status')->fetchAll();
        $stats = ['total' => 0, 'sent' => 0, 'failed' => 0];

        foreach ($rows as $row) {
            $count = (int) $row['total'];
            $stats['total'] += $count;
            if ($row['status'] === 'Sent') {
                $stats['sent'] = $count;
            }
            if ($row['status'] === 'Failed') {
                $stats['failed'] = $count;
            }
        }

        return $stats;
    }
}
