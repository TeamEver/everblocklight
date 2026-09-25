<?php

/**
 * Bootstrap des tests unitaires : aucune boutique PrestaShop n'est chargée.
 * Les classes du cœur utilisées par le code testé sont remplacées par des stubs
 * minimaux (tests/stubs), réinitialisables entre deux tests.
 */

declare(strict_types=1);

define('EVERBLOCKLIGHT_MODULE_ROOT', dirname(__DIR__));

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '8.2.0');
}
if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}
if (!defined('_PS_MODULE_DIR_')) {
    define('_PS_MODULE_DIR_', dirname(EVERBLOCKLIGHT_MODULE_ROOT) . '/');
}

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/stubs/prestashop.php';
require EVERBLOCKLIGHT_MODULE_ROOT . '/vendor/autoload.php';
