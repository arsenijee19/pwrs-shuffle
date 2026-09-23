<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Services;

use PlayWorld\MysteryBox\Support\Database;

final class PrizeService
{
    public const RARITIES = ['Ultra Rare', 'Rare', 'Special', 'Standard', 'Common'];
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
        $rows = Database::pdo()->query(
            "SELECT * FROM prizes WHERE active = 1 AND won_count < max_quantity AND weight > 0
            ORDER BY
                CASE rarity
                    WHEN 'Ultra Rare' THEN 1
                    WHEN 'Rare' THEN 2
                    WHEN 'Special' THEN 3
                    WHEN 'Standard' THEN 4
                    WHEN 'Common' THEN 5
                    ELSE 6
                END,
                weight ASC,
                name ASC"
        )->fetchAll();

        return array_values(array_filter($rows, function (array $prize): bool {
            return (int) ($prize['discount_percent'] ?? 0) < 1
                || (new DiscountCodeService())->hasAvailable((int) $prize['discount_percent']);
        }));
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
        $id = !empty($data['id']) ? (int) $data['id'] : null;
        $existing = $id ? $this->find($id) : null;
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $weight = filter_var($data['weight'] ?? null, FILTER_VALIDATE_INT);
        $maxQuantity = filter_var($data['max_quantity'] ?? null, FILTER_VALIDATE_INT);
        $discount = ($data['discount_enabled'] ?? null) ? (int) ($data['discount_percent'] ?? 0) : null;
        if ($name === '' || $description === '') {
            throw new \InvalidArgumentException('Naziv i opis nagrade su obavezni.');
        }
        if ($weight === false || $weight < 0 || $maxQuantity === false || $maxQuantity < 1) {
            throw new \InvalidArgumentException('Weight mora biti najmanje 0, a maksimalan broj najmanje 1.');
        }
        if ($existing && $maxQuantity < (int) $existing['won_count']) {
            throw new \InvalidArgumentException('Maksimalan broj ne može biti manji od već osvojenog broja.');
        }
        if (!in_array(trim((string) ($data['rarity'] ?? '')), self::RARITIES, true)) {
            throw new \InvalidArgumentException('Rarity vrednost nije dozvoljena.');
        }
        if ($discount !== null && ($discount < 1 || $discount > 100)) {
            throw new \InvalidArgumentException('Procenat popusta mora biti između 1 i 100.');
        }
        $bonusBoxCount = (int) ($data['bonus_box_count'] ?? 0);
        if ($bonusBoxCount < 0 || $bonusBoxCount > 50) {
            throw new \InvalidArgumentException('Broj dodatnih Mystery Box-ova mora biti između 0 i 50.');
        }

        $coverPath = $this->handleCoverUpload($cover, $data['existing_cover'] ?? null);
        $params = [
            'name' => $name,
            'description' => $description,
            'instructions' => trim($data['instructions'] ?? ''),
            'cover_path' => $coverPath,
            'rarity' => trim($data['rarity']),
            'weight' => $weight,
            'max_quantity' => $maxQuantity,
            'bonus_box_count' => $bonusBoxCount,
            'discount_percent' => $discount,
            'manual_claim' => isset($data['manual_claim']) ? 1 : 0,
            'active' => isset($data['active']) ? 1 : 0,
            'updated_at' => date('c'),
        ];

        if ($id) {
            $params['id'] = $id;
            Database::pdo()->prepare(
                'UPDATE prizes SET name=:name, description=:description, instructions=:instructions, cover_path=:cover_path,
                rarity=:rarity, weight=:weight, max_quantity=:max_quantity, bonus_box_count=:bonus_box_count,
                discount_percent=:discount_percent, manual_claim=:manual_claim, active=:active, updated_at=:updated_at WHERE id=:id'
            )->execute($params);
            $this->logChanges($id, $existing ?: [], $params);
            return;
        }

        $params['created_at'] = date('c');
        Database::pdo()->prepare(
            'INSERT INTO prizes (name, description, instructions, cover_path, rarity, weight, max_quantity, bonus_box_count, discount_percent, manual_claim, won_count, active, created_at, updated_at)
            VALUES (:name, :description, :instructions, :cover_path, :rarity, :weight, :max_quantity, :bonus_box_count, :discount_percent, :manual_claim, 0, :active, :created_at, :updated_at)'
        )->execute($params);
    }

    /** @return array<string,mixed> */
    public function inlineUpdate(int $id, string $field, string $value): array
    {
        $allowed = ['weight', 'max_quantity', 'rarity'];
        if (!in_array($field, $allowed, true)) {
            throw new \InvalidArgumentException('Polje nije moguće menjati ovim putem.');
        }
        $prize = $this->find($id);
        if (!$prize) {
            throw new \InvalidArgumentException('Nagrada nije pronađena.');
        }
        $next = $field === 'rarity' ? trim($value) : filter_var($value, FILTER_VALIDATE_INT);
        if ($field === 'rarity' && !in_array($next, self::RARITIES, true)) {
            throw new \InvalidArgumentException('Rarity vrednost nije dozvoljena.');
        }
        if ($field === 'weight' && ($next === false || $next < 0)) {
            throw new \InvalidArgumentException('Weight mora biti ceo broj 0 ili veći.');
        }
        if ($field === 'max_quantity' && ($next === false || $next < (int) $prize['won_count'] || $next < 1)) {
            throw new \InvalidArgumentException('Maksimalan broj ne može biti manji od osvojenog broja.');
        }

        Database::pdo()->prepare("UPDATE prizes SET {$field} = :value, updated_at = :updated_at WHERE id = :id")
            ->execute(['value' => $next, 'updated_at' => date('c'), 'id' => $id]);
        $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
        Database::pdo()->prepare(
            'INSERT INTO prize_change_log (prize_id, field_name, old_value, new_value, admin_id, created_at)
             VALUES (:prize_id, :field_name, :old_value, :new_value, :admin_id, :created_at)'
        )->execute(['prize_id' => $id, 'field_name' => $field, 'old_value' => (string) $prize[$field], 'new_value' => (string) $next, 'admin_id' => $adminId, 'created_at' => date('c')]);

        return $this->find($id) ?: throw new \RuntimeException('Nagrada nije pronađena nakon izmene.');
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

    public function toggle(int $id): bool
    {
        $prize = $this->find($id);
        if (!$prize) {
            return false;
        }

        Database::pdo()->prepare(
            'UPDATE prizes SET active = :active, updated_at = :updated_at WHERE id = :id'
        )->execute([
            'active' => (int) $prize['active'] === 1 ? 0 : 1,
            'updated_at' => date('c'),
            'id' => $id,
        ]);

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
            'INSERT INTO prizes (name, description, instructions, cover_path, rarity, weight, max_quantity, bonus_box_count, discount_percent, manual_claim, won_count, active, created_at, updated_at)
            VALUES (:name, :description, :instructions, :cover_path, :rarity, :weight, :max_quantity, :bonus_box_count, :discount_percent, :manual_claim, 0, 1, :created_at, :updated_at)'
        );
        $update = $pdo->prepare(
            'UPDATE prizes SET description=:description, instructions=:instructions, cover_path=:cover_path,
            rarity=:rarity, weight=:weight, max_quantity=max(won_count, :max_quantity), bonus_box_count=:bonus_box_count,
            discount_percent=:discount_percent, manual_claim=:manual_claim, active=1, updated_at=:updated_at
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
                'bonus_box_count' => (int) ($item['bonus_box_count'] ?? 0),
                'discount_percent' => $item['discount_percent'] ?? null,
                'manual_claim' => (int) ($item['manual_claim'] ?? 1),
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

    public function seedBonusCatalog(): array
    {
        $created = 0;
        $updated = 0;
        $now = date('c');
        $pdo = Database::pdo();

        $find = $pdo->prepare('SELECT id FROM prizes WHERE lower(name) = lower(:name) LIMIT 1');
        $insert = $pdo->prepare(
            'INSERT INTO prizes (name, description, instructions, cover_path, rarity, weight, max_quantity, bonus_box_count, discount_percent, manual_claim, won_count, active, created_at, updated_at)
            VALUES (:name, :description, :instructions, :cover_path, :rarity, :weight, :max_quantity, :bonus_box_count, :discount_percent, :manual_claim, 0, 1, :created_at, :updated_at)'
        );
        $update = $pdo->prepare(
            'UPDATE prizes SET description=:description, instructions=:instructions, cover_path=:cover_path,
            rarity=:rarity, weight=:weight, max_quantity=max(won_count, :max_quantity), bonus_box_count=:bonus_box_count,
            discount_percent=:discount_percent, manual_claim=:manual_claim, active=1, updated_at=:updated_at
            WHERE id=:id'
        );

        foreach ($this->bonusCatalog() as $item) {
            $payload = [
                'name' => $item['name'],
                'description' => $this->catalogDescription($item['name']),
                'instructions' => 'Sačuvajte PDF potvrdu i javite se PlayWorld timu za preuzimanje nagrade.',
                'cover_path' => '/assets/placeholder-cover.svg',
                'rarity' => $item['rarity'],
                'weight' => $item['weight'],
                'max_quantity' => $item['max_quantity'],
                'bonus_box_count' => $item['bonus_box_count'],
                'discount_percent' => $item['discount_percent'],
                'manual_claim' => $item['manual_claim'],
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

    public function bonusCatalog(): array
    {
        return [
            ['name' => '2 Mystery Box-a + 5% popusta', 'weight' => 10, 'rarity' => 'Rare', 'max_quantity' => 100000, 'bonus_box_count' => 2, 'discount_percent' => 5, 'manual_claim' => 0],
            ['name' => '5 Mystery Box-ova + 50% popusta', 'weight' => 1, 'rarity' => 'Ultra Rare', 'max_quantity' => 100000, 'bonus_box_count' => 5, 'discount_percent' => 50, 'manual_claim' => 0],
            ['name' => '10 Mystery Box-ova', 'weight' => 1, 'rarity' => 'Ultra Rare', 'max_quantity' => 100000, 'bonus_box_count' => 10, 'discount_percent' => null, 'manual_claim' => 0],
            ['name' => 'Besplatna igra po izboru', 'weight' => 1, 'rarity' => 'Ultra Rare', 'max_quantity' => 100000, 'bonus_box_count' => 0, 'discount_percent' => null, 'manual_claim' => 1],
            ['name' => 'PlayStation Plus Deluxe - 1 mesec', 'weight' => 7, 'rarity' => 'Rare', 'max_quantity' => 100000, 'bonus_box_count' => 0, 'discount_percent' => null, 'manual_claim' => 1],
            ['name' => 'PlayStation Plus Extra - 1 mesec', 'weight' => 8, 'rarity' => 'Rare', 'max_quantity' => 100000, 'bonus_box_count' => 0, 'discount_percent' => null, 'manual_claim' => 1],
            ['name' => 'PlayStation Plus Essential - 24 meseca', 'weight' => 3, 'rarity' => 'Ultra Rare', 'max_quantity' => 100000, 'bonus_box_count' => 0, 'discount_percent' => null, 'manual_claim' => 1],
            ['name' => 'PlayStation Plus Deluxe - 3 meseca', 'weight' => 5, 'rarity' => 'Rare', 'max_quantity' => 100000, 'bonus_box_count' => 0, 'discount_percent' => null, 'manual_claim' => 1],
            ['name' => 'PlayStation Plus Deluxe - 12 meseci', 'weight' => 2, 'rarity' => 'Ultra Rare', 'max_quantity' => 100000, 'bonus_box_count' => 0, 'discount_percent' => null, 'manual_claim' => 1],
            ['name' => '3 Mystery Box-a', 'weight' => 9, 'rarity' => 'Rare', 'max_quantity' => 100000, 'bonus_box_count' => 3, 'discount_percent' => null, 'manual_claim' => 0],
            ['name' => '1 Mystery Box', 'weight' => 10, 'rarity' => 'Common', 'max_quantity' => 100000, 'bonus_box_count' => 1, 'discount_percent' => null, 'manual_claim' => 0],
            ['name' => '2 Mystery Box-a + 10% popusta', 'weight' => 6, 'rarity' => 'Rare', 'max_quantity' => 100000, 'bonus_box_count' => 2, 'discount_percent' => 10, 'manual_claim' => 0],
            ['name' => '1 Mystery Box + 15% popusta', 'weight' => 6, 'rarity' => 'Rare', 'max_quantity' => 100000, 'bonus_box_count' => 1, 'discount_percent' => 15, 'manual_claim' => 0],
            ['name' => 'GTA 6 Ultimate', 'weight' => 1, 'rarity' => 'Ultra Rare', 'max_quantity' => 2, 'bonus_box_count' => 0, 'discount_percent' => null, 'manual_claim' => 1],
            ['name' => 'Red Dead Redemption 2', 'weight' => 8, 'rarity' => 'Common', 'max_quantity' => 100000, 'bonus_box_count' => 0, 'discount_percent' => null, 'manual_claim' => 1],
        ];
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
            ['name' => '10% popusta na sledeću kupovinu', 'importance' => 4, 'max_quantity' => 100, 'discount_percent' => 10, 'manual_claim' => 0],
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
        if (!is_array($cover) || ($cover['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $existing ?: '/assets/placeholder-cover.svg';
        }

        if (($cover['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return $existing ?: '/assets/placeholder-cover.svg';
        }

        $temporaryPath = (string) ($cover['tmp_name'] ?? '');
        if ($temporaryPath === '' || !is_file($temporaryPath)) {
            return $existing ?: '/assets/placeholder-cover.svg';
        }

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $type = @mime_content_type($temporaryPath);
        if (!isset($allowed[$type])) {
            return $existing ?: '/assets/placeholder-cover.svg';
        }

        $dir = app_path('public/uploads');
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('Folder za cover slike nije moguće pripremiti.');
            }
        }

        $name = bin2hex(random_bytes(16)) . '.' . $allowed[$type];
        if (!move_uploaded_file($temporaryPath, $dir . DIRECTORY_SEPARATOR . $name)) {
            throw new \RuntimeException('Cover slika nije mogla da se sačuva.');
        }

        return '/uploads/' . $name;
    }

    /** @param array<string,mixed> $old @param array<string,mixed> $new */
    private function logChanges(int $prizeId, array $old, array $new): void
    {
        $fields = ['name', 'description', 'instructions', 'cover_path', 'rarity', 'weight', 'max_quantity', 'bonus_box_count', 'discount_percent', 'manual_claim', 'active'];
        $stmt = Database::pdo()->prepare(
            'INSERT INTO prize_change_log (prize_id, field_name, old_value, new_value, admin_id, created_at)
             VALUES (:prize_id, :field_name, :old_value, :new_value, :admin_id, :created_at)'
        );
        foreach ($fields as $field) {
            $oldValue = $old[$field] ?? null;
            $newValue = $new[$field] ?? null;
            if ((string) $oldValue === (string) $newValue) {
                continue;
            }
            $stmt->execute([
                'prize_id' => $prizeId,
                'field_name' => $field,
                'old_value' => $oldValue === null ? null : (string) $oldValue,
                'new_value' => $newValue === null ? null : (string) $newValue,
                'admin_id' => isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null,
                'created_at' => date('c'),
            ]);
        }
    }
}
