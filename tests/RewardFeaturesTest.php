<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Tests;

use PlayWorld\MysteryBox\Services\DiscountCodeService;
use PlayWorld\MysteryBox\Services\DrawService;
use PlayWorld\MysteryBox\Services\PrizeService;
use PlayWorld\MysteryBox\Services\RewardClaimService;
use PlayWorld\MysteryBox\Services\RewardDeliveryService;
use PlayWorld\MysteryBox\Services\TokenService;
use PlayWorld\MysteryBox\Services\N8nRewardClaimsApi;
use PlayWorld\MysteryBox\Support\Database;

final class RewardFeaturesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['APP_KEY'] = 'test-application-key';
        $_ENV['ANTI_REPEAT_ENABLED'] = 'true';
        $_ENV['ANTI_REPEAT_SECRET'] = 'test-anti-repeat-secret';
        $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
    }

    public function testPrizeSnapshotDoesNotChangeAfterPrizeEdit(): void
    {
        $id = $this->insertPrize('Snapshot Prize', 10, 2, 0, null, 0);
        $token = (new TokenService())->generate(1)[0]['token'];
        $draw = (new DrawService())->open($token);

        Database::pdo()->prepare('UPDATE prizes SET name = "Changed Prize", description = "Changed", discount_percent = 25, bonus_box_count = 4, manual_claim = 1 WHERE id = :id')->execute(['id' => $id]);

        $again = (new DrawService())->findByDrawId($draw['draw_id']);
        $this->assertSame('Snapshot Prize', $again['prize_name']);
        $this->assertSame('Opis Snapshot Prize', $again['prize_description']);
        $this->assertNull($again['discount_percent']);
        $this->assertSame(0, (int) $again['bonus_box_count']);
    }

    public function testPrizeRemainsEligibleAfterLegacyMaxQuantity(): void
    {
        $this->insertPrize('Limited Prize', 10, 2, 2, null, 0);
        $available = (new PrizeService())->activeAvailable();
        $names = array_column($available, 'name');
        $this->assertContains('Limited Prize', $names);

        $token = (new TokenService())->generate(1)[0]['token'];
        $draw = (new DrawService())->open($token);
        $this->assertSame('Limited Prize', $draw['prize_name']);
        $this->assertSame(3, (int) Database::pdo()->query("SELECT won_count FROM prizes WHERE name = 'Limited Prize'")->fetchColumn());
    }

    public function testDiscountCodeIsAssignedOnceAndRefreshKeepsIt(): void
    {
        $this->insertPrize('Discount Prize', 10, 10, 0, 10, 0);
        (new DiscountCodeService())->import("PW10-ONE\nPW10-TWO\n", 10);
        $token = (new TokenService())->generate(1)[0]['token'];
        $draw = (new DrawService())->open($token);
        $code = (new DiscountCodeService())->forDraw($draw['draw_id']);
        $this->assertSame('PW10-ONE', $code['code']);
        $this->assertSame($code['code'], (new DiscountCodeService())->forDraw($draw['draw_id'])['code']);
        $this->assertSame(1, (int) Database::pdo()->query('SELECT COUNT(*) FROM discount_codes WHERE status = "Assigned"')->fetchColumn());
    }

    public function testBulkDiscountImportReportsAvailableCodes(): void
    {
        $contents = implode("\n", array_map(static fn (int $number): string => 'PW10-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT), range(1, 500)));
        $result = (new DiscountCodeService())->import($contents, 10);

        $this->assertSame(500, $result['created']);
        $this->assertSame(0, $result['duplicates']);
        $this->assertSame(0, $result['invalid']);
        $this->assertSame(500, (new DiscountCodeService())->availableCount(10));
    }

    public function testDiscountPrizeWithoutCodesIsExcluded(): void
    {
        $this->insertPrize('No Code Prize', 100, 10, 0, 25, 0);
        $this->insertPrize('Fallback Prize', 1, 10, 0, null, 0);
        $token = (new TokenService())->generate(1)[0]['token'];
        $draw = (new DrawService())->open($token);
        $this->assertSame('Fallback Prize', $draw['prize_name']);
    }

    public function testBonusDeliveryIsIdempotentAndCreatesExactCount(): void
    {
        $this->insertPrize('Bonus Prize', 10, 10, 0, null, 2);
        $token = (new TokenService())->generate(1)[0]['token'];
        $draw = (new DrawService())->open($token);
        $sent = 0;
        $service = new RewardDeliveryService(static function (string $email, string $name, array $links) use (&$sent): void {
            $sent++;
            if (count($links) !== 2) {
                throw new \RuntimeException('wrong link count');
            }
        });

        $first = $service->claim($draw, 'buyer@example.com');
        $second = $service->claim($draw, 'buyer@example.com');

        $this->assertSame('Sent', $first['status']);
        $this->assertTrue($second['idempotent_replay']);
        $this->assertSame(1, $sent);
        $this->assertSame(3, (int) Database::pdo()->query('SELECT COUNT(*) FROM tokens')->fetchColumn());
    }

    public function testManualClaimIsUniquePerDraw(): void
    {
        $this->insertPrize('Manual Prize', 10, 10, 0, null, 0, 1);
        $token = (new TokenService())->generate(1)[0]['token'];
        $draw = (new DrawService())->open($token);
        $service = new RewardClaimService();
        $first = $service->create($draw, 'buyer@example.com', '+381 60 123 4567');
        $second = $service->create($draw, 'buyer@example.com', '+381601234567');
        $this->assertFalse($first['idempotent_replay']);
        $this->assertTrue($second['idempotent_replay']);
        $this->assertSame(1, (int) Database::pdo()->query('SELECT COUNT(*) FROM reward_claims')->fetchColumn());
    }

    public function testRewardClaimLeaseAndNotificationAreIdempotent(): void
    {
        $this->insertPrize('Telegram Prize', 10, 10, 0, null, 0, 1);
        $token = (new TokenService())->generate(1)[0]['token'];
        $draw = (new DrawService())->open($token);
        $claim = (new RewardClaimService())->create($draw, 'buyer@example.com', '+381601234567');
        $_ENV['API_ENABLED'] = 'true';
        $_ENV['N8N_API_KEY'] = 'api-key';
        $api = new N8nRewardClaimsApi();
        $headers = ['Authorization' => 'Bearer api-key', 'Content-Type' => 'application/json'];
        $leased = $api->lease('POST', $headers, json_encode(['limit' => 20, 'worker' => 'telegram'], JSON_THROW_ON_ERROR));
        $this->assertSame(200, $leased['status']);
        $this->assertCount(1, $leased['body']['claims']);
        $result = $api->notificationResult((int) $claim['claim_id'], 'POST', $headers, json_encode(['worker' => 'telegram', 'status' => 'Sent', 'provider_message_id' => '123'], JSON_THROW_ON_ERROR));
        $this->assertSame(200, $result['status']);
        $replayed = $api->notificationResult((int) $claim['claim_id'], 'POST', $headers, json_encode(['worker' => 'telegram', 'status' => 'Sent'], JSON_THROW_ON_ERROR));
        $this->assertSame(200, $replayed['status']);
        $this->assertSame('Sent', $replayed['body']['notification_status']);
    }

    public function testAntiRepeatExcludesOnlyTheImmediatelyPreviousPrize(): void
    {
        $firstPrize = $this->insertPrize('First Prize', 10, 10, 1, null, 0);
        $secondPrize = $this->insertPrize('Second Prize', 10, 10, 0, null, 0);
        $oldToken = (new TokenService())->generate(1)[0];
        $fingerprint = hash_hmac('sha256', '192.0.2.10', 'test-anti-repeat-secret');
        $now = date('c');
        Database::pdo()->prepare(
            'INSERT INTO draws (draw_id, token_id, prize_id, token_mask, created_at, snapshot_name, snapshot_description, snapshot_instructions, snapshot_cover_path, snapshot_rarity, snapshot_bonus_box_count, snapshot_manual_claim, client_fingerprint, anti_repeat_applied)
             VALUES ("PW-AAAAAAAAAA", :token_id, :prize_id, "masked", :created_at, "First Prize", "Opis", "", "/assets/placeholder-cover.svg", "Common", 0, 0, :fingerprint, 1)'
        )->execute(['token_id' => $oldToken['id'], 'prize_id' => $firstPrize, 'created_at' => $now, 'fingerprint' => $fingerprint]);
        Database::pdo()->prepare('UPDATE tokens SET status = "Used", used_at = :now WHERE id = :id')->execute(['now' => $now, 'id' => $oldToken['id']]);

        $newToken = (new TokenService())->generate(1)[0]['token'];
        $draw = (new DrawService())->open($newToken);
        $this->assertSame('Second Prize', $draw['prize_name']);
        $this->assertSame(1, (int) $draw['anti_repeat_applied']);
        $stored = Database::pdo()->query('SELECT client_fingerprint FROM draws WHERE draw_id = "' . $draw['draw_id'] . '"')->fetchColumn();
        $this->assertSame($fingerprint, $stored);
        $this->assertStringNotContainsString('192.0.2.10', (string) $stored);
        $this->assertNotSame($firstPrize, $secondPrize);

        // Once a different prize has been drawn, the older prize is eligible again.
        Database::pdo()->prepare('UPDATE prizes SET weight = 0 WHERE id = :id')->execute(['id' => $secondPrize]);
        $thirdToken = (new TokenService())->generate(1)[0]['token'];
        $thirdDraw = (new DrawService())->open($thirdToken);
        $this->assertSame('First Prize', $thirdDraw['prize_name']);
        $this->assertSame(0, (int) $thirdDraw['anti_repeat_applied']);
    }

    private function insertPrize(string $name, int $weight, int $max, int $won, ?int $discount, int $bonus, int $manual = 0): int
    {
        $now = date('c');
        $stmt = Database::pdo()->prepare(
            'INSERT INTO prizes (name, description, instructions, cover_path, rarity, weight, max_quantity, bonus_box_count, discount_percent, manual_claim, won_count, active, created_at, updated_at)
             VALUES (:name, :description, "", "/assets/placeholder-cover.svg", "Common", :weight, :max, :bonus, :discount, :manual, :won, 1, :created, :updated)'
        );
        $stmt->execute([
            'name' => $name, 'description' => 'Opis ' . $name, 'weight' => $weight, 'max' => $max,
            'bonus' => $bonus, 'discount' => $discount, 'manual' => $manual, 'won' => $won,
            'created' => $now, 'updated' => $now,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }
}
