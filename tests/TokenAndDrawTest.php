<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Tests;

use PlayWorld\MysteryBox\Services\DrawService;
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

    public function testExpiredResultIsNotVisibleAfterRetentionWindow(): void
    {
        $draw = ['created_at' => (new \DateTimeImmutable('-6 days'))->format('c')];

        $this->assertFalse((new DrawService())->isVisible($draw));
    }
}
