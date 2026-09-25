<?php

declare(strict_types=1);

namespace Everblocklight\Tests\Unit;

use Everblocklight\Tools\Service\EverblocklightCache;
use ReflectionClass;

final class CacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Vide le cache mémoire statique entre deux tests.
        $reflection = new ReflectionClass(EverblocklightCache::class);
        foreach (['runtimeCache', 'runtimeStored'] as $property) {
            $reflection->getProperty($property)->setValue(null, []);
        }
    }

    public function testStoreAndRetrieve(): void
    {
        self::assertFalse(EverblocklightCache::isCacheStored('everblocklight-a'));

        EverblocklightCache::cacheStore('everblocklight-a', ['x' => 1]);

        self::assertTrue(EverblocklightCache::isCacheStored('everblocklight-a'));
        self::assertSame(['x' => 1], EverblocklightCache::cacheRetrieve('everblocklight-a'));
        self::assertSame(['x' => 1], \Cache::retrieve('everblocklight-a'), 'Écrit aussi dans le cache PrestaShop');
    }

    public function testRetrieveMissingKeyReturnsEmptyString(): void
    {
        self::assertSame('', EverblocklightCache::cacheRetrieve('inconnue'));
    }

    public function testDropByPatternOnlyRemovesMatchingKeys(): void
    {
        EverblocklightCache::cacheStore('everblocklight-block-1', 'a');
        EverblocklightCache::cacheStore('everblocklight-block-2', 'b');
        EverblocklightCache::cacheStore('autre-module', 'c');

        EverblocklightCache::cacheDropByPattern('everblocklight-block-');

        self::assertFalse(EverblocklightCache::isCacheStored('everblocklight-block-1'));
        self::assertFalse(EverblocklightCache::isCacheStored('everblocklight-block-2'));
        self::assertTrue(EverblocklightCache::isCacheStored('autre-module'));
    }

    public function testObjectCacheVersionIncrements(): void
    {
        self::assertSame(1, EverblocklightCache::getObjectCacheVersion('block', 0), 'Objet sans id : version 1');
        self::assertSame(1, EverblocklightCache::getObjectCacheVersion('block', 5));

        EverblocklightCache::refreshObjectCacheVersion('block', 5);
        self::assertSame(2, EverblocklightCache::getObjectCacheVersion('block', 5));
        self::assertSame(1, EverblocklightCache::getObjectCacheVersion('block', 6), 'Les autres objets ne changent pas');
    }

    public function testClearAllModuleCacheKeepsForeignEntries(): void
    {
        EverblocklightCache::cacheStore('everblocklight-x', 1);
        EverblocklightCache::cacheStore('EverblocklightShortcode_getAllShortcodes_1_1', 2);
        EverblocklightCache::cacheStore('ps_core_entry', 3);

        EverblocklightCache::clearAllModuleCache();

        self::assertFalse(EverblocklightCache::isCacheStored('everblocklight-x'));
        self::assertFalse(EverblocklightCache::isCacheStored('EverblocklightShortcode_getAllShortcodes_1_1'));
        self::assertTrue(EverblocklightCache::isCacheStored('ps_core_entry'));
    }

    public function testModuleConfigurationIsCachedOutsideAdmin(): void
    {
        \Configuration::$values['EVERBLOCKLIGHT_USE_OBF'] = '1';
        self::assertSame('1', EverblocklightCache::getModuleConfiguration('EVERBLOCKLIGHT_USE_OBF'));

        \Configuration::$values['EVERBLOCKLIGHT_USE_OBF'] = '0';
        self::assertSame('1', EverblocklightCache::getModuleConfiguration('EVERBLOCKLIGHT_USE_OBF'), 'Valeur mise en cache');
    }

    public function testModuleConfigurationIsEmptyInAdmin(): void
    {
        \Configuration::$values['EVERBLOCKLIGHT_USE_OBF'] = '1';
        \Context::getContext()->controller = (object) ['controller_type' => 'admin'];

        self::assertSame('', EverblocklightCache::getModuleConfiguration('EVERBLOCKLIGHT_USE_OBF'));
    }
}
