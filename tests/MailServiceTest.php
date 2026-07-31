<?php

declare(strict_types=1);

namespace PlayWorld\MysteryBox\Tests;

use PlayWorld\MysteryBox\Services\MailService;

final class MailServiceTest extends TestCase
{
    public function testMysteryBoxEmailTemplateContainsButtonAndLink(): void
    {
        $link = 'https://shuffle.playworld.rs/box/test-token';
        $html = (new MailService())->mysteryBoxHtml($link, 'Marko');
        $text = (new MailService())->mysteryBoxText($link, 'Marko');

        $this->assertStringContainsString('Otvori Mystery Box', $html);
        $this->assertStringContainsString($link, $html);
        $this->assertStringContainsString('Poštovani/a Marko', $html);
        $this->assertStringContainsString($link, $text);
    }
}
