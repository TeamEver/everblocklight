<?php

declare(strict_types=1);

namespace Everblocklight\Tests\Unit;

use Everblocklight\Tools\Service\EverblocklightTools;
use PHPUnit\Framework\Attributes\DataProvider;

final class ShortcodeParsingTest extends TestCase
{
    public static function tokenProvider(): array
    {
        return [
            'chaine vide' => ['', false],
            'texte brut' => ['Bonjour tout le monde', false],
            'shortcode simple' => ['<p>[cart_total]</p>', true],
            'shortcode avec attributs' => ['[product id="1,2" carousel=true]', true],
            'hook smarty' => ["{hook h='displayHome'}", true],
            'variable smarty' => ['{$shop.name}', true],
            'tableau JS ignoré' => ['var a = [1, 2, 3];', false],
            'accolades CSS ignorées' => ['.a { color: red; }', false],
        ];
    }

    #[DataProvider('tokenProvider')]
    public function testHasShortcodeToken(string $html, bool $expected): void
    {
        self::assertSame($expected, EverblocklightTools::hasShortcodeToken($html));
    }

    public function testParseShortcodeAttrs(): void
    {
        $attrs = self::callStatic(
            EverblocklightTools::class,
            'parseShortcodeAttrs',
            'tag="summer|sale" LIMIT="8"  order = "price" empty=""'
        );

        self::assertSame(['summer', 'sale'], array_values($attrs['tag']));
        self::assertSame('8', $attrs['limit'], 'Les clés sont normalisées en minuscules');
        self::assertSame('price', $attrs['order']);
        self::assertSame('', $attrs['empty']);
    }

    public function testParseShortcodeAttrsIgnoresUnquotedValues(): void
    {
        self::assertSame([], self::callStatic(EverblocklightTools::class, 'parseShortcodeAttrs', 'limit=8 carousel=true'));
    }

    public static function booleanProvider(): array
    {
        return [
            [null, null], [true, true], [false, false], ['', null],
            ['1', true], ['TRUE', true], ['yes', true], ['on', true],
            ['0', false], ['false', false], ['No', false], ['off', false],
            ['peut-être', null],
        ];
    }

    #[DataProvider('booleanProvider')]
    public function testParseBoolean($value, ?bool $expected): void
    {
        self::assertSame($expected, self::callStatic(EverblocklightTools::class, 'parseBoolean', $value));
    }

    public function testLinkRewriteUsesCoreSlugger(): void
    {
        self::assertSame('mon-guide-d-achat', EverblocklightTools::linkRewrite('  Mon Guide d\'achat '));
    }
}
