<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Tests;

use PlayWorld\MysteryBox\Services\DrawService;
use PlayWorld\MysteryBox\Services\PrizeService;
use PlayWorld\MysteryBox\Services\TokenService;
use PlayWorld\MysteryBox\Support\Database;

final class TokenAndDrawTest extends TestCase
{
    public function testGeneratedTokenIsHashedAndUsable(): void
    {
        $created = (new TokenService())->generate(1);

        $this->assertCount(1, $created);
        $plain = $created[0]['token'];
        $token = (new TokenService())->findPlain($plain);

        $this->assertNotNull($token);
        $this->assertSame('Active', $token['status']);
        $this->assertNotSame($plain, $token['token_hash']);
        $this->assertSame(hash('sha256', $plain), $token['token_hash']);
    }

    public function testTokenLookupIgnoresTrailingFormattingCharacters(): void
    {
        $created = (new TokenService())->generate(1);
        $plain = $created[0]['token'];
        $token = (new TokenService())->findPlain('*' . $plain . '*');

        $this->assertNotNull($token);
        $this->assertSame('Active', $token['status']);
    }

    public function testTokenLookupKeepsBase64UrlUnderscores(): void
    {
        $hash = hash('sha256', '_test_token_');
        $now = date('c');
        Database::pdo()->prepare(
            'INSERT INTO tokens (token_hash, status, created_at, updated_at) VALUES (:hash, "Active", :created, :updated)'
        )->execute(['hash' => $hash, 'created' => $now, 'updated' => $now]);

        $token = (new TokenService())->findPlain('_test_token_');

        $this->assertNotNull($token);
        $this->assertSame('Active', $token['status']);
    }

    public function testDrawIsIdempotentForUsedToken(): void
    {
        $now = date('c');
        Database::pdo()->prepare(
            'INSERT INTO prizes (name, description, instructions, cover_path, rarity, weight, max_quantity, won_count, active, created_at, updated_at)
            VALUES ("Gift Card", "Opis", "Uputstvo", "/assets/placeholder-cover.svg", "Standard", 10, 5, 0, 1, :created, :updated)'
        )->execute(['created' => $now, 'updated' => $now]);

        $plain = (new TokenService())->generate(1)[0]['token'];
        $service = new DrawService();
        $first = $service->open($plain);
        $second = $service->open($plain);

        $this->assertSame($first['draw_id'], $second['draw_id']);
        $this->assertSame('Gift Card', $first['prize_name']);
        $won = Database::pdo()->query('SELECT won_count FROM prizes LIMIT 1')->fetchColumn();
        $this->assertSame(1, (int) $won);
    }

    public function testRarityRollUsesExactConfiguredRanges(): void
    {
        $service = new DrawService();
        $counts = array_fill_keys(PrizeService::RARITIES, 0);

        // Repeat each 1..100 roll equally so the configured percentages are
        // tested deterministically rather than relying on a flaky sample.
        for ($index = 0; $index < 1000; $index++) {
            $rarity = $service->pickRarity(PrizeService::RARITIES, ($index % 100) + 1);
            $counts[$rarity]++;
        }

        $this->assertSame(10, $counts['Ultra Rare']);
        $this->assertSame(50, $counts['Rare']);
        $this->assertSame(150, $counts['Special']);
        $this->assertSame(350, $counts['Standard']);
        $this->assertSame(440, $counts['Common']);

        // With Ultra Rare empty, the remaining tiers use 5/99, 15/99, 35/99,
        // and 44/99 rather than falling back to Common.
        $this->assertSame('Rare', $service->pickRarity(['Rare', 'Special', 'Standard', 'Common'], 5));
        $this->assertSame('Special', $service->pickRarity(['Rare', 'Special', 'Standard', 'Common'], 6));
        $this->assertSame('Common', $service->pickRarity(['Rare', 'Special', 'Standard', 'Common'], 99));
    }

    public function testActiveZeroWeightPrizeIsStillEligible(): void
    {
        $now = date('c');
        Database::pdo()->prepare(
            'INSERT INTO prizes (name, description, instructions, cover_path, rarity, weight, max_quantity, won_count, active, created_at, updated_at)
             VALUES ("Zero Weight Prize", "Opis", "Uputstvo", "/assets/placeholder-cover.svg", "Common", 0, 1, 100000, 1, :created, :updated)'
        )->execute(['created' => $now, 'updated' => $now]);

        $token = (new TokenService())->generate(1)[0]['token'];
        $draw = (new DrawService())->open($token);

        $this->assertSame('Zero Weight Prize', $draw['prize_name']);
    }

    public function testWeightDoesNotChangeEqualChanceWithinRarity(): void
    {
        $_ENV['ANTI_REPEAT_ENABLED'] = 'false';
        $now = date('c');
        $insert = Database::pdo()->prepare(
            'INSERT INTO prizes (name, description, instructions, cover_path, rarity, weight, max_quantity, won_count, active, created_at, updated_at)
             VALUES (:name, "Opis", "Uputstvo", "/assets/placeholder-cover.svg", "Common", :weight, 1, 0, 1, :created, :updated)'
        );
        $insert->execute(['name' => 'Weight One', 'weight' => 1, 'created' => $now, 'updated' => $now]);
        $insert->execute(['name' => 'Weight One Hundred', 'weight' => 100, 'created' => $now, 'updated' => $now]);

        $counts = ['Weight One' => 0, 'Weight One Hundred' => 0];
        for ($index = 0; $index < 600; $index++) {
            $token = (new TokenService())->generate(1)[0]['token'];
            $draw = (new DrawService())->open($token);
            $counts[$draw['prize_name']]++;
        }

        $this->assertGreaterThan(200, $counts['Weight One']);
        $this->assertGreaterThan(200, $counts['Weight One Hundred']);
    }

    public function testDrawCanBeVerifiedByDrawId(): void
    {
        $now = date('c');
        Database::pdo()->prepare(
            'INSERT INTO prizes (name, description, instructions, cover_path, rarity, weight, max_quantity, won_count, active, created_at, updated_at)
            VALUES ("NBA 2K26", "Opis", "Uputstvo", "/assets/placeholder-cover.svg", "Special", 10, 5, 0, 1, :created, :updated)'
        )->execute(['created' => $now, 'updated' => $now]);

        $plain = (new TokenService())->generate(1)[0]['token'];
        $service = new DrawService();
        $draw = $service->open($plain);
        $verified = $service->findByDrawId(strtolower($draw['draw_id']));

        $this->assertNotNull($verified);
        $this->assertSame($draw['draw_id'], $verified['draw_id']);
        $this->assertSame('NBA 2K26', $verified['prize_name']);
        $this->assertNull($service->findByDrawId('PW-FAKEFAKEFA'));
    }

    public function testExpiredResultIsNotVisibleAfterRetentionWindow(): void
    {
        $draw = ['created_at' => (new \DateTimeImmutable('-6 days'))->format('c')];

        $this->assertFalse((new DrawService())->isVisible($draw));
    }

    public function testPlayWorldCatalogSeedIsRepeatable(): void
    {
        $service = new PrizeService();

        $first = $service->seedPlayWorldCatalog();
        $second = $service->seedPlayWorldCatalog();

        $this->assertSame(28, $first['created']);
        $this->assertSame(0, $first['updated']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(28, $second['updated']);
        $this->assertSame(28, (int) Database::pdo()->query('SELECT COUNT(*) FROM prizes')->fetchColumn());
    }

    public function testBonusCatalogSeedIsRepeatableAndUsesRequestedLimits(): void
    {
        $service = new PrizeService();

        $first = $service->seedBonusCatalog();
        $second = $service->seedBonusCatalog();

        $this->assertSame(15, $first['created']);
        $this->assertSame(0, $first['updated']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(15, $second['updated']);

        $gta = Database::pdo()->query("SELECT rarity, weight, max_quantity, active FROM prizes WHERE name = 'GTA 6 Ultimate'")->fetch();
        $this->assertSame('Ultra Rare', $gta['rarity']);
        $this->assertSame(1, (int) $gta['weight']);
        $this->assertSame(2, (int) $gta['max_quantity']);
        $this->assertSame(1, (int) $gta['active']);

        $common = Database::pdo()->query("SELECT rarity, weight, max_quantity FROM prizes WHERE name = '1 Mystery Box'")->fetch();
        $this->assertSame('Common', $common['rarity']);
        $this->assertSame(10, (int) $common['weight']);
        $this->assertSame(100000, (int) $common['max_quantity']);
    }

    public function testPrizeCanBeToggledWithoutChangingItsDrawCount(): void
    {
        $service = new PrizeService();
        $service->seedBonusCatalog();
        $prize = $service->all()[0];

        $this->assertTrue($service->toggle((int) $prize['id']));
        $disabled = $service->find((int) $prize['id']);
        $this->assertSame(0, (int) $disabled['active']);
        $this->assertSame(0, (int) $disabled['won_count']);

        $this->assertTrue($service->toggle((int) $prize['id']));
        $enabled = $service->find((int) $prize['id']);
        $this->assertSame(1, (int) $enabled['active']);
    }
}
