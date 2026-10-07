<?php

/**
 * Stubs minimaux des classes PrestaShop utilisées par le code testé.
 * Ils ne reproduisent que le comportement nécessaire aux tests.
 */

declare(strict_types=1);

class Configuration
{
    /** @var array<string, mixed> */
    public static array $values = [];

    public static function get($key, $idLang = null, $idShopGroup = null, $idShop = null, $default = false)
    {
        return self::$values[$key] ?? $default;
    }

    public static function updateValue($key, $values, $html = false, $idShopGroup = null, $idShop = null): bool
    {
        self::$values[$key] = $values;

        return true;
    }

    public static function reset(): void
    {
        self::$values = [];
    }
}

class Cache
{
    /** @var array<string, mixed> */
    public static array $store = [];

    public static function isStored($key): bool
    {
        return array_key_exists($key, self::$store);
    }

    public static function store($key, $value): void
    {
        self::$store[$key] = $value;
    }

    public static function retrieve($key)
    {
        return self::$store[$key] ?? null;
    }

    public static function clean($key): void
    {
        if (str_ends_with($key, '*')) {
            $prefix = substr($key, 0, -1);
            foreach (array_keys(self::$store) as $storedKey) {
                if (str_starts_with($storedKey, $prefix)) {
                    unset(self::$store[$storedKey]);
                }
            }

            return;
        }

        unset(self::$store[$key]);
    }

    public static function reset(): void
    {
        self::$store = [];
    }
}

class Tools
{
    public static function str2url($value): string
    {
        $value = strtolower(trim((string) $value));
        $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);

        return trim($value, '-');
    }
}

class Context
{
    public $controller;
    public $language;
    public $shop;

    public function getTranslator(): TranslatorStub
    {
        return new TranslatorStub();
    }

    private static ?Context $instance = null;

    public static function getContext(): Context
    {
        if (self::$instance === null) {
            self::$instance = new Context();
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}

class Module
{
}

class Hook
{
    /** @var array<string, array<int, callable>> */
    public static array $listeners = [];

    /**
     * Les paramètres passés par référence (ex. 'docs' => &$docs) restent liés, comme dans le cœur.
     */
    public static function exec($hookName, $hookArgs = [], $idModule = null, $arrayReturn = false)
    {
        foreach (self::$listeners[$hookName] ?? [] as $listener) {
            $listener($hookArgs);
        }

        return '';
    }

    public static function reset(): void
    {
        self::$listeners = [];
    }
}

class TranslatorStub
{
    public function trans($id, array $parameters = [], $domain = null, $locale = null): string
    {
        return strtr((string) $id, $parameters);
    }
}
