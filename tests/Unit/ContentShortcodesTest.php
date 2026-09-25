<?php

declare(strict_types=1);

namespace Everblocklight\Tests\Unit;

use Everblocklight\Tools\Service\EverblocklightTools;
use PHPUnit\Framework\Attributes\DataProvider;

final class ContentShortcodesTest extends TestCase
{
    public function testAlertWithValidType(): void
    {
        self::assertSame(
            '<div class="alert alert-success" role="alert">Commande validée</div>',
            EverblocklightTools::getAlertShortcode('[alert type="success"]Commande validée[/alert]')
        );
    }

    public function testAlertFallsBackToInfo(): void
    {
        self::assertSame(
            '<div class="alert alert-info" role="alert">A</div><div class="alert alert-info" role="alert">B</div>',
            EverblocklightTools::getAlertShortcode('[alert]A[/alert][alert type="<script>"]B[/alert]')
        );
    }

    public function testAlertIsMultiline(): void
    {
        $html = EverblocklightTools::getAlertShortcode("[alert type=\"warning\"]\n<p>Ligne</p>\n[/alert]");

        self::assertStringContainsString('alert-warning', $html);
        self::assertStringContainsString('<p>Ligne</p>', $html);
    }

    public static function videoProvider(): array
    {
        return [
            'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
            'youtube court' => ['https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
            'youtube embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ?si=abc', 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
            'youtube live' => ['https://www.youtube.com/live/abc123', 'https://www.youtube.com/embed/abc123'],
            'vimeo' => ['https://vimeo.com/123456', 'https://player.vimeo.com/video/123456'],
            'dailymotion' => ['https://www.dailymotion.com/video/x8abc', '//www.dailymotion.com/embed/video/x8abc'],
            'vidyard' => ['https://vidyard.com/watch/AbC-123', 'https://play.vidyard.com/AbC-123.html'],
        ];
    }

    #[DataProvider('videoProvider')]
    public function testDetectVideoSite(string $url, string $expectedSrc): void
    {
        self::assertStringContainsString($expectedSrc, EverblocklightTools::detectVideoSite($url));
    }

    public function testUnknownVideoIsLeftUntouched(): void
    {
        self::assertSame('', EverblocklightTools::detectVideoSite('https://example.com/video.mp4'));
        self::assertSame('[video https://example.com/x]', EverblocklightTools::getVideoShortcode('[video https://example.com/x]'));
    }

    public function testVideoShortcodeIsReplaced(): void
    {
        $html = EverblocklightTools::getVideoShortcode('<p>[video https://youtu.be/dQw4w9WgXcQ]</p>');

        self::assertStringStartsWith('<p><iframe', $html);
        self::assertStringNotContainsString('[video', $html);
    }
}
