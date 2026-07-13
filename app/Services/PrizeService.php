<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use PlayWorld\MysteryBox\Support\Database;

final class PrizeService
{
    private const IMPORTANCE = [
        1 => ['rarity' => 'Ultra Rare', 'weight' => 2, 'max_quantity' => 1],
        2 => ['rarity' => 'Rare', 'weight' => 8, 'max_quantity' => 2],
        3 => ['rarity' => 'Special', 'weight' => 18, 'max_quantity' => 4],
        4 => ['rarity' => 'Standard', 'weight' => 35, 'max_quantity' => 8],
        5 => ['rarity' => 'Common', 'weight' => 55, 'max_quantity' => 12],
    ];

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

    public function seedPlayWorldCatalog(): array
    {
        $created = 0;
        $updated = 0;
        $now = date('c');
        $pdo = Database::pdo();

        $find = $pdo->prepare('SELECT id FROM prizes WHERE lower(name) = lower(:name) LIMIT 1');
        $insert = $pdo->prepare(
            'INSERT INTO prizes (name, description, instructions, cover_path, rarity, weight, max_quantity, won_count, active, created_at, updated_at)
            VALUES (:name, :description, :instructions, :cover_path, :rarity, :weight, :max_quantity, 0, 1, :created_at, :updated_at)'
        );
        $update = $pdo->prepare(
            'UPDATE prizes SET description=:description, instructions=:instructions, cover_path=:cover_path,
            rarity=:rarity, weight=:weight, max_quantity=max(max_quantity, :max_quantity), active=1, updated_at=:updated_at
            WHERE id=:id'
        );

        foreach ($this->playWorldCatalog() as $item) {
            $settings = self::IMPORTANCE[$item['importance']];
            $payload = [
                'name' => $item['name'],
                'description' => $this->catalogDescription($item['name']),
                'instructions' => 'Sačuvajte PDF potvrdu i javite se PlayWorld timu za preuzimanje nagrade.',
                'cover_path' => '/assets/placeholder-cover.svg',
                'rarity' => $settings['rarity'],
                'weight' => $settings['weight'],
                'max_quantity' => $item['max_quantity'] ?? $settings['max_quantity'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $find->execute(['name' => $item['name']]);
            $id = $find->fetchColumn();

            if ($id) {
                unset($payload['name'], $payload['created_at']);
                $payload['id'] = (int) $id;
                $update->execute($payload);
                $updated++;
                continue;
            }

            $insert->execute($payload);
            $created++;
        }

        return ['created' => $created, 'updated' => $updated];
    }

    public function playWorldCatalog(): array
    {
        return [
            ['name' => 'EA Sports FC 26', 'importance' => 3],
            ['name' => 'NBA 2K26', 'importance' => 3],
            ['name' => 'Grand Theft Auto VI (GTA 6)', 'importance' => 1],
            ['name' => 'Mortal Kombat X', 'importance' => 5],
            ['name' => 'Mortal Kombat 11', 'importance' => 5],
            ['name' => 'Mortal Kombat 11 Ultimate', 'importance' => 4],
            ['name' => 'Astro Bot PS5', 'importance' => 2],
            ['name' => 'Marvel Spider-Man 2 PS5', 'importance' => 2],
            ['name' => 'Returnal PS5', 'importance' => 2],
            ['name' => 'Grand Theft Auto V PS4 / PS5', 'importance' => 2],
            ['name' => 'Poppy Playtime Chapter 1 PS4 / PS5', 'importance' => 4],
            ['name' => 'Poppy Playtime Chapter 3 PS4 / PS5', 'importance' => 3],
            ['name' => 'UFC 4 PS5', 'importance' => 5],
            ['name' => 'Farming Simulator 22 PS5', 'importance' => 5],
            ['name' => 'Tekken 7', 'importance' => 4],
            ['name' => 'L.A. Noire', 'importance' => 5],
            ['name' => 'Mad Max', 'importance' => 5],
            ['name' => 'Mafia Trilogy', 'importance' => 5],
            ['name' => 'Assassin\'s Creed Black Flag Resynced PS5', 'importance' => 1],
            ['name' => 'UFC 6 PS5', 'importance' => 1],
            ['name' => '10% popusta na sledeću kupovinu', 'importance' => 4, 'max_quantity' => 100],
            ['name' => 'God of War', 'importance' => 3],
            ['name' => 'Batman Arkham Knight', 'importance' => 4],
            ['name' => 'PlayStation Plus Extra 12 meseci', 'importance' => 2],
            ['name' => 'PlayStation Plus Essential 1 mesec', 'importance' => 3],
            ['name' => 'Call of Duty Modern Warfare Remastered', 'importance' => 4],
            ['name' => 'Call of Duty Modern Warfare 2 Campaign Remastered', 'importance' => 4],
            ['name' => 'Ghost of Tsushima', 'importance' => 3],
        ];
    }

    public function catalogDescription(string $name): string
    {
        if (str_contains(strtolower($name), 'popusta')) {
            return 'Osvojili ste PlayWorld popust koji možete iskoristiti pri sledećoj kupovini.';
        }

        return 'Osvojili ste PlayWorld Mystery Box nagradu: ' . $name . '.';
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
