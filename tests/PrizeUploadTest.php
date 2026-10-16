<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Tests;

use PlayWorld\MysteryBox\Services\PrizeService;
use PlayWorld\MysteryBox\Support\Database;

final class PrizeUploadTest extends TestCase
{
    /** @return array<string,mixed> */
    private function prizeData(): array
    {
        return [
            'name' => 'Upload prize', 'description' => 'Opis', 'instructions' => '', 'rarity' => 'Rare',
            'weight' => '5', 'max_quantity' => '1', 'bonus_box_count' => '0', 'active' => 'on',
        ];
    }

    public function testNonImageUploadIsRejectedWithClearMessageAndNothingIsSaved(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cover');
        file_put_contents($tmp, '<?php echo 1;');

        try {
            (new PrizeService())->save($this->prizeData(), ['error' => UPLOAD_ERR_OK, 'tmp_name' => $tmp, 'name' => 'evil.php']);
            $this->fail('A non-image upload must be rejected.');
        } catch (\InvalidArgumentException $error) {
            $this->assertStringContainsString('podržanom formatu', $error->getMessage());
        } finally {
            @unlink($tmp);
        }

        $this->assertSame(0, (int) Database::pdo()->query("SELECT COUNT(*) FROM prizes WHERE name = 'Upload prize'")->fetchColumn());
    }

    public function testUploadTooLargeForPhpIsReportedAsTooLarge(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('prevelika');
        (new PrizeService())->save($this->prizeData(), ['error' => UPLOAD_ERR_INI_SIZE, 'tmp_name' => '', 'name' => 'big.png']);
    }

    public function testSavingWithoutAFileUsesThePlaceholderCover(): void
    {
        (new PrizeService())->save($this->prizeData(), ['error' => UPLOAD_ERR_NO_FILE, 'tmp_name' => '', 'name' => '']);

        $cover = Database::pdo()->query("SELECT cover_path FROM prizes WHERE name = 'Upload prize'")->fetchColumn();
        $this->assertSame('/assets/placeholder-cover.svg', $cover);
    }
}
