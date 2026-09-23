<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use PDO;
use PlayWorld\MysteryBox\Support\Database;
use PlayWorld\MysteryBox\Support\SecretBox;
use Throwable;

final class DiscountCodeService
{
    public function availableCount(int $percentage, ?PDO $pdo = null): int
    {
        $pdo ??= Database::pdo();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM discount_codes WHERE percentage = :percentage AND status = "Available"');
        $stmt->execute(['percentage' => $percentage]);
        return (int) $stmt->fetchColumn();
    }

    public function hasAvailable(int $percentage, ?PDO $pdo = null): bool
    {
        return $percentage > 0 && $this->availableCount($percentage, $pdo) > 0;
    }

    /** @return array{created:int,duplicates:int,invalid:int} */
    public function import(string $contents, int $percentage, ?int $adminId = null): array
    {
        if ($percentage < 1 || $percentage > 100) {
            throw new \InvalidArgumentException('Procenat mora biti između 1 i 100.');
        }

        $created = 0;
        $duplicates = 0;
        $invalid = 0;
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO discount_codes
             (percentage, code_hash, code_ciphertext, status, created_by, created_at)
             VALUES (:percentage, :code_hash, :code_ciphertext, "Available", :created_by, :created_at)'
        );

        Database::beginImmediate($pdo);
        try {
            foreach (preg_split('/\R/u', $contents) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $columns = str_getcsv($line, ',', '"', '\\');
                $code = trim((string) ($columns[0] ?? ''));
                $code = preg_replace('/^\xEF\xBB\xBF/', '', $code) ?? $code;
                if ($code === '' || strlen($code) > 160 || preg_match('/[\x00-\x1F\x7F]/', $code)) {
                    $invalid++;
                    continue;
                }

                $normalized = strtoupper($code);
                try {
                    $stmt->execute([
                        'percentage' => $percentage,
                        'code_hash' => hash('sha256', $normalized),
                        'code_ciphertext' => SecretBox::encrypt($code),
                        'created_by' => $adminId,
                        'created_at' => date('c'),
                    ]);
                    $created++;
                } catch (Throwable $error) {
                    if (str_contains(strtolower($error->getMessage()), 'unique')) {
                        $duplicates++;
                        continue;
                    }
                    throw $error;
                }
            }
            Database::commit($pdo);
        } catch (Throwable $error) {
            Database::rollback($pdo);
            throw $error;
        }

        return compact('created', 'duplicates', 'invalid');
    }

    /** Must be called inside the draw transaction. */
    public function assignAvailable(int $percentage, string $drawId, PDO $pdo): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM discount_codes
             WHERE percentage = :percentage AND status = "Available"
             ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['percentage' => $percentage]);
        $code = $stmt->fetch();
        if (!$code) {
            return null;
        }

        $updated = $pdo->prepare(
            'UPDATE discount_codes
             SET status = "Assigned", assigned_draw_id = :draw_id, assigned_at = :assigned_at
             WHERE id = :id AND status = "Available"'
        );
        $updated->execute(['draw_id' => $drawId, 'assigned_at' => date('c'), 'id' => $code['id']]);
        if ($updated->rowCount() !== 1) {
            return null;
        }

        return $code;
    }

    public function forDraw(string $drawId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM discount_codes WHERE assigned_draw_id = :draw_id LIMIT 1');
        $stmt->execute(['draw_id' => $drawId]);
        $code = $stmt->fetch();
        if (!$code) {
            return null;
        }

        return [
            'id' => (int) $code['id'],
            'percentage' => (int) $code['percentage'],
            'code' => SecretBox::decrypt($code['code_ciphertext']),
            'masked_code' => $this->mask(SecretBox::decrypt($code['code_ciphertext'])),
            'status' => $code['status'],
        ];
    }

    /** @return array<int, array<string,mixed>> */
    public function recent(int $limit = 500, string $search = '', ?int $percentage = null, ?string $status = null): array
    {
        $sql = 'SELECT dc.*, d.prize_id, p.name prize_name
             FROM discount_codes dc
             LEFT JOIN draws d ON d.draw_id = dc.assigned_draw_id
             LEFT JOIN prizes p ON p.id = d.prize_id
             WHERE 1 = 1';
        $params = [];
        if ($percentage !== null && $percentage > 0) {
            $sql .= ' AND dc.percentage = :percentage';
            $params['percentage'] = $percentage;
        }
        if ($status !== null && in_array($status, ['Available', 'Assigned'], true)) {
            $sql .= ' AND dc.status = :status';
            $params['status'] = $status;
        }
        if ($search !== '') {
            $sql .= ' AND (dc.assigned_draw_id LIKE :search OR p.name LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        $stmt = Database::pdo()->prepare($sql . ' ORDER BY dc.created_at DESC LIMIT :limit');
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            try {
                $row['masked_code'] = $this->mask(SecretBox::decrypt($row['code_ciphertext']));
            } catch (Throwable) {
                $row['masked_code'] = '[zaštićeno]';
            }
            unset($row['code_ciphertext'], $row['code_hash']);
        }
        unset($row);
        return $rows;
    }

    /** @return array<int,array{percentage:int,available:int,assigned:int}> */
    public function stats(): array
    {
        $rows = Database::pdo()->query(
            'SELECT percentage,
                SUM(CASE WHEN status = "Available" THEN 1 ELSE 0 END) available,
                SUM(CASE WHEN status = "Assigned" THEN 1 ELSE 0 END) assigned
             FROM discount_codes GROUP BY percentage ORDER BY percentage ASC'
        )->fetchAll();
        return array_map(static fn (array $row): array => [
            'percentage' => (int) $row['percentage'],
            'available' => (int) $row['available'],
            'assigned' => (int) $row['assigned'],
        ], $rows);
    }

    public function mask(string $code): string
    {
        if (strlen($code) <= 4) {
            return str_repeat('*', strlen($code));
        }
        return substr($code, 0, 2) . str_repeat('*', max(2, strlen($code) - 4)) . substr($code, -2);
    }
}
