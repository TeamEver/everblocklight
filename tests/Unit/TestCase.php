<?php

declare(strict_types=1);

namespace Everblocklight\Tests\Unit;

use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionMethod;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Configuration::reset();
        \Cache::reset();
        \Context::reset();
        \Hook::reset();
    }

    /**
     * Appelle une méthode statique protégée/privée (sans modifier sa visibilité dans le code).
     */
    protected static function callStatic(string $class, string $method, ...$args)
    {
        $reflection = new ReflectionMethod($class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(null, ...$args);
    }
}
