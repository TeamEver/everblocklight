<?php

declare(strict_types=1);

namespace Everblocklight\Tests\Unit;

use Everblocklight\Tools\Entity\Block;
use Everblocklight\Tools\Entity\Shortcode;

final class EntitiesTest extends TestCase
{
    public function testBlockFromDatabaseMapsAndCastsColumns(): void
    {
        $block = Block::fromDatabase([
            'id_everblocklight' => '12',
            'name' => 'Bandeau',
            'id_hook' => '7',
            'only_home' => '1',
            'device' => '2',
            'position' => '3',
            'categories' => '[4,5]',
            'css_class' => '',
            'modal' => '0',
            'timeout' => '1500',
            'date_start' => '2026-01-01 00:00:00',
            'active' => '1',
        ], [
            ['id_lang' => '1', 'content' => '<p>FR</p>', 'custom_code' => ''],
            ['id_lang' => '2', 'content' => '<p>EN</p>', 'custom_code' => '<style></style>'],
        ]);

        self::assertSame(12, $block->id);
        self::assertSame(12, $block->id_everblocklight);
        self::assertSame(7, $block->id_hook);
        self::assertTrue($block->only_home);
        self::assertFalse($block->only_category);
        self::assertSame(2, $block->device);
        self::assertSame('[4,5]', $block->categories);
        self::assertNull($block->css_class, 'Une chaîne vide devient null');
        self::assertFalse($block->modal);
        self::assertSame(1500, $block->timeout);
        self::assertSame(1, $block->id_shop, 'Boutique 1 par défaut');
        self::assertSame(['1' => '<p>FR</p>', '2' => '<p>EN</p>'], array_map('strval', $block->content));
    }

    public function testBlockTranslationFallbacks(): void
    {
        \Configuration::$values['PS_LANG_DEFAULT'] = 2;
        $block = Block::fromDatabase(['id_everblocklight' => 1], [
            ['id_lang' => 1, 'content' => ''],
            ['id_lang' => 2, 'content' => 'Défaut'],
            ['id_lang' => 3, 'content' => 'Autre'],
        ]);

        self::assertSame('Autre', $block->getContent(3), 'Langue demandée');
        self::assertSame('Défaut', $block->getContent(1), 'Traduction vide : langue par défaut');
        self::assertSame('Défaut', $block->getContent(99), 'Langue inconnue : langue par défaut');

        \Configuration::$values['PS_LANG_DEFAULT'] = 1;
        self::assertSame('Défaut', $block->getContent(99), 'Défaut vide : première traduction non vide');
    }

    public function testBootstrapColClass(): void
    {
        self::assertSame('', Block::getBootstrapColClass(0));
        self::assertSame('col-4 col-md-4', Block::getBootstrapColClass(3));
        self::assertSame('col-12 col-md-12', Block::getBootstrapColClass(5), 'Valeur inconnue : pleine largeur');
    }

    public function testShortcodeFromDatabaseAllLanguages(): void
    {
        $shortcode = Shortcode::fromDatabase(
            ['id_everblocklight_shortcode' => '3', 'shortcode' => '[promo]', 'id_shop' => '2'],
            [
                ['id_lang' => 1, 'title' => 'Promo', 'content' => '-10 %'],
                ['id_lang' => 2, 'title' => 'Sale', 'content' => '10% off'],
            ]
        );

        self::assertSame(3, $shortcode->id);
        self::assertSame('[promo]', $shortcode->shortcode);
        self::assertSame(2, $shortcode->id_shop);
        self::assertSame([1 => 'Promo', 2 => 'Sale'], $shortcode->title);
        self::assertSame([1 => '-10 %', 2 => '10% off'], $shortcode->content);
    }

    public function testShortcodeFromDatabaseSingleLanguage(): void
    {
        $shortcode = Shortcode::fromDatabase(
            ['id_everblocklight_shortcode' => 3, 'shortcode' => '[promo]', 'id_lang' => 2, 'title' => 'Sale', 'content' => '10% off'],
            [],
            2
        );

        self::assertSame('Sale', $shortcode->title);
        self::assertSame('10% off', $shortcode->content);
    }
}
