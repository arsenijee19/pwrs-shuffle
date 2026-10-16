<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PlayWorld\MysteryBox\Services\DrawService;
use PlayWorld\MysteryBox\Services\PdfService;
use PlayWorld\MysteryBox\Services\RewardClaimService;
use PlayWorld\MysteryBox\Services\TokenService;
use PlayWorld\MysteryBox\Support\Database;

final class ClaimFlowTest extends TestCase
{
    private const LEGACY_INSTRUCTIONS = 'Sačuvajte PDF potvrdu i javite se PlayWorld timu za preuzimanje nagrade.';

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['APP_KEY'] = 'test-application-key';
        $_ENV['ANTI_REPEAT_ENABLED'] = 'false';
        $_SERVER['REMOTE_ADDR'] = '192.0.2.20';
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function phoneNumbers(): array
    {
        return [
            'plus prefix' => ['+381601234567', '381601234567'],
            'plus with spaces' => ['+381 60 123 4567', '381601234567'],
            'double zero prefix' => ['00381601234567', '381601234567'],
            'local number' => ['0601234567', '381601234567'],
            'local with dash' => ['060 123-4567', '381601234567'],
            'already international' => ['381601234567', '381601234567'],
        ];
    }

    #[DataProvider('phoneNumbers')]
    public function testPhoneInternationalNormalisation(string $input, string $expected): void
    {
        $this->assertSame($expected, phone_international($input));
    }

    public function testClaimStatusLabelsAreSerbian(): void
    {
        $this->assertSame('Novo', claim_status('New')['label']);
        $this->assertSame('U toku', claim_status('InProgress')['label']);
        $this->assertSame('Isporučeno', claim_status('Delivered')['label']);
        $this->assertSame('Otkazano', claim_status('Cancelled')['label']);
        $this->assertSame('Novo', claim_status('Something else')['label']);
    }

    public function testClaimCreationIsIdempotentForSameContactAndRejectsAnotherContact(): void
    {
        $draw = $this->openDraw('Claim Prize', 0, null, 1);
        $service = new RewardClaimService();

        $first = $service->create($draw, 'Marko@Example.test', '+381 60 123 4567');
        $this->assertFalse($first['replay'] ?? false);
        $this->assertSame('New', $first['status']);

        $again = $service->create($draw, 'marko@example.test', '+381601234567');
        $this->assertSame($first['claim_id'], $again['claim_id']);
        $this->assertSame(1, (int) Database::pdo()->query('SELECT COUNT(*) FROM reward_claims')->fetchColumn());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('kontaktirajte PlayWorld tim');
        $service->create($draw, 'other@example.test', '+381601234567');
    }

    public function testClaimRejectsInvalidInputWithPoliteMessages(): void
    {
        $draw = $this->openDraw('Claim Prize', 0, null, 1);
        $service = new RewardClaimService();

        try {
            $service->create($draw, 'not-an-email', '+381601234567');
            $this->fail('Invalid email must be rejected.');
        } catch (\InvalidArgumentException $error) {
            $this->assertStringContainsString('Molimo Vas', $error->getMessage());
        }

        try {
            $service->create($draw, 'ok@example.test', '12');
            $this->fail('Invalid phone must be rejected.');
        } catch (\InvalidArgumentException $error) {
            $this->assertStringContainsString('telefona', $error->getMessage());
        }

        $this->assertSame(0, (int) Database::pdo()->query('SELECT COUNT(*) FROM reward_claims')->fetchColumn());
    }

    public function testStatusChangeIsRecordedAndUnknownStatusIsRejected(): void
    {
        $draw = $this->openDraw('Claim Prize', 0, null, 1);
        $service = new RewardClaimService();
        $claim = $service->create($draw, 'marko@example.test', '+381601234567');

        $this->assertFalse($service->updateStatus($claim['claim_id'], 'Nope'));
        $this->assertTrue($service->updateStatus($claim['claim_id'], 'InProgress'));
        $this->assertTrue($service->updateStatus($claim['claim_id'], 'Delivered'));

        $stored = $service->findById($claim['claim_id']);
        $this->assertSame('Delivered', $stored['status']);
        $this->assertNotEmpty($stored['completed_at']);
        $this->assertSame(2, (int) Database::pdo()->query('SELECT COUNT(*) FROM reward_claim_events WHERE claim_id = ' . (int) $claim['claim_id'])->fetchColumn());
    }

    /** @return array<string,array{0:int,1:?int,2:int}> */
    public static function prizeTypes(): array
    {
        return [
            'manual claim' => [0, null, 1],
            'bonus boxes' => [2, null, 0],
            'discount' => [0, 10, 0],
        ];
    }

    #[DataProvider('prizeTypes')]
    public function testCertificateIsOnePageWithLinkToTheResult(int $bonus, ?int $discount, int $manual): void
    {
        $draw = $this->openDraw('PDF Prize', $bonus, $discount, $manual);
        $url = 'https://example.test/box/' . bin2hex(random_bytes(8));

        $pdf = (new PdfService())->render($draw, $url);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(1, preg_match_all('/\/Type \/Page\b/', $pdf), 'Certificate must fit on one page.');
        $this->assertStringContainsString('/URI (' . $url . ')', $pdf, 'Certificate must contain a clickable link to the result.');
    }

    public function testLegacyPrizeInstructionsAreClearedByMigration(): void
    {
        $now = date('c');
        $pdo = Database::pdo();
        $insert = $pdo->prepare(
            'INSERT INTO prizes (name, description, instructions, cover_path, rarity, weight, max_quantity, bonus_box_count, discount_percent, manual_claim, won_count, active, created_at, updated_at)
             VALUES (:name, "Opis", :instructions, "/assets/placeholder-cover.svg", "Common", 1, 1, 0, NULL, 1, 0, 1, :now, :now)'
        );
        $insert->execute(['name' => 'Legacy', 'instructions' => self::LEGACY_INSTRUCTIONS, 'now' => $now]);
        $insert->execute(['name' => 'Custom', 'instructions' => 'Posebno uputstvo za ovu nagradu.', 'now' => $now]);

        $pdo->exec((string) file_get_contents(app_path('database/migrations/011_claim_flow_copy.sql')));

        $this->assertSame('', $pdo->query("SELECT instructions FROM prizes WHERE name = 'Legacy'")->fetchColumn());
        $this->assertSame('Posebno uputstvo za ovu nagradu.', $pdo->query("SELECT instructions FROM prizes WHERE name = 'Custom'")->fetchColumn());
    }

    public function testInvalidLinkMessagesAreServedInSerbian(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ovaj link nije pronađen');
        (new DrawService())->open('does-not-exist');
    }

    public function testDatabaseMigrateRecordsMigrationsAndIsRepeatable(): void
    {
        Database::reset();
        $path = 'storage/database/test-migrate-' . bin2hex(random_bytes(6)) . '.sqlite';
        $_ENV['DB_PATH'] = $path;

        try {
            Database::migrate();
            Database::migrate();

            $files = count(glob(app_path('database/migrations/*.sql')) ?: []);
            $recorded = (int) Database::pdo()->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
            $this->assertSame($files, $recorded);
        } finally {
            Database::reset();
            $_ENV['DB_PATH'] = $this->dbPath;
            @unlink(app_path($path));
        }
    }

    /** @return array<string,mixed> */
    private function openDraw(string $name, int $bonus, ?int $discount, int $manual): array
    {
        $now = date('c');
        $pdo = Database::pdo();
        $pdo->exec('UPDATE prizes SET active = 0');
        $pdo->prepare(
            'INSERT INTO prizes (name, description, instructions, cover_path, rarity, weight, max_quantity, bonus_box_count, discount_percent, manual_claim, won_count, active, created_at, updated_at)
             VALUES (:name, :description, "", "/assets/placeholder-cover.svg", "Rare", 10, 1, :bonus, :discount, :manual, 0, 1, :now, :now)'
        )->execute([
            'name' => $name, 'description' => 'Opis ' . $name, 'bonus' => $bonus,
            'discount' => $discount, 'manual' => $manual, 'now' => $now,
        ]);

        if ($discount !== null) {
            (new \PlayWorld\MysteryBox\Services\DiscountCodeService())->import("PW-TEST-ONE\nPW-TEST-TWO\n", $discount);
        }

        $token = (new TokenService())->generate(1)[0]['token'];
        return (new DrawService())->open($token);
    }
}
