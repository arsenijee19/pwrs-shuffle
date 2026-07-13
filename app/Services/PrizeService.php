<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use PlayWorld\MysteryBox\Support\Database;

final class PrizeService
{
    public function all(): array
    {
        return Database::pdo()->query('SELECT * FROM prizes ORDER BY active DESC, name ASC')->fetchAll();
    }

    public function activeAvailable(): array
    {
        return Database::pdo()->query(
            'SELECT * FROM prizes WHERE active = 1 AND won_count < max_quantity AND weight > 0 ORDER BY name ASC'
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM prizes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $prize = $stmt->fetch();
        return $prize ?: null;
    }

    public function save(array $data, ?array $cover): void
    {
        $coverPath = $this->handleCoverUpload($cover, $data['existing_cover'] ?? null);
        $params = [
            'name' => trim($data['name']),
            'description' => trim($data['description']),
            'instructions' => trim($data['instructions'] ?? ''),
            'cover_path' => $coverPath,
            'rarity' => trim($data['rarity']),
            'weight' => max(0, (int) $data['weight']),
            'max_quantity' => max(1, (int) $data['max_quantity']),
            'active' => isset($data['active']) ? 1 : 0,
            'updated_at' => date('c'),
        ];

        if (!empty($data['id'])) {
            $params['id'] = (int) $data['id'];
            Database::pdo()->prepare(
                'UPDATE prizes SET name=:name, description=:description, instructions=:instructions, cover_path=:cover_path,
                rarity=:rarity, weight=:weight, max_quantity=:max_quantity, active=:active, updated_at=:updated_at WHERE id=:id'
            )->execute($params);
            return;
        }

        $params['created_at'] = date('c');
        Database::pdo()->prepare(
            'INSERT INTO prizes (name, description, instructions, cover_path, rarity, weight, max_quantity, won_count, active, created_at, updated_at)
            VALUES (:name, :description, :instructions, :cover_path, :rarity, :weight, :max_quantity, 0, :active, :created_at, :updated_at)'
        )->execute($params);
    }

    public function delete(int $id): bool
    {
        $prize = $this->find($id);
        if (!$prize || (int) $prize['won_count'] > 0) {
            return false;
        }

        Database::pdo()->prepare('DELETE FROM prizes WHERE id = :id')->execute(['id' => $id]);
        return true;
    }

    private function handleCoverUpload(?array $cover, ?string $existing): string
    {
        if (!$cover || ($cover['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $existing ?: '/assets/placeholder-cover.svg';
        }

        if (($cover['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return $existing ?: '/assets/placeholder-cover.svg';
        }

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $type = mime_content_type($cover['tmp_name']);
        if (!isset($allowed[$type])) {
            return $existing ?: '/assets/placeholder-cover.svg';
        }

        $dir = app_path('public/uploads');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $name = bin2hex(random_bytes(16)) . '.' . $allowed[$type];
        move_uploaded_file($cover['tmp_name'], $dir . DIRECTORY_SEPARATOR . $name);

        return '/uploads/' . $name;
    }
}

