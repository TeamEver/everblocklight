<?php

/**
 * 2019-2025 Team Ever
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 *  @author    Team Ever <https://www.team-ever.com/>
 *  @copyright 2019-2025 Team Ever
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}

spl_autoload_register(static function ($className) {
    $prefix = 'Everblocklight\\Tools\\';
    if (strncmp($className, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($className, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/src/Service/EverblocklightCache.php';

if (!function_exists('everblocklightRegisterLegacyAlias')) {
    function everblocklightRegisterLegacyAlias(string $className, string $legacyAlias, string $relativePath): void
    {
        if (class_exists($legacyAlias, false)) {
            return;
        }

        if (!class_exists($className, false)) {
            $file = __DIR__ . '/' . ltrim($relativePath, '/\\');
            if (is_file($file)) {
                require_once $file;
            }
        }

        if (!class_exists($className, false)) {
            return;
        }

        class_alias($className, $legacyAlias, false);
    }
}

everblocklightRegisterLegacyAlias(\Everblocklight\Tools\Entity\Block::class, 'EverBlockLightClass', 'src/Entity/Block.php');
everblocklightRegisterLegacyAlias(\Everblocklight\Tools\Entity\Shortcode::class, 'EverblocklightShortcode', 'src/Entity/Shortcode.php');

use Everblocklight\Tools\Checkout\EverblocklightCheckoutStep;
use Everblocklight\Tools\Service\AdminConfigurationManager;
use Everblocklight\Tools\Service\EverblocklightCache;
use Everblocklight\Tools\Service\EverblocklightTools;
use Everblocklight\Tools\Service\ShortcodeDocumentationProvider;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Symfony\Component\Form\FormBuilderInterface;

class_exists(EverblocklightTools::class);

class Everblocklight extends Module
{
    private const ADMIN_MENU_ICON = 'view_quilt';
    public const CONFIG_PREFIX = 'EVERBLOCKLIGHT_';

    private $postErrors = [];
    private $postSuccess = [];
    private $allowedActions = [
        'refreshtokens',
        'fetchinstagramimages',
        'fetchwordpressposts',
    ];
    private $bypassedControllers = [
        'hookDisplayInvoiceLegalFreeText',
    ];

    public function __construct()
    {
        $this->name = 'everblocklight';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Team Ever';
        $this->need_instance = 0;
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Ever Block Light');
        $this->description = $this->l('Add HTML block everywhere !');
        $this->confirmUninstall = $this->l('Do yo really want to uninstall this module ?');
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => _PS_VERSION_,
        ];
    }

    /**
     * Intercepte dynamiquement les hooks display non déclarés explicitement.
     *
     * Cette méthode évite de devoir créer une méthode hook* pour chaque hook display,
     * tout en limitant l'exécution au front-office (hors contrôleurs bypassés).
     *
     * @param string $method Nom de la méthode appelée (ex: hookDisplayHome)
     * @param array<int, mixed> $args Arguments transmis par PrestaShop
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        if (php_sapi_name() == 'cli') {
            return;
        }
        $controllerTypes = [
            'front',
            'modulefront',
        ];
        $context = Context::getContext();
        if (!in_array($context->controller->controller_type, $controllerTypes) && !in_array($method, $this->bypassedControllers)) {
            return;
        }
        if (Hook::isDisplayHookName(lcfirst(str_replace('hook', '', $method)))) {
            return $this->everHook($method, $args);
        }
    }

    /**
     * Installe le module et son socle fonctionnel (config, SQL, hooks, onglets BO).
     */
    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        if (!$this->installConfiguration()) {
            return false;
        }

        if (!$this->installTranslations()) {
            return false;
        }

        if (!$this->installSql()) {
            return false;
        }

        if (!$this->installHooks()) {
            return false;
        }

        if (!$this->installExampleBlock()) {
            return false;
        }

        if (!$this->installTabs()) {
            return false;
        }

        return true;
    }

    /**
     * Initialise les clés de configuration nécessaires au fonctionnement du module.
     */
    private function installConfiguration(): bool
    {
        $configuration = [
            ['EVERBLOCKLIGHT_LOAD_FRONT_CSS', 1],
            ['EVERBLOCKLIGHT_TINYMCE', 1],
            ['EVERBLOCKLIGHT_CSS_P_LLOREM_NUMBER', 5],
            ['EVERBLOCKLIGHT_CSS_S_LLOREM_NUMBER', 5],
            ['EVERBLOCKLIGHT_WP_API_URL', ''],
            ['EVERBLOCKLIGHT_WP_BLOG_URL', '/blog'],
            ['EVERBLOCKLIGHT_WP_POST_NBR', 3],
            ['EVERBLOCKLIGHT_WP_POSTS_BG_IMAGE', ''],
            ['EVERBLOCKLIGHT_INSTA_SHOW_CAPTION', 0],
            ['EVERBLOCKLIGHT_CONTACT_MAX_UPLOAD_SIZE', 2097152],
            ['EVERBLOCKLIGHT_CONTACT_ALLOWED_EXTENSIONS', json_encode(['pdf', 'jpg', 'jpeg', 'png']), true],
            ['EVERBLOCKLIGHT_CONTACT_ALLOWED_MIME_TYPES', json_encode(['application/pdf', 'image/jpeg', 'image/png']), true],
            ['EVERBLOCKLIGHT_LOW_STOCK_THRESHOLD', 5],
            ['EVERBLOCKLIGHT_STORELOCATOR_TOGGLE', 0],
            ['EVERBLOCKLIGHT_GOOGLE_API_KEY', ''],
            ['EVERBLOCKLIGHT_GOOGLE_PLACE_ID', ''],
            ['EVERBLOCKLIGHT_GOOGLE_REVIEWS_LIMIT', 5],
            ['EVERBLOCKLIGHT_GOOGLE_REVIEWS_MIN_RATING', 0],
            ['EVERBLOCKLIGHT_GOOGLE_REVIEWS_SORT', 'most_relevant'],
            ['EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_RATING', 1],
            ['EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_AVATAR', 1],
            ['EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_CTA', 1],
            ['EVERBLOCKLIGHT_GOOGLE_REVIEWS_CTA_LABEL', $this->l('Read all reviews on Google')],
            ['EVERBLOCKLIGHT_GOOGLE_REVIEWS_CTA_URL', ''],
        ];

        foreach ($configuration as $item) {
            $autoload = $item[2] ?? false;
            if (!Configuration::updateValue($item[0], $item[1], $autoload)) {
                return false;
            }
        }

        return true;
    }

    private function installTranslations(): bool
    {
        return $this->refreshTranslations();
    }

    public function refreshTranslations(?int $idLang = null): bool
    {
        try {
            $this->importLegacyTranslations($idLang);
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog('Everblocklight translations import failed: ' . $exception->getMessage(), 2);
        }

        return true;
    }

    /**
     * Exécute le script SQL d'installation et crée les tables du module.
     */
    private function installSql(): bool
    {
        $sql = require dirname(__FILE__) . '/sql/install.php';
        if (!is_array($sql)) {
            return false;
        }

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Crée les hooks personnalisés du module puis enregistre les hooks natifs requis.
     */
    private function installHooks(): bool
    {
        foreach ($this->getCustomHooks() as $customHook) {
            if (!$this->createHookIfNotExists($customHook[0], $customHook[1], $customHook[2])) {
                return false;
            }
        }

        foreach ($this->getHooksToRegister() as $hookName) {
            if (!$this->isRegisteredInHook($hookName) && !$this->registerHook($hookName)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Hooks personnalisés créés par le module.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function getCustomHooks(): array
    {
        return [
            ['displayEverblocklightExtraOrderStep', 'Extra order step', 'This hook is triggered on extra order step'],
            ['actionGetEverBlockLightBefore', 'Before block is rendered', 'This hook triggers before block is rendered'],
            ['actionEverBlockLightChangeShortcodeBefore', 'Before block shortcodes are rendered', 'This hook triggers before every block shortcode is rendered'],
            ['actionEverBlockLightChangeShortcodeAfter', 'After block shortcodes are rendered', 'This hook triggers after every block shortcode is rendered'],
            ['displayBeforeRenderingShortcodes', 'Before rendering shortcodes', 'This hook triggers before shortcodes are rendered'],
            ['displayAfterRenderingShortcodes', 'After rendering shortcodes', 'This hook triggers after shortcodes are rendered'],
            ['displayFakeHook', 'Fake hook', 'Ne pas afficher ce hook en front, il sera utilisé pour du contenu asynchrone'],
            ['displayBeforeStoreLocator', 'display before Everblocklight store locator', 'This hook triggers before store locator is rendered'],
            ['displayAfterStoreLocator', 'display after Everblocklight store locator', 'This hook triggers after store locator is rendered'],
            ['displayAfterLocatorStore', 'display after store content on store locator', 'This hook triggers after store content on store locator'],
            ['displayBeforeProductMiniature', 'display before product miniature', 'This hook triggers before product miniature is rendered'],
            ['displayAfterProductMiniature', 'display after product miniature', 'This hook triggers after product miniature is rendered'],
        ];
    }

    /**
     * Hooks natifs ou personnalisés sur lesquels le module se greffe.
     *
     * @return array<int, string>
     */
    private function getHooksToRegister(): array
    {
        return [
            'displayHeader',
            'actionAdminControllerSetMedia',
            'actionObjectLanguageAddAfter',
            'actionOutputHTMLBefore',
            'actionEmailAddAfterContent',
            'actionCmsPageFormBuilderModifier',
            'actionObjectCmsUpdateAfter',
            'actionCheckoutRender',
            'displayOrderConfirmation',
            'displayAdminOrder',
            'displayPDFInvoice',
            'displayPDFDeliverySlip',
            'actionObjectEverBlockLightClassUpdateAfter',
            'actionObjectEverBlockLightClassDeleteAfter',
        ];
    }

    /**
     * Cree un bloc d'exemple desactive sur la home avec tous les shortcodes documentes.
     */
    private function installExampleBlock(): bool
    {
        $idHook = (int) Hook::getIdByName('displayHome');
        if ($idHook <= 0) {
            return false;
        }

        if (!$this->isRegisteredInHook('displayHome') && !$this->registerHook('displayHome')) {
            return false;
        }

        $content = $this->buildExampleBlockContent();
        if ($content === '') {
            return false;
        }

        $languages = Language::getLanguages(false);
        if (empty($languages) && isset($this->context->language->id)) {
            $languages = [
                ['id_lang' => (int) $this->context->language->id],
            ];
        }

        $shopIds = $this->getInstallShopIds();
        if (empty($shopIds)) {
            return false;
        }

        foreach ($shopIds as $idShop) {
            $existingBlockId = (int) Db::getInstance()->getValue(
                'SELECT `id_everblocklight`
                FROM `' . _DB_PREFIX_ . 'everblocklight`
                WHERE `id_shop` = ' . (int) $idShop . '
                  AND `name` = "' . pSQL('exemple') . '"'
            );
            if ($existingBlockId > 0) {
                continue;
            }

            $position = (int) Db::getInstance()->getValue(
                'SELECT COALESCE(MAX(`position`), 0) + 1
                FROM `' . _DB_PREFIX_ . 'everblocklight`
                WHERE `id_shop` = ' . (int) $idShop . '
                  AND `id_hook` = ' . (int) $idHook
            );

            if (!Db::getInstance()->insert('everblocklight', [
                'name' => 'exemple',
                'id_hook' => (int) $idHook,
                'only_home' => 1,
                'only_category' => 0,
                'only_category_product' => 0,
                'only_manufacturer' => 0,
                'only_supplier' => 0,
                'only_cms_category' => 0,
                'obfuscate_link' => 0,
                'add_container' => 1,
                'lazyload' => 0,
                'device' => 0,
                'id_shop' => (int) $idShop,
                'position' => $position,
                'categories' => json_encode([]),
                'manufacturers' => json_encode([]),
                'suppliers' => json_encode([]),
                'cms_categories' => json_encode([]),
                'groups' => json_encode([]),
                'background' => null,
                'css_class' => null,
                'data_attribute' => null,
                'bootstrap_class' => '0',
                'modal' => 0,
                'delay' => 0,
                'timeout' => 0,
                'date_start' => null,
                'date_end' => null,
                'active' => 0,
            ], true)) {
                return false;
            }

            $idBlock = (int) Db::getInstance()->Insert_ID();
            foreach ($languages as $language) {
                $idLang = (int) ($language['id_lang'] ?? $language['id'] ?? 0);
                if ($idLang <= 0) {
                    continue;
                }

                if (!Db::getInstance()->execute(
                    'INSERT INTO `' . _DB_PREFIX_ . 'everblocklight_lang`
                    (`id_everblocklight`, `id_lang`, `content`, `custom_code`)
                    VALUES (
                        ' . (int) $idBlock . ',
                        ' . (int) $idLang . ',
                        "' . pSQL($content, true) . '",
                        ""
                    )'
                )) {
                    Db::getInstance()->delete('everblocklight_lang', '`id_everblocklight` = ' . (int) $idBlock);
                    Db::getInstance()->delete('everblocklight', '`id_everblocklight` = ' . (int) $idBlock . ' AND `id_shop` = ' . (int) $idShop);

                    return false;
                }
            }
        }

        return true;
    }

    private function buildExampleBlockContent(): string
    {
        $shortcodes = [];
        foreach (ShortcodeDocumentationProvider::getDocumentation($this) as $group) {
            foreach ((array) ($group['entries'] ?? []) as $entry) {
                $code = trim((string) ($entry['code'] ?? ''));
                if ($code === '' || $code === '[storelocator]') {
                    continue;
                }

                $shortcodes[] = $code;
            }
        }

        $shortcodes = array_values(array_unique($shortcodes));
        if (empty($shortcodes)) {
            return '';
        }

        return '<h2>Exemple shortcodes Ever Block Light</h2>' . PHP_EOL
            . implode(PHP_EOL, array_map(static function (string $shortcode): string {
                return '<p>' . $shortcode . '</p>';
            }, $shortcodes));
    }

    /**
     * @return array<int, int>
     */
    private function getInstallShopIds(): array
    {
        $shopIds = [];
        try {
            $shopIds = class_exists('Shop') ? (array) Shop::getShops(false, null, true) : [];
        } catch (Throwable $exception) {
            $shopIds = [];
        }

        if (empty($shopIds)) {
            $shopIds[] = (int) ($this->context->shop->id ?? 0);
            $shopIds[] = (int) Configuration::get('PS_SHOP_DEFAULT');
        }

        return array_values(array_unique(array_filter(array_map('intval', $shopIds))));
    }

    private function installTabs(): bool
    {
        $tabs = [
            ['AdminEverBlockLightParent', 'IMPROVE', $this->l('Ever Block Light'), null],
            ['AdminEverBlockLightConfiguration', 'AdminEverBlockLightParent', $this->l('Configuration'), 'admin_everblocklight_configuration'],
            ['AdminEverBlockLight', 'AdminEverBlockLightParent', $this->l('HTML Blocks'), 'admin_everblocklight_blocks'],
            ['AdminEverBlockLightHook', 'AdminEverBlockLightParent', $this->l('Hooks'), 'admin_everblocklight_hooks'],
            ['AdminEverBlockLightShortcode', 'AdminEverBlockLightParent', $this->l('Shortcodes'), 'admin_everblocklight_shortcodes'],
            ['AdminEverBlockLightShortcodeDocumentation', 'AdminEverBlockLightParent', $this->l('Shortcode documentation'), 'admin_everblocklight_shortcodes_documentation'],
        ];

        foreach ($tabs as $tab) {
            if (!$this->installModuleTab($tab[0], $tab[1], $tab[2], $tab[3])) {
                return false;
            }
        }

        return true;
    }

    private function createHookIfNotExists(string $name, string $title, string $description): bool
    {
        if ((int) Hook::getIdByName($name) > 0) {
            return true;
        }

        $hook = new Hook();
        $hook->name = $name;
        $hook->title = $title;
        $hook->description = $description;

        return (bool) $hook->add();
    }

    public function uninstall()
    {
        // Uninstall SQL
        $sql = [];
        include dirname(__FILE__) . '/sql/uninstall.php';
        foreach ($this->getModuleConfigurationKeys() as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall()
            && $this->uninstallModuleTab('AdminEverBlockLightConfiguration')
            && $this->uninstallModuleTab('AdminEverBlockLight')
            && $this->uninstallModuleTab('AdminEverBlockLightHook')
            && $this->uninstallModuleTab('AdminEverBlockLightShortcode')
            && $this->uninstallModuleTab('AdminEverBlockLightShortcodeDocumentation')
            && $this->uninstallModuleTab('AdminEverBlockLightParent');
    }

    /**
     * Liste toutes les clés de configuration du module (y compris les clés dynamiques par magasin).
     *
     * @return array<int, string>
     */
    private function getModuleConfigurationKeys(): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT `name` FROM `' . _DB_PREFIX_ . 'configuration`
            WHERE `name` LIKE "' . pSQL(str_replace('_', '\\_', self::CONFIG_PREFIX)) . '%"'
        );

        return is_array($rows) ? array_column($rows, 'name') : [];
    }

    public function l($string, $specific = null, $idLang = null)
    {
        return Context::getContext()->getTranslator()->trans(
            $string,
            [],
            $this->getTranslationDomain($specific)
        );
    }

    private function getTranslationDomain($specific = null)
    {
        $domainKey = $specific ? $specific : $this->name;

        return sprintf('Modules.%s.%s', Tools::ucfirst($this->name), $this->normalizeDomainKey($domainKey));
    }

    private function normalizeDomainKey($key)
    {
        $key = trim((string) $key);

        if ($key === '') {
            $key = $this->name;
        }

        $key = str_replace(['-', '.'], '_', $key);
        $key = preg_replace('/[^A-Za-z0-9_]/', '', $key);
        $key = Tools::strtolower($key);

        return Tools::ucfirst($key);
    }

    public function hookActionObjectLanguageAddAfter($params): void
    {
        $language = $params['object'] ?? null;
        if ($language instanceof Language && Validate::isLoadedObject($language)) {
            $this->refreshTranslations((int) $language->id);

            return;
        }

        $this->refreshTranslations();
    }

    private function importLegacyTranslations(?int $idLang = null)
    {
        $legacyDir = dirname(__FILE__) . '/translations';

        if (!is_dir($legacyDir)) {
            return;
        }

        $defaultMap = $this->loadDefaultLegacyTranslations($legacyDir);

        if (empty($defaultMap)) {
            return;
        }

        foreach ($this->getLanguagesForTranslationImport($idLang) as $language) {
            $legacyFile = $this->resolveLegacyFileForIso($legacyDir, $language['iso_code']);

            if (!$legacyFile) {
                continue;
            }

            $legacyTranslations = $this->loadLegacyTranslationsFromFile($legacyFile);

            if (empty($legacyTranslations)) {
                continue;
            }

            foreach ($legacyTranslations as $legacyKey => $translatedValue) {
                if (!isset($defaultMap[$legacyKey])) {
                    continue;
                }

                $domain = $this->buildDomainFromLegacyKey($legacyKey);

                if (!$domain) {
                    continue;
                }

                $source = $defaultMap[$legacyKey];

                if ($source === $translatedValue || $source === '') {
                    continue;
                }

                $this->upsertTranslation(
                    (int) $language['id_lang'],
                    $domain,
                    $source,
                    $translatedValue
                );
            }
        }
    }

    private function getLanguagesForTranslationImport(?int $idLang = null): array
    {
        if ($idLang === null || $idLang <= 0) {
            return Language::getLanguages(false);
        }

        $language = new Language($idLang);
        if (!Validate::isLoadedObject($language)) {
            return [];
        }

        return [[
            'id_lang' => (int) $language->id,
            'iso_code' => (string) $language->iso_code,
        ]];
    }

    private function loadDefaultLegacyTranslations($legacyDir)
    {
        $candidates = ['en.php', 'gb.php', 'us.php', 'modern_gb.php', 'modern_en.php'];

        foreach ($candidates as $candidate) {
            $path = $legacyDir . '/' . $candidate;

            if (is_file($path)) {
                return $this->loadLegacyTranslationsFromFile($path);
            }
        }

        foreach (glob($legacyDir . '/*.php') as $file) {
            if (basename($file) === 'index.php') {
                continue;
            }

            return $this->loadLegacyTranslationsFromFile($file);
        }

        return [];
    }

    private function resolveLegacyFileForIso($legacyDir, $isoCode)
    {
        $iso = Tools::strtolower($isoCode);
        $candidates = [$iso . '.php', 'modern_' . $iso . '.php'];

        if ($iso === 'en') {
            $candidates[] = 'gb.php';
            $candidates[] = 'us.php';
            $candidates[] = 'modern_gb.php';
            $candidates[] = 'modern_us.php';
        }

        foreach ($candidates as $candidate) {
            $path = $legacyDir . '/' . $candidate;

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function loadLegacyTranslationsFromFile($path)
    {
        if (!is_file($path)) {
            return [];
        }

        $backup = isset($GLOBALS['_MODULE']) ? $GLOBALS['_MODULE'] : null;
        $GLOBALS['_MODULE'] = [];
        $_MODULE = &$GLOBALS['_MODULE'];

        include $path;

        $translations = isset($GLOBALS['_MODULE']) && is_array($GLOBALS['_MODULE'])
            ? $GLOBALS['_MODULE']
            : [];

        if ($backup !== null) {
            $GLOBALS['_MODULE'] = $backup;
        } else {
            unset($GLOBALS['_MODULE']);
        }

        return $translations;
    }

    private function buildDomainFromLegacyKey($legacyKey)
    {
        if (strpos($legacyKey, '>') === false) {
            return null;
        }

        $parts = explode('>', $legacyKey, 2);

        if (!isset($parts[1])) {
            return null;
        }

        $domainPart = $parts[1];
        $segments = explode('_', $domainPart);

        if (empty($segments)) {
            return null;
        }

        $domainKey = $segments[0];

        return sprintf('Modules.%s.%s', Tools::ucfirst($this->name), $this->normalizeDomainKey($domainKey));
    }

    private function upsertTranslation($idLang, $domain, $source, $translation)
    {
        $db = Db::getInstance();
        $where = '`id_lang` = ' . (int) $idLang
            . " AND `domain` = '" . pSQL($domain) . "'"
            . " AND `key` = '" . pSQL($source, true) . "'"
            . " AND (`theme` IS NULL OR `theme` = '')";
        $idTranslation = (int) $db->getValue(
            'SELECT `id_translation` FROM `' . _DB_PREFIX_ . 'translation` WHERE ' . $where
        );

        if ($idTranslation > 0) {
            $db->update(
                'translation',
                ['translation' => pSQL($translation, true)],
                '`id_translation` = ' . (int) $idTranslation
            );

            return;
        }

        $db->insert('translation', [
            'id_lang' => (int) $idLang,
            'domain' => pSQL($domain),
            'key' => pSQL($source, true),
            'translation' => pSQL($translation, true),
            'theme' => '',
        ], false, true, Db::INSERT);
    }

    private function installModuleTab(string $className, string $parentClassName, string $name, ?string $routeName = null): bool
    {
        $existingId = (int) Tab::getIdFromClassName($className);
        if ($existingId > 0) {
            $existingTab = new Tab($existingId);
            $shouldSave = false;

            if ($routeName) {
                if (property_exists($existingTab, 'route_name')) {
                    $existingTab->route_name = $routeName;
                    $shouldSave = true;
                }
            }

            if ($className === 'AdminEverBlockLightParent'
                && property_exists($existingTab, 'icon')
                && $existingTab->icon !== self::ADMIN_MENU_ICON
            ) {
                $existingTab->icon = self::ADMIN_MENU_ICON;
                $shouldSave = true;
            }

            return $shouldSave ? (bool) $existingTab->save() : true;
        }

        $parentId = (int) Tab::getIdFromClassName($parentClassName);
        if ($parentId <= 0) {
            return false;
        }

        $tab = new Tab();
        $tab->active = true;
        $tab->class_name = $className;
        $tab->id_parent = $parentId;
        $tab->position = Tab::getNewLastPosition($tab->id_parent);
        $tab->module = $this->name;
        if ($routeName && property_exists($tab, 'route_name')) {
            $tab->route_name = $routeName;
        }

        if ($className === 'AdminEverBlockLightParent' && property_exists($tab, 'icon')) {
            $tab->icon = self::ADMIN_MENU_ICON;
        }

        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = $name;
        }

        return (bool) $tab->add();
    }

    protected function uninstallModuleTab($tabClass)
    {
        $tabId = (int) Tab::getIdFromClassName($tabClass);
        if (!$tabId) {
            return true;
        }

        $tab = new Tab($tabId);
        return $tab->delete();
    }

    public function checkHooks()
    {
        $this->installHooks();
        $this->registerStoredBlockHooks();
        $this->installTabs();
    }

    protected function registerStoredBlockHooks(): void
    {
        try {
            $blocksHooks = Db::getInstance()->executeS(
                'SELECT DISTINCT h.`name`
                FROM `' . _DB_PREFIX_ . 'everblocklight` b
                INNER JOIN `' . _DB_PREFIX_ . 'hook` h ON h.`id_hook` = b.`id_hook`
                WHERE b.`id_hook` > 0
                  AND h.`name` NOT LIKE "action%"
                  AND h.`name` NOT LIKE "filter%"'
            );
        } catch (Exception $exception) {
            PrestaShopLogger::addLog($this->name . ' | ' . $exception->getMessage());

            return;
        }

        if (!is_array($blocksHooks)) {
            return;
        }

        foreach ($blocksHooks as $hook) {
            $hookName = (string) ($hook['name'] ?? '');
            if (!$hookName || !Validate::isHookName($hookName) || $this->isRegisteredInHook($hookName)) {
                continue;
            }

            $this->registerHook($hookName);
        }
    }

    public function getContent()
    {
        $this->secureModuleFolder();
        EverblocklightTools::checkAndFixDatabase();
        $this->checkHooks();

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminEverBlockLightConfiguration'));

        return '';
    }

    public function getAdminConfigurationFormData(): array
    {
        return $this->getAdminConfigurationManager()->getFormData($this);
    }

    public function getAdminConfigurationViewContext(): array
    {
        return $this->getAdminConfigurationManager()->getViewContext($this);
    }

    public function processAdminConfigurationRequest(): array
    {
        return $this->getAdminConfigurationManager()->processRequest($this);
    }

    public function getAdminConfigurationLegacyFormValues(): array
    {
        return $this->getConfigFormValues();
    }

    public function getAdminConfigurationModuleStatistics(): array
    {
        return $this->getModuleStatistics();
    }

    public function getAdminConfigurationAllowedActions(): array
    {
        return $this->allowedActions;
    }

    public function getAdminConfigurationCronToken(): string
    {
        return $this->encrypt($this->name . '/evercron');
    }

    public function prepareAdminConfigurationEnvironment(): void
    {
        $this->secureModuleFolder();
        EverblocklightTools::checkAndFixDatabase();
        $this->checkHooks();
    }

    public function resetAdminConfigurationMessages(): void
    {
        $this->postErrors = [];
        $this->postSuccess = [];
    }

    public function getAdminConfigurationMessages(): array
    {
        return [
            'errors' => $this->postErrors,
            'success' => $this->postSuccess,
        ];
    }

    public function runAdminConfigurationPostValidation(): void
    {
        $this->postValidation();
    }

    public function runAdminConfigurationPostProcess(): void
    {
        $this->postProcess();
    }

    public function runAdminConfigurationCacheCleanup(): void
    {
        $this->emptyAllCache();
    }

    private function getAdminConfigurationManager(): AdminConfigurationManager
    {
        try {
            $container = SymfonyContainer::getInstance();
            if ($container && $container->has(AdminConfigurationManager::class)) {
                return $container->get(AdminConfigurationManager::class);
            }
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog($this->name . ' | ' . $exception->getMessage());
        }

        return new AdminConfigurationManager();
    }

    protected function getConfigFormValues()
    {
        $idShop = (int) Context::getContext()->shop->id;
        $custom_css = Tools::file_get_contents(
            _PS_MODULE_DIR_ . '/' . $this->name . '/views/css/custom' . $idShop . '.css'
        );
        $custom_js = Tools::file_get_contents(
            _PS_MODULE_DIR_ . '/' . $this->name . '/views/js/custom' . $idShop . '.js'
        );
        $filePath = _PS_MODULE_DIR_ . $this->name . '/views/js/header-scripts-' . $this->context->shop->id . '.js';
        if (file_exists($filePath) && filesize($filePath) > 0) {
            $headerScripts = file_get_contents($filePath);
        } else {
            $headerScripts = '';
        }
        $configData = [
            'EVERBLOCKLIGHT_OPTIONS_POSITION' => Configuration::get('EVERBLOCKLIGHT_OPTIONS_POSITION'),
            'EVERBLOCKLIGHT_OPTIONS_TITLE' => $this->getConfigInMultipleLangs('EVERBLOCKLIGHT_OPTIONS_TITLE'),
            'EVERBLOCKLIGHT_INSTA_ACCESS_TOKEN' => Configuration::get('EVERBLOCKLIGHT_INSTA_ACCESS_TOKEN'),
            'EVERBLOCKLIGHT_INSTA_LINK' => Configuration::get('EVERBLOCKLIGHT_INSTA_LINK'),
            'EVERBLOCKLIGHT_INSTA_SHOW_CAPTION' => Configuration::get('EVERBLOCKLIGHT_INSTA_SHOW_CAPTION'),
            'EVERBLOCKLIGHT_WP_API_URL' => Configuration::get('EVERBLOCKLIGHT_WP_API_URL'),
            'EVERBLOCKLIGHT_WP_BLOG_URL' => Configuration::get('EVERBLOCKLIGHT_WP_BLOG_URL'),
            'EVERBLOCKLIGHT_WP_POST_NBR' => Configuration::get('EVERBLOCKLIGHT_WP_POST_NBR'),
            'EVERBLOCKLIGHT_WP_POSTS_BG_IMAGE' => Configuration::get('EVERBLOCKLIGHT_WP_POSTS_BG_IMAGE'),
            'EVERBLOCKLIGHT_GOOGLE_API_KEY' => Configuration::get('EVERBLOCKLIGHT_GOOGLE_API_KEY'),
            'EVERBLOCKLIGHT_GOOGLE_PLACE_ID' => Configuration::get('EVERBLOCKLIGHT_GOOGLE_PLACE_ID'),
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_LIMIT' => Configuration::get('EVERBLOCKLIGHT_GOOGLE_REVIEWS_LIMIT'),
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_MIN_RATING' => Configuration::get('EVERBLOCKLIGHT_GOOGLE_REVIEWS_MIN_RATING'),
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SORT' => Configuration::get('EVERBLOCKLIGHT_GOOGLE_REVIEWS_SORT'),
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_RATING' => Configuration::get('EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_RATING'),
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_AVATAR' => Configuration::get('EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_AVATAR'),
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_CTA' => Configuration::get('EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_CTA'),
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_CTA_LABEL' => Configuration::get('EVERBLOCKLIGHT_GOOGLE_REVIEWS_CTA_LABEL'),
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_CTA_URL' => Configuration::get('EVERBLOCKLIGHT_GOOGLE_REVIEWS_CTA_URL'),
            'EVERBLOCKLIGHT_GMAP_KEY' => Configuration::get('EVERBLOCKLIGHT_GMAP_KEY'),
            'EVERBLOCKLIGHT_MARKER_ICON' => Configuration::get('EVERBLOCKLIGHT_MARKER_ICON'),
            'EVERBLOCKLIGHT_STORELOCATOR_TOGGLE' => Configuration::get('EVERBLOCKLIGHT_STORELOCATOR_TOGGLE'),
            'EVERBLOCKLIGHT_USE_OBF' => Configuration::get('EVERBLOCKLIGHT_USE_OBF'),
            'EVERBLOCKLIGHT_CSS' => $custom_css,
            'EVERBLOCKLIGHT_JS' => $custom_js,
            'EVERBLOCKLIGHT_CSS_LINKS' => Configuration::get('EVERBLOCKLIGHT_CSS_LINKS'),
            'EVERBLOCKLIGHT_JS_LINKS' => Configuration::get('EVERBLOCKLIGHT_JS_LINKS'),
            'EVERBLOCKLIGHT_HEADER_SCRIPTS' => $headerScripts,
            'EVERBLOCKLIGHT_CSS_P_LLOREM_NUMBER' => Configuration::get('EVERBLOCKLIGHT_CSS_P_LLOREM_NUMBER'),
            'EVERBLOCKLIGHT_CSS_S_LLOREM_NUMBER' => Configuration::get('EVERBLOCKLIGHT_CSS_S_LLOREM_NUMBER'),
            'EVERBLOCKLIGHT_TINYMCE' => Configuration::get('EVERBLOCKLIGHT_TINYMCE'),
        ];
        $stores = Store::getStores((int) $this->context->language->id);
        $holidays = EverblocklightTools::getFrenchHolidays((int) date('Y'));
        foreach ($stores as $store) {
            foreach ($holidays as $date) {
                $hoursKey = 'EVERBLOCKLIGHT_HOLIDAY_HOURS_' . (int) $store['id_store'] . '_' . $date;
                $configData[$hoursKey] = Configuration::get($hoursKey);
            }
        }
        return $configData;
    }

    protected function getModuleStatistics(): array
    {
        $idShop = (int) $this->context->shop->id;
        $stats = [
            'blocks_total' => $this->countTableRecords('everblocklight', 'id_shop = ' . $idShop),
            'blocks_active' => $this->countTableRecords('everblocklight', 'id_shop = ' . $idShop . ' AND active = 1'),
            'shortcodes' => $this->countTableRecords('everblocklight_shortcode', 'id_shop = ' . $idShop),
        ];

        return $stats;
    }

    public function getAdminModuleStatistics(): array
    {
        return $this->getModuleStatistics();
    }

    protected function countTableRecords(string $table, string $whereClause = ''): int
    {
        if (!$this->moduleTableExists($table)) {
            return 0;
        }

        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);
        $sql = sprintf(
            'SELECT COUNT(*) FROM `%s`',
            bqSQL(_DB_PREFIX_ . $table)
        );
        if ($whereClause !== '') {
            $sql .= ' WHERE ' . $whereClause;
        }

        return (int) $db->getValue($sql);
    }

    protected function moduleTableExists(string $table): bool
    {
        $tableName = _DB_PREFIX_ . $table;
        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);
        $pattern = str_replace(['_', '%'], ['\\_', '\\%'], pSQL($tableName));
        $sql = sprintf("SHOW TABLES LIKE '%s'", $pattern);
        $result = $db->executeS($sql);

        return !empty($result);
    }

    public function postValidation()
    {
        if (Tools::isSubmit('submit' . $this->name . 'Module')) {
            if (Tools::getValue('EVERBLOCKLIGHT_CSS_P_LLOREM_NUMBER')
                && !Validate::isInt(Tools::getValue('EVERBLOCKLIGHT_CSS_P_LLOREM_NUMBER'))
            ) {
                $this->postErrors[] = $this->l(
                    'Error : The field "Llorem paragraph number" is not valid'
                );
            }
            if (Tools::getValue('EVERBLOCKLIGHT_CSS_S_LLOREM_NUMBER')
                && !Validate::isInt(Tools::getValue('EVERBLOCKLIGHT_CSS_S_LLOREM_NUMBER'))
            ) {
                $this->postErrors[] = $this->l(
                    'Error : The field "Llorem sentences per paragraphs number" is not valid'
                );
            }
            if (Tools::getValue('EVERBLOCKLIGHT_TINYMCE')
                && !Validate::isBool(Tools::getValue('EVERBLOCKLIGHT_TINYMCE'))
            ) {
                $this->postErrors[] = $this->l(
                    'Error : The field "Extends TinyMCE" is not valid'
                );
            }
            if (Tools::getValue('EVERBLOCKLIGHT_WP_POST_NBR')
                && !Validate::isUnsignedInt(Tools::getValue('EVERBLOCKLIGHT_WP_POST_NBR'))
            ) {
                $this->postErrors[] = $this->l(
                    'Error : The field "Number of blog posts" is not valid'
                );
            }
            $blogUrl = Tools::getValue('EVERBLOCKLIGHT_WP_BLOG_URL');
            if (!empty($blogUrl)
                && !Validate::isUrl($blogUrl)
                && (strpos($blogUrl, '/') !== 0)
            ) {
                $this->postErrors[] = $this->l(
                    'Error : The field "Blog URL" must be a valid URL or start with /'
                );
            }
            if (Tools::getValue('EVERBLOCKLIGHT_GOOGLE_REVIEWS_LIMIT')
                && (!Validate::isUnsignedInt(Tools::getValue('EVERBLOCKLIGHT_GOOGLE_REVIEWS_LIMIT'))
                || (int) Tools::getValue('EVERBLOCKLIGHT_GOOGLE_REVIEWS_LIMIT') < 1)
            ) {
                $this->postErrors[] = $this->l('Error: the field "Maximum number of reviews" is not valid');
            }
            $minRatingValue = Tools::getValue('EVERBLOCKLIGHT_GOOGLE_REVIEWS_MIN_RATING');
            if ($minRatingValue !== '' && $minRatingValue !== null) {
                if (!is_numeric($minRatingValue)) {
                    $this->postErrors[] = $this->l('Error: the field "Minimum rating to display" must be a number');
                } elseif ((float) $minRatingValue < 0 || (float) $minRatingValue > 5) {
                    $this->postErrors[] = $this->l('Error: the field "Minimum rating to display" must be between 0 and 5');
                }
            }
            $sortValue = Tools::getValue('EVERBLOCKLIGHT_GOOGLE_REVIEWS_SORT');
            if ($sortValue && !in_array($sortValue, ['most_relevant', 'newest'], true)) {
                $this->postErrors[] = $this->l('Error: the field "Reviews sort order" is not valid');
            }
            $boolFields = [
                'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_RATING',
                'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_AVATAR',
                'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_CTA',
            ];
            foreach ($boolFields as $boolField) {
                $value = Tools::getValue($boolField);
                if ($value !== '' && $value !== null && !Validate::isBool($value)) {
                    $this->postErrors[] = $this->l('Error: one of the Google reviews display options is not valid');
                    break;
                }
            }
            if (Tools::getValue('EVERBLOCKLIGHT_GOOGLE_REVIEWS_CTA_URL')
                && !Validate::isUrl(Tools::getValue('EVERBLOCKLIGHT_GOOGLE_REVIEWS_CTA_URL'))
            ) {
                $this->postErrors[] = $this->l('Error: the field "CTA link override" must be a valid URL');
            }
        }
    }

    protected function postProcess()
    {
        $idShop = (int) Context::getContext()->shop->id;
        $custom_css = _PS_MODULE_DIR_ . $this->name . '/views/css/custom' . $idShop . '.css';
        $custom_js = _PS_MODULE_DIR_ . $this->name . '/views/js/custom' . $idShop . '.js';
        // Compressed
        $compressedCss = _PS_MODULE_DIR_ . $this->name . '/views/css/custom-compressed' . $idShop . '.css';
        $cssCode = Tools::getValue('EVERBLOCKLIGHT_CSS');
        $jsCode = Tools::getValue('EVERBLOCKLIGHT_JS');
        // Compress CSS code
        $compressedCssCode = $this->compressCSSCode(
            $cssCode
        );
        // Create CSS file if need
        if (!is_file($custom_css)) {
            $handle_css = fopen(
                $custom_css,
                'w+'
            );
            fclose($handle_css);
        }
        if (!is_file($compressedCss)) {
            $handle_css = fopen(
                $compressedCss,
                'w+'
            );
            fclose($handle_css);
        }
        // Create JS file if need
        if (!is_file($custom_js)) {
            $handle_js = fopen(
                $custom_js,
                'w+'
            );
            fclose($handle_js);
        }
        Configuration::updateValue(
            'EVERBLOCKLIGHT_LOAD_FRONT_CSS',
            Tools::getValue('EVERBLOCKLIGHT_LOAD_FRONT_CSS')
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_USE_OBF',
            Tools::getValue('EVERBLOCKLIGHT_USE_OBF')
        );
        file_put_contents(
            $custom_css,
            $cssCode
        );
        file_put_contents(
            $custom_js,
            $jsCode
        );
        file_put_contents(
            $compressedCss,
            $compressedCssCode
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_OPTIONS_POSITION',
            Tools::getValue('EVERBLOCKLIGHT_OPTIONS_POSITION')
        );
        $formTitle = [];
        foreach (Language::getLanguages(false) as $lang) {
            $formTitle[$lang['id_lang']] = (
                Tools::getValue('EVERBLOCKLIGHT_OPTIONS_TITLE_' . $lang['id_lang'])
            ) ? Tools::getValue(
                'EVERBLOCKLIGHT_OPTIONS_TITLE_' . $lang['id_lang']
            ) : '';
        }
        $headerScripts = Tools::getValue('EVERBLOCKLIGHT_HEADER_SCRIPTS');
        $filePath = _PS_MODULE_DIR_ . $this->name . '/views/js/header-scripts-' . $this->context->shop->id . '.js';
        file_put_contents($filePath, $headerScripts);
        Configuration::updateValue(
            'EVERBLOCKLIGHT_OPTIONS_TITLE',
            $formTitle,
            true
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_INSTA_ACCESS_TOKEN',
            Tools::getValue('EVERBLOCKLIGHT_INSTA_ACCESS_TOKEN')
        );
        // Auto refresh Instagram token
        if (Tools::getValue('EVERBLOCKLIGHT_INSTA_ACCESS_TOKEN')) {
            EverblocklightTools::refreshInstagramToken();
        }
        Configuration::updateValue(
            'EVERBLOCKLIGHT_INSTA_LINK',
            Tools::getValue('EVERBLOCKLIGHT_INSTA_LINK')
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_INSTA_SHOW_CAPTION',
            Tools::getValue('EVERBLOCKLIGHT_INSTA_SHOW_CAPTION')
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_WP_API_URL',
            Tools::getValue('EVERBLOCKLIGHT_WP_API_URL')
        );
        $blogUrl = trim((string) Tools::getValue('EVERBLOCKLIGHT_WP_BLOG_URL'));
        if ($blogUrl === '') {
            $blogUrl = '/blog';
        }
        Configuration::updateValue(
            'EVERBLOCKLIGHT_WP_BLOG_URL',
            $blogUrl
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_WP_POST_NBR',
            Tools::getValue('EVERBLOCKLIGHT_WP_POST_NBR')
        );
        $googleReviewsLimit = (int) Tools::getValue('EVERBLOCKLIGHT_GOOGLE_REVIEWS_LIMIT');
        if ($googleReviewsLimit <= 0) {
            $googleReviewsLimit = 5;
        }
        $googleReviewsMinRating = Tools::getValue('EVERBLOCKLIGHT_GOOGLE_REVIEWS_MIN_RATING');
        if ($googleReviewsMinRating === '' || $googleReviewsMinRating === null) {
            $googleReviewsMinRating = 0;
        }
        $googleReviewsMinRating = (float) $googleReviewsMinRating;
        if ($googleReviewsMinRating < 0) {
            $googleReviewsMinRating = 0;
        }
        if ($googleReviewsMinRating > 5) {
            $googleReviewsMinRating = 5;
        }
        $googleReviewsSort = Tools::getValue('EVERBLOCKLIGHT_GOOGLE_REVIEWS_SORT');
        if (!in_array($googleReviewsSort, ['newest', 'most_relevant'], true)) {
            $googleReviewsSort = 'most_relevant';
        }
        $googleReviewsShowRating = Tools::getValue('EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_RATING');
        $googleReviewsShowRating = in_array((string) $googleReviewsShowRating, ['1', 'true', 'on'], true) ? 1 : 0;
        $googleReviewsShowAvatar = Tools::getValue('EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_AVATAR');
        $googleReviewsShowAvatar = in_array((string) $googleReviewsShowAvatar, ['1', 'true', 'on'], true) ? 1 : 0;
        $googleReviewsShowCta = Tools::getValue('EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_CTA');
        $googleReviewsShowCta = in_array((string) $googleReviewsShowCta, ['1', 'true', 'on'], true) ? 1 : 0;
        $googleReviewsCtaLabel = trim((string) Tools::getValue('EVERBLOCKLIGHT_GOOGLE_REVIEWS_CTA_LABEL'));
        $googleReviewsCtaUrl = trim((string) Tools::getValue('EVERBLOCKLIGHT_GOOGLE_REVIEWS_CTA_URL'));
        Configuration::updateValue(
            'EVERBLOCKLIGHT_GOOGLE_API_KEY',
            Tools::getValue('EVERBLOCKLIGHT_GOOGLE_API_KEY')
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_GOOGLE_PLACE_ID',
            Tools::getValue('EVERBLOCKLIGHT_GOOGLE_PLACE_ID')
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_LIMIT',
            $googleReviewsLimit
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_MIN_RATING',
            $googleReviewsMinRating
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SORT',
            $googleReviewsSort
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_RATING',
            $googleReviewsShowRating
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_AVATAR',
            $googleReviewsShowAvatar
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_SHOW_CTA',
            $googleReviewsShowCta
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_CTA_LABEL',
            $googleReviewsCtaLabel
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_GOOGLE_REVIEWS_CTA_URL',
            $googleReviewsCtaUrl
        );
        EverblocklightCache::cacheDropByPattern('everblocklight_google_reviews_');
        Configuration::updateValue(
            'EVERBLOCKLIGHT_GMAP_KEY',
            Tools::getValue('EVERBLOCKLIGHT_GMAP_KEY')
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_STORELOCATOR_TOGGLE',
            Tools::getValue('EVERBLOCKLIGHT_STORELOCATOR_TOGGLE')
        );
        if (isset($_FILES['EVERBLOCKLIGHT_MARKER_ICON'])
            && isset($_FILES['EVERBLOCKLIGHT_MARKER_ICON']['tmp_name'])
            && !empty($_FILES['EVERBLOCKLIGHT_MARKER_ICON']['tmp_name'])
        ) {
            $filename = $_FILES['EVERBLOCKLIGHT_MARKER_ICON']['name'];
            $extension = Tools::strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($extension !== 'svg') {
                $this->postErrors[] = $this->l('Marker icon must be an SVG file.');
            } elseif (!($tmpName = tempnam(_PS_TMP_IMG_DIR_, 'PS'))
                || !move_uploaded_file($_FILES['EVERBLOCKLIGHT_MARKER_ICON']['tmp_name'], $tmpName)
            ) {
                $this->postErrors[] = $this->l('Error while uploading marker icon.');
            } else {
                $dest = _PS_MODULE_DIR_ . $this->name . '/views/img/store-locator-marker.svg';
                copy($tmpName, $dest);
                @unlink($tmpName);
                Configuration::updateValue('EVERBLOCKLIGHT_MARKER_ICON', 'store-locator-marker.svg');
            }
        }
        if (isset($_FILES['EVERBLOCKLIGHT_WP_POSTS_BG_IMAGE'])
            && isset($_FILES['EVERBLOCKLIGHT_WP_POSTS_BG_IMAGE']['tmp_name'])
            && !empty($_FILES['EVERBLOCKLIGHT_WP_POSTS_BG_IMAGE']['tmp_name'])
        ) {
            $filename = $_FILES['EVERBLOCKLIGHT_WP_POSTS_BG_IMAGE']['name'];
            $extension = Tools::strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($extension, $allowedExtensions, true)) {
                $this->postErrors[] = $this->l('WordPress background image must be a JPG, PNG, WEBP, or GIF file.');
            } elseif (!($tmpName = tempnam(_PS_TMP_IMG_DIR_, 'PS'))
                || !move_uploaded_file($_FILES['EVERBLOCKLIGHT_WP_POSTS_BG_IMAGE']['tmp_name'], $tmpName)
            ) {
                $this->postErrors[] = $this->l('Error while uploading WordPress background image.');
            } else {
                $previous = Configuration::get('EVERBLOCKLIGHT_WP_POSTS_BG_IMAGE');
                if ($previous) {
                    $previousPath = _PS_MODULE_DIR_ . $this->name . '/views/img/' . $previous;
                    if (file_exists($previousPath)) {
                        @unlink($previousPath);
                    }
                }
                $safeName = 'wp-posts-bg-' . time() . '.' . $extension;
                $dest = _PS_MODULE_DIR_ . $this->name . '/views/img/' . $safeName;
                copy($tmpName, $dest);
                @unlink($tmpName);
                $webpUrl = EverblocklightTools::convertToWebP($dest);
                if ($webpUrl) {
                    $webpPath = parse_url($webpUrl, PHP_URL_PATH);
                    $safeName = $webpPath ? basename($webpPath) : basename($webpUrl);
                }
                Configuration::updateValue('EVERBLOCKLIGHT_WP_POSTS_BG_IMAGE', $safeName);
            }
        }
        $stores = Store::getStores((int) $this->context->language->id);
        $holidays = EverblocklightTools::getFrenchHolidays((int) date('Y'));
        foreach ($stores as $store) {
            foreach ($holidays as $date) {
                $hoursKey = 'EVERBLOCKLIGHT_HOLIDAY_HOURS_' . (int) $store['id_store'] . '_' . $date;
                Configuration::updateValue($hoursKey, Tools::getValue($hoursKey));
            }
        }
        Configuration::updateValue(
            'EVERBLOCKLIGHT_CSS_LINKS',
            Tools::getValue('EVERBLOCKLIGHT_CSS_LINKS')
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_JS_LINKS',
            Tools::getValue('EVERBLOCKLIGHT_JS_LINKS')
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_CSS_P_LLOREM_NUMBER',
            Tools::getValue('EVERBLOCKLIGHT_CSS_P_LLOREM_NUMBER')
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_CSS_S_LLOREM_NUMBER',
            Tools::getValue('EVERBLOCKLIGHT_CSS_S_LLOREM_NUMBER')
        );
        Configuration::updateValue(
            'EVERBLOCKLIGHT_TINYMCE',
            Tools::getValue('EVERBLOCKLIGHT_TINYMCE')
        );
        $stores = EverblocklightTools::getStoreLocatorData();
        $filename = 'store-locator-' . $idShop . '.js';
        $filePath = _PS_MODULE_DIR_ . $this->name . '/views/js/' . $filename;
        if (!empty($stores) && Tools::getValue('EVERBLOCKLIGHT_GMAP_KEY')) {
            $markers = [];
            $context = Context::getContext();
            $markerIcon = Configuration::get('EVERBLOCKLIGHT_MARKER_ICON');
            foreach ($stores as $store) {
                $storeId = isset($store['id']) ? (int) $store['id'] : (int) $store['id_store'];
                if (!empty($store['is_open'])) {
                    $status = sprintf($this->l('Open today until %s'), $store['open_until']);
                } elseif (!empty($store['opens_at'])) {
                    $status = sprintf($this->l('Open today at %s'), $store['opens_at']);
                } else {
                    $status = $this->l('Closed');
                }
                $marker = [
                    'id' => $storeId,
                    'lat' => $store['latitude'],
                    'lng' => $store['longitude'],
                    'title' => $store['name'],
                    'address1' => $store['address1'],
                    'address2' => $store['address2'],
                    'postcode' => $store['postcode'],
                    'city' => $store['city'],
                    'phone' => $store['phone'],
                    'img' => $context->link->getBaseLink(null, null) . 'img/st/' . $storeId . '.jpg',
                    'status' => $status,
                    'cms_link' => $store['cms_link'],
                    'directions_label' => $this->l('Get directions'),
                    'hours_label' => $this->l('See hours'),
                ];
                if ($markerIcon) {
                    $marker['icon'] = $context->link->getBaseLink(null, null) . 'modules/' . $this->name . '/views/img/' . $markerIcon;
                }
                $markers[] = $marker;
            }
            $gmapScript = EverblocklightTools::generateGoogleMapScript($markers);
            if ($gmapScript) {
                file_put_contents($filePath, $gmapScript);
            }
        } elseif (file_exists($filePath)) {
            unlink($filePath);
        }
        $this->postSuccess[] = $this->l('All settings have been saved');
    }

    protected function emptyAllCache()
    {
        EverblocklightCache::clearAllModuleCache();
        $this->postSuccess[] = $this->l('Everblocklight cache has been cleared');
    }

    public function hookActionAdminControllerSetMedia()
    {
        $controller = Tools::getValue('controller');
        $isModuleConfiguration = Tools::getValue('configure') === $this->name;
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $isSymfonyContentForm = (bool) preg_match('#/(?:modules/)?everblocklight/(blocks|shortcodes)/(new|[0-9]+/edit)#', $requestUri);
        $isSymfonyEverblocklightAdmin = strpos($requestUri, '/modules/everblocklight/') !== false
            || (bool) preg_match('#/everblocklight/(blocks|shortcodes|hooks|configuration|clear-cache)#', $requestUri);
        $moduleControllers = [
            'AdminEverBlockLight',
            'AdminEverBlockLightConfiguration',
            'AdminEverBlockLightHook',
            'AdminEverBlockLightShortcode',
            'AdminEverBlockLightShortcodeDocumentation',
        ];

        if (Tools::getValue('id_' . $this->name)
            || Tools::getIsset('add' . $this->name)
            || $isModuleConfiguration
            || $isSymfonyEverblocklightAdmin
            || in_array($controller, $moduleControllers, true)
        ) {
            $this->context->controller->addCss($this->_path . 'views/css/ever.css');
            $this->context->controller->addCSS(
                'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.58.1/codemirror.min.css',
                'all'
            );
            $this->context->controller->addCSS(
                'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.58.1/theme/dracula.min.css',
                'all'
            );
            $this->context->controller->addJS(
                'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.58.1/codemirror.min.js'
            );
            $this->context->controller->addJS(
                'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.58.1/mode/javascript/javascript.min.js'
            );
            $this->context->controller->addJs($this->_path . 'views/js/admin.js');
            if ((bool) Configuration::get('EVERBLOCKLIGHT_TINYMCE') === true
                && !$isModuleConfiguration
                && ($isSymfonyContentForm || Tools::getValue('id_' . $this->name) || Tools::getIsset('add' . $this->name))
            ) {
                $this->context->controller->addJs(__PS_BASE_URI__ . 'js/tiny_mce/tinymce.min.js');
                $this->context->controller->addJs(__PS_BASE_URI__ . 'js/admin/tinymce.inc.js');
                $this->context->controller->addJs($this->_path . 'views/js/adminTinyMce.js');
            }
        }
    }


    public function hookActionCmsPageFormBuilderModifier($params)
    {
        /** @var FormBuilderInterface $formBuilder */
        $formBuilder = $params['form_builder'];
        $idCms = (int) $params['id'];

        $stores = Store::getStores((int) Context::getContext()->language->id);
        $choices = [];
        $selectedStoreId = null;

        foreach ($stores as $store) {
            $choices[$store['name']] = (int) $store['id_store'];

            // Vérifie si ce store est lié à cette page CMS
            $cmsLinked = (int) Configuration::get('QCD_ASSOCIATED_CMS_PAGE_ID_STORE_' . $store['id_store']);
            if ($cmsLinked === $idCms) {
                $selectedStoreId = (int) $store['id_store'];
            }
        }
        $formBuilder->add('qcd_associated_store', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
            'label' => $this->l('Associate with store'),
            'required' => false,
            'choices' => $choices,
            'placeholder' => $this->l('Select a store'),
            'data' => $selectedStoreId,
            'attr' => [
                'class' => 'form-select',
            ],
        ]);
    }

    public function hookActionObjectCmsUpdateAfter($params)
    {
        /** @var CMS $cms */
        $cms = $params['object'];
        $cmsPage = Tools::getValue('cms_page');

        if (!empty($cmsPage['qcd_associated_store'])) {
            $id_store = (int) $cmsPage['qcd_associated_store'];
            Configuration::updateValue(
                'QCD_ASSOCIATED_CMS_PAGE_ID_STORE_' . $id_store,
                (int) $cms->id,
                false, // id_lang
                $this->context->shop->id // id_shop
            );
        }
    }

    public function hookActionOutputHTMLBefore($params)
    {
        $txt = $params['html'];
        if (!EverblocklightTools::hasShortcodeToken($txt)) {
            return $txt;
        }
        try {
            $context = Context::getContext();
            // @Todo : move to EverblocklightShortcodes
            $txt = EverblocklightTools::renderShortcodes($txt, $context, $this);
            $params['html'] = $txt;
            return $params['html'];
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Ever Block Light hookActionOutputHTMLBefore : ' . $e->getMessage()
            );
            EverblocklightTools::setLog(
                $this->name . date('y-m-d'),
                $e->getMessage()
            );
            return $params['html'];
        }
    }

    public function hookActionCheckoutRender($params)
    {
        $stepTitle = $this->getConfigInMultipleLangs('EVERBLOCKLIGHT_OPTIONS_TITLE');
        if (!$stepTitle[$this->context->language->id]
            || empty($stepTitle[$this->context->language->id])
        ) {
            return;
        }
        $translator = Context::getContext()->getTranslator();

        /** @var CheckoutProcess $process */
        $process = $params['checkoutProcess'];
        $steps = $process->getSteps();

        $everStep = new EverblocklightCheckoutStep(
            $this->context,
            $translator,
            $this
        );
        $everStep->setCheckoutProcess($process);
        switch ((int) Configuration::get('EVERBLOCKLIGHT_OPTIONS_POSITION')) {
            case 1:
                $newSteps = [
                    $steps[0],
                    $everStep,
                    $steps[1],
                    $steps[2],
                    $steps[3]
                ];
                break;

            case 2:
                $newSteps = [
                    $steps[0],
                    $steps[1],
                    $everStep,
                    $steps[2],
                    $steps[3]
                ];
                break;

            case 3:
                $newSteps = [
                    $steps[0],
                    $steps[1],
                    $steps[2],
                    $everStep,
                    $steps[3]
                ];
                break;

            default:
                $newSteps = [
                    $steps[0],
                    $everStep,
                    $steps[1],
                    $steps[2],
                    $steps[3]
                ];
                break;
        }
        $process->setSteps($newSteps);
    }

    public function hookDisplayOrderDetail($params)
    {
        return $this->hookDisplayOrderConfirmation($params);
    }

    public function hookDisplayOrderConfirmation($params)
    {
        try {
            $order = $params['order'];
            $checkoutSessionData = $this->getCartSessionDatas(
                $order->id_cart
            );
            if (isset($checkoutSessionData) && $checkoutSessionData) {
                $checkoutSessionData = json_decode(json_encode($checkoutSessionData), true);
                if (!$checkoutSessionData) {
                    return;
                }
                $hiddenKeys = [
                    'hidden',
                    'everHide',
                    'submitCustomStep',
                    'controller',
                ];
                if (is_array($checkoutSessionData)) {
                    foreach ($checkoutSessionData as $key => $value) {
                        if (in_array($key, $hiddenKeys)) {
                            unset($checkoutSessionData[$key]);
                        }
                        if (empty($value)) {
                            unset($checkoutSessionData[$key]);
                        }
                    }
                    $this->context->smarty->assign(array(
                        'checkoutSessionData' => $checkoutSessionData,
                    ));
                    return $this->display(__FILE__, 'views/templates/hook/orderconfirmation.tpl');
                }
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog($this->name . ' | ' . $e->getMessage());
            EverblocklightTools::setLog(
                $this->name . date('y-m-d'),
                $e->getMessage()
            );
        }
    }

    public function hookDisplayAdminOrder($params)
    {
        try {
            $order = new Order((int) $params['id_order']);
            $checkoutSessionData = $this->getCartSessionDatas(
                $order->id_cart
            );
            if (isset($checkoutSessionData) && $checkoutSessionData) {
                $checkoutSessionData = json_decode(json_encode($checkoutSessionData), true);
                if (is_array($checkoutSessionData) && !empty($checkoutSessionData)) {
                    $this->context->smarty->assign(array(
                        'checkoutSessionData' => $checkoutSessionData,
                    ));
                    return $this->display(__FILE__, 'views/templates/hook/orderconfirmation.tpl');
                }
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog($this->name . ' | ' . $e->getMessage());
            EverblocklightTools::setLog(
                $this->name . date('y-m-d'),
                $e->getMessage()
            );
        }
    }

    public function hookDisplayPDFDeliverySlip($params)
    {
        return $this->hookDisplayPDFInvoice($params);
    }

    public function hookDisplayPDFInvoice($params)
    {
        try {
            $order = new Order((int) $params['object']->id_order);
            $checkoutSessionData = $this->getCartSessionDatas(
                $order->id_cart
            );
            if (isset($checkoutSessionData) && $checkoutSessionData) {
                $checkoutSessionData = json_decode(json_encode($checkoutSessionData), true);
                $hiddenKeys = [
                    'hidden',
                    'everHide',
                    'submitCustomStep',
                    'controller',
                ];
                if (is_array($checkoutSessionData) && !empty($checkoutSessionData)) {
                    foreach ($checkoutSessionData as $key => $value) {
                        if (in_array($key, $hiddenKeys)) {
                            unset($checkoutSessionData[$key]);
                        }
                        if (empty($value)) {
                            unset($checkoutSessionData[$key]);
                        }
                    }
                    $this->context->smarty->assign(array(
                        'checkoutSessionData' => $checkoutSessionData,
                    ));
                    return $this->display(__FILE__, 'views/templates/hook/pdf.tpl');
                }
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog($this->name . ' | ' . $e->getMessage());
            EverblocklightTools::setLog(
                $this->name . date('y-m-d'),
                $e->getMessage()
            );
        }
    }

    public function hookActionEmailAddAfterContent($params)
    {
        try {
            $context = Context::getContext();
            $languageId = $params['id_lang'];
            $lang = new Language(
                (int) $languageId
            );
            $context->language = $lang;
            $params['template_txt'] = EverblocklightTools::renderShortcodes($params['template_txt'], $context, $this);
            $params['template_html'] = EverblocklightTools::renderShortcodes($params['template_html'], $context, $this);
            return $params;
        } catch (Exception $e) {
            PrestaShopLogger::addLog($this->name . ' | ' . $e->getMessage());
            EverblocklightTools::setLog(
                $this->name . date('y-m-d'),
                $e->getMessage()
            );
        }
    }

    public function hookActionEmailSendBefore($params)
    {
        if (isset($params['templateVars']['{id_order}'])) {
            try {
                $id_order = (int) $params['templateVars'] ["{id_order}"];
                $order = new Order(
                    (int) $id_order
                );
                $checkoutSessionData = $this->getCartSessionDatas(
                    $order->id_cart
                );
                if (isset($checkoutSessionData) && $checkoutSessionData) {
                    $checkoutSessionData = json_decode(json_encode($checkoutSessionData), true);
                    $hiddenKeys = [
                        'hidden',
                        'everHide',
                        'submitCustomStep',
                        'controller',
                    ];
                    if (is_array($checkoutSessionData) && !empty($checkoutSessionData)) {
                        foreach ($checkoutSessionData as $key => $value) {
                            if (in_array($key, $hiddenKeys)) {
                                unset($checkoutSessionData[$key]);
                            }
                            if (empty($value)) {
                                unset($checkoutSessionData[$key]);
                            }
                        }
                        $this->context->smarty->assign(array(
                            'checkoutSessionData' => $checkoutSessionData,
                        ));
                        $optionsHtml = $this->context->smarty->fetch(
                            $this->local_path . 'views/templates/hook/pdf.tpl'
                        );
                        $params['templateVars']['{order_options}'] = $optionsHtml;
                    }
                }
            } catch (Exception $e) {
                PrestaShopLogger::addLog($this->name . ' | ' . $e->getMessage());
                EverblocklightTools::setLog(
                    $this->name . date('y-m-d'),
                    $e->getMessage()
                );
            }
        }
        return $params;
    }

    public static function getCartSessionDatas($idCart)
    {
        $sql = new DbQuery();
        $sql->select('checkout_session_data');
        $sql->from(
            'cart'
        );
        $sql->where(
            'id_cart = ' . (int) $idCart
        );
        $res =  Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
        if (!$res) {
            return;
        }
        $checkout_session_data = json_decode(
            $res
        );
        foreach ($checkout_session_data as $key => $value) {
            if ($key == 'ever-checkout-step') {
                return $value->everdata;
            }
        }
        return false;
    }

    public function hookActionObjectEverBlockLightClassDeleteAfter($params)
    {
        $this->clearBlockObjectCacheFromHook($params);
    }

    public function hookActionObjectEverBlockLightClassUpdateAfter($params)
    {
        $this->clearBlockObjectCacheFromHook($params);
    }

    private function clearBlockObjectCacheFromHook(array $params): void
    {
        $object = $params['object'] ?? null;
        $blockId = is_object($object) && isset($object->id) ? (int) $object->id : null;
        $shopId = is_object($object) && isset($object->id_shop) ? (int) $object->id_shop : (int) $this->context->shop->id;
        $hookId = is_object($object) && isset($object->id_hook) ? (int) $object->id_hook : 0;

        EverBlockLightClass::clearCache($blockId, $shopId, Language::getLanguages(false), $hookId > 0 ? [$hookId] : []);
    }

    public function everHook($method, $args)
    {
        $position = isset($args[0]['position']) ? (int) $args[0]['position'] : null;
        $context = Context::getContext();
        $id_hook = (int) Hook::getIdByName(lcfirst(str_replace('hook', '', $method)));
        $hookName = lcfirst(str_replace('hook', '', $method));
        $idObj = 0;
        if (Tools::getValue('id_product')) {
            $idObj = (int) Tools::getValue('id_product');
        }
        if (Tools::getValue('id_category')) {
            $idObj = (int) Tools::getValue('id_category');
        }
        if (Tools::getValue('id_manufacturer')) {
            $idObj = (int) Tools::getValue('id_manufacturer');
        }
        if (Tools::getValue('id_supplier')) {
            $idObj = (int) Tools::getValue('id_supplier');
        }
        if (Tools::getValue('id_cms')) {
            $idObj = (int) Tools::getValue('id_cms');
        }
        $everblocklight = EverblocklightClass::getBlocks(
            (int) $id_hook,
            (int) $context->language->id,
            (int) $context->shop->id
        );
        $isPreview = isset($args[0]['everblocklight_preview']) && (bool) $args[0]['everblocklight_preview'];
        $isBypassed = in_array($method, $this->bypassedControllers, true);
        $id_entity = isset($context->customer->id) && $context->customer->id ? (int) $context->customer->id : false;
        $customerGroups = $id_entity
            ? Customer::getGroupsStatic((int) $id_entity)
            : array_values(array_unique(array_filter([
                (int) Configuration::get('PS_UNIDENTIFIED_GROUP'),
                (int) Configuration::get('PS_GUEST_GROUP'),
                (int) Configuration::get('PS_CUSTOMER_GROUP'),
            ])));
        $currentBlock = [];
        $visibleBlocks = [];
        $visibleCacheIds = [];

        foreach ($everblocklight as $block) {
            if ((bool) $block['modal'] === true
                && (bool) EverblocklightTools::isBot() === true
            ) {
                continue;
            }
            if (Validate::isInt($position) && (int) $block['position'] != (int) $position) {
                continue;
            }
            // Check device
            if ((int) $block['device'] > 0
                && (int) $context->getDevice() != (int) $block['device']
            ) {
                continue;
            }
            if ((bool) $block['only_home'] === true
                && Tools::getValue('controller') != 'index'
            ) {
                continue;
            }
            // Only category management
            if ((bool) $block['only_category'] === true
                && Tools::getValue('controller') != 'category'
            ) {
                continue;
            }
            // Only manufacturer management
            if ((bool) $block['only_manufacturer'] === true
                && Tools::getValue('controller') != 'manufacturer'
            ) {
                continue;
            }
            // Only supplier management
            if ((bool) $block['only_supplier'] === true
                && Tools::getValue('controller') != 'supplier'
            ) {
                continue;
            }
            // Only CMS category management
            if ((bool) $block['only_cms_category'] === true
                && !Tools::getValue('id_cms_category')
            ) {
                continue;
            }
            $continue = false;
            // Only category pages
            if ((bool) $block['only_category'] === true
                && Tools::getValue('controller') === 'category'
            ) {
                $categories = json_decode($block['categories']);
                $continue = !is_array($categories) || !in_array((int) Tools::getValue('id_category'), $categories);
            }
            // Only manufacturer pages
            if ((bool) $block['only_manufacturer'] === true
                && Tools::getValue('controller') === 'manufacturer'
            ) {
                $manufacturers = json_decode($block['manufacturers']);
                $continue = !is_array($manufacturers) || !in_array((int) Tools::getValue('id_manufacturer'), $manufacturers);
            }
            // Only supplier pages
            if ((bool) $block['only_supplier'] === true
                && Tools::getValue('controller') === 'supplier'
            ) {
                $suppliers = json_decode($block['suppliers']);
                $continue = !is_array($suppliers) || !in_array((int) Tools::getValue('id_supplier'), $suppliers);
            }
            // Only CMS category pages
            if ((bool) $block['only_cms_category'] === true
                && Tools::getValue('controller') === 'cms'
                && Tools::getValue('id_cms_category')
            ) {
                $cms_categories = json_decode($block['cms_categories']);
                $continue = !is_array($cms_categories) || !in_array((int) Tools::getValue('id_cms_category'), $cms_categories);
            }
            // Only products pages with specific category
            if (
                Tools::getValue('id_product')
                && Tools::getValue('controller') === 'product'
                && (bool) $block['only_category_product'] === true
            ) {
                $product = new Product((int) Tools::getValue('id_product'));
                $categories = json_decode($block['categories']);
                $defaultCategory = (int) $product->id_category_default;
                if ($categories && is_array($categories)) {
                    $continue = !in_array($defaultCategory, $categories);
                }
            }
            if ((bool) $continue === true) {
                continue;
            }
            // Date start and date end management
            $now = new DateTime();
            $now = $now->format('Y-m-d H:i:s');
            if (!empty($block['date_start'])
                && $block['date_start'] !== '0000-00-00 00:00:00'
                && $block['date_start'] > $now
            ) {
                continue;
            }
            if (!empty($block['date_end'])
                && $block['date_end'] !== '0000-00-00 00:00:00'
                && $block['date_end'] < $now
            ) {
                continue;
            }
            $allowedGroups = json_decode($block['groups'], true);
            if (isset($customerGroups)
                && !empty($allowedGroups)
                && !array_intersect($allowedGroups, $customerGroups)
            ) {
                continue;
            }

            $visibleBlocks[] = $block;
            $visibleCacheIds[] = $this->buildBlockRenderCacheId($block, $method, $hookName, $context, $idObj, $position);
        }

        if (!$isPreview && !empty($visibleCacheIds)) {
            $cachedHtml = '';
            $allBlocksCached = true;
            foreach ($visibleCacheIds as $cacheId) {
                if (!EverblocklightCache::isCacheStored($cacheId)) {
                    $allBlocksCached = false;
                    break;
                }
                $cachedHtml .= (string) EverblocklightCache::cacheRetrieve($cacheId);
            }

            if ($allBlocksCached) {
                return $cachedHtml;
            }
        }

        foreach ($visibleBlocks as $index => $block) {
            if ((bool) $block['obfuscate_link'] === true) {
                $block['content'] = EverblocklightTools::obfuscateText(
                    $block['content']
                );
            }
            if ((bool) $block['lazyload'] === true) {
                $block['content'] = EverblocklightTools::addLazyLoadToImages(
                    $block['content']
                );
            }
            if ($isBypassed) {
                $block['content'] = strip_tags($block['content']);
            }
            $currentBlock[] = [
                'block' => $block,
                '_everblocklight_cache_id' => $visibleCacheIds[$index] ?? null,
            ];
        }

        Hook::exec(
            'actionRenderBlockBefore',
            [
                'everhook' => trim($method),
                $this->name => &$currentBlock,
                'args' => $args,
            ]
        );

        return $this->renderCachedBlockItems($currentBlock, $method, $hookName, $args, $context, $idObj, $position, $isPreview, $isBypassed);
    }

    private function renderCachedBlockItems(array $items, string $method, string $hookName, array $args, Context $context, int $idObj, ?int $position, bool $isPreview, bool $isBypassed): string
    {
        $html = '';
        foreach ($items as $item) {
            if (!isset($item['block'])) {
                continue;
            }
            if (!is_array($item['block'])) {
                $html .= $this->renderBlockItems([$item], $method, $args, $isBypassed);
                continue;
            }

            $cacheId = isset($item['_everblocklight_cache_id']) && is_string($item['_everblocklight_cache_id'])
                ? $item['_everblocklight_cache_id']
                : $this->buildBlockRenderCacheId($item['block'], $method, $hookName, $context, $idObj, $position);
            if ($isPreview || !EverblocklightCache::isCacheStored($cacheId)) {
                $rendered = $this->renderBlockItems([$item], $method, $args, $isBypassed);
                if (!$isPreview) {
                    EverblocklightCache::cacheStore($cacheId, $rendered);
                }
                $html .= $rendered;
                continue;
            }

            $html .= (string) EverblocklightCache::cacheRetrieve($cacheId);
        }

        return $html;
    }

    private function renderBlockItems(array $items, string $method, array $args, bool $isBypassed): string
    {
        $this->context->smarty->assign([
            'everhook' => trim($method),
            $this->name => $items,
            'args' => $args,
            'is_bypassed' => $isBypassed,
        ]);

        return $this->display(__FILE__, $this->name . '.tpl');
    }

    private function buildBlockRenderCacheId(array $block, string $method, string $hookName, Context $context, int $idObj, ?int $position): string
    {
        $blockId = (int) ($block['id_everblocklight'] ?? 0);
        $fingerprintSource = json_encode($block);
        if (!is_string($fingerprintSource)) {
            $fingerprintSource = serialize($block);
        }

        return str_replace('|', '-', implode('-', [
            $this->name,
            'block',
            $blockId,
            'id_hook',
            (int) ($block['id_hook'] ?? 0),
            'version',
            EverblocklightCache::getObjectCacheVersion('block', $blockId),
            'controller',
            trim((string) Tools::getValue('controller')),
            'method',
            trim($method),
            'hookName',
            trim($hookName),
            'idObj',
            (int) $idObj,
            'idLang',
            (int) $context->language->id,
            'idShop',
            (int) $context->shop->id,
            'idCurrency',
            (int) $context->currency->id,
            'device',
            (int) $context->getDevice(),
            'position',
            $position === null ? 'all' : (int) $position,
            'hash',
            md5($fingerprintSource),
        ]));
    }

    public function hookDisplayHeader()
    {
        if (Tools::getValue('eac')
            && Validate::isInt(Tools::getValue('eac'))
        ) {
            EverblocklightTools::addToCartByUrl(
                $this->context,
                (int) Tools::getValue('id_product'),
                (int) Tools::getValue('id_product_attribute'),
                (int) Tools::getValue('qty')
            );
        }
        if (isset($this->context->controller->php_self)
            && $this->context->controller->php_self === 'product'
            && ($idProduct = (int) Tools::getValue('id_product'))
        ) {
            $cookie = $this->context->cookie;
            $viewed = $cookie->__isset('viewed')
                ? (string) $cookie->__get('viewed')
                : '';
            $viewedArray = array_filter(array_map('intval', explode(',', $viewed)));
            $viewedArray = array_diff($viewedArray, [$idProduct]);
            $viewedArray[] = $idProduct;
            if (count($viewedArray) > 20) {
                $viewedArray = array_slice($viewedArray, -20);
            }
            $cookie->__set('viewed', implode(',', $viewedArray));
        }
        $idShop = (int) $this->context->shop->id;
        if ((bool) EverblocklightCache::getModuleConfiguration('EVERBLOCKLIGHT_LOAD_FRONT_CSS') === true) {
            $this->context->controller->registerStylesheet(
                'module-' . $this->name . '-css',
                'modules/' . $this->name . '/views/css/' . $this->name . '.css',
                ['media' => 'all', 'priority' => 200]
            );
        }
        $this->context->controller->registerJavascript(
            'module-' . $this->name . '-js',
            'modules/' . $this->name . '/views/js/' . $this->name . '.js',
            ['position' => 'bottom', 'priority' => 200, 'version' => $this->version]
        );
        if ((bool) EverblocklightCache::getModuleConfiguration('EVERBLOCKLIGHT_USE_OBF') === true) {
            $this->context->controller->registerJavascript(
                'module-' . $this->name . '-obf-js',
                'modules/' . $this->name . '/views/js/' . $this->name . '-obfuscation.js',
                ['position' => 'bottom', 'priority' => 200]
            );
        }
        $compressedCss = _PS_MODULE_DIR_ . '/' . $this->name . '/views/css/custom-compressed' . $idShop . '.css';
        $customJs = _PS_MODULE_DIR_ . '/' . $this->name . '/views/js/custom' . $idShop . '.js';
        if (file_exists($compressedCss) && filesize($compressedCss) > 0) {
            $this->context->controller->registerStylesheet(
                'module-' . $this->name . '-custom-compressed-css',
                'modules/' . $this->name . '/views/css/custom-compressed' . $idShop . '.css',
                ['media' => 'all', 'priority' => 200]
            );
        }
        if (file_exists($customJs) && filesize($customJs) > 0) {
            $this->context->controller->registerJavascript(
                'module-' . $this->name . '-compressed-js',
                'modules/' . $this->name . '/views/js/custom' . $idShop . '.js',
                ['position' => 'bottom', 'priority' => 200]
            );
        }
        $externalJs = EverblocklightCache::getModuleConfiguration('EVERBLOCKLIGHT_JS_LINKS');
        $jsLinksArray = [];
        if ($externalJs) {
            $jsLinksArray = explode("\n", $externalJs);
            foreach ($jsLinksArray as $key => $value) {
                $this->context->controller->registerJavascript(
                    'module-' . $this->name . '-custom-' . (int) $key . '-js',
                    $value,
                    ['server' => 'remote', 'position' => 'bottom', 'priority' => 200]
                );
            }
        }
        $externalCss = EverblocklightCache::getModuleConfiguration('EVERBLOCKLIGHT_CSS_LINKS');
        $cssLinksArray = [];
        if ($externalCss) {
            $cssLinksArray = explode("\n", $externalCss);
            foreach ($cssLinksArray as $key => $value) {
                $this->context->controller->registerStylesheet(
                    'module-' . $this->name . '-custom-' . (int) $key . '-js',
                    $value,
                    ['server' => 'remote', 'media' => 'all', 'priority' => 200]
                );
            }
        }
        // Do not show GMAP api KEY on Everblocklight cache
        $apiKey = Configuration::get('EVERBLOCKLIGHT_GMAP_KEY');
        if ($apiKey && Tools::getValue('controller') == 'cms') {
            $filename = 'store-locator-' . $idShop . '.js';
            $filePath = _PS_MODULE_DIR_ . $this->name . '/views/js/' . $filename;
            if (file_exists($filePath) && filesize($filePath) > 0) {
                $this->context->controller->registerJavascript(
                    'module-' . $this->name . '-custom-gmap-js',
                    'https://maps.googleapis.com/maps/api/js?key=' . $apiKey . '&libraries=places,geometry',
                    ['server' => 'remote', 'position' => 'bottom', 'priority' => 300, 'attributes' => 'defer']
                );
                $this->context->controller->registerJavascript(
                    'module-' . $this->name . '-shop-map-js',
                    'modules/' . $this->name . '/views/js/' . $filename,
                    ['server' => 'local', 'position' => 'bottom', 'priority' => 400, 'attributes' => 'defer']
                );
            }
        }
        $contactLink = base64_encode(
            $this->context->link->getModuleLink(
                $this->name,
                'contact'
            )
        );
        $modalLink = base64_encode(
            $this->context->link->getModuleLink(
                $this->name,
                'modal'
            )
        );
        $employeeLogged = false;
        if (isset($this->context->employee) && $this->context->employee) {
            if (method_exists($this->context->employee, 'isLoggedBack')) {
                $employeeLogged = (bool) $this->context->employee->isLoggedBack();
            }
            if (!$employeeLogged && (int) $this->context->employee->id > 0) {
                $employeeLogged = true;
            }
        }
        $this->context->smarty->assign('everblocklight_is_employee', $employeeLogged);

        Media::addJsDef([
            'everblocklight_contact_link' => $contactLink,
            'everblocklight_modal_link' => $modalLink,
            'everblocklight_token' => Tools::getToken(),
            'everblocklight_is_employee' => $employeeLogged,
        ]);
        $filePath = _PS_MODULE_DIR_ . $this->name . '/views/js/header-scripts-' . $this->context->shop->id . '.js';
        if (file_exists($filePath) && filesize($filePath) > 0) {
            return PHP_EOL . file_get_contents($filePath) . PHP_EOL;
        }
    }

    protected function compressCSSCode($css)
    {
        // Supprime les commentaires
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        // Supprime les espaces inutiles
        $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $css);
        // Remplace les séparateurs de déclarations par des points-virgules
        $css = str_replace(';}', '}', $css);
        // Supprime les espaces inutiles entre les propriétés et les valeurs
        $css = preg_replace('/[\s]*:[\s]*(.*?)[\s]*;/', ':$1;', $css);
        return $css;
    }

    public function getConfigInMultipleLangs($key, $idShopGroup = null, $idShop = null): array
    {
        $resultsArray = [];
        foreach (Language::getIDs() as $idLang) {
            $resultsArray[$idLang] = Configuration::get($key, $idLang, $idShopGroup, $idShop);
        }
        return $resultsArray;
    }

    protected function secureModuleFolder()
    {
        $moduleName = $this->name;
        $moduleAuthor = $this->author;
        $indexContent = Tools::getDefaultIndexContent();
        // Utiliser le chemin du module actuel comme point de départ
        $moduleDir = $this->getLocalPath();
        // Fonction récursive pour ajouter le fichier index.php
        static::addIndexFileRecursively($moduleDir, $indexContent);
    }

    /**
     * Ajoute le fichier index.php récursivement dans tous les répertoires et sous-répertoires
     *
     * @param string $dir Le répertoire de départ
     * @param string $indexContent Le contenu à ajouter dans le fichier index.php
     */
    protected function addIndexFileRecursively($dir, $indexContent)
    {
        // Vérifier si le fichier index.php existe, sinon le créer
        $indexPath = $dir . '/index.php';
        if (!file_exists($indexPath)) {
            file_put_contents($indexPath, $indexContent);
        }
        // Parcourir le répertoire pour trouver des sous-répertoires et appliquer la fonction récursivement
        $files = new DirectoryIterator($dir);
        foreach ($files as $file) {
            if ($file->isDot()) {
                continue;
            }
            if ($file->isDir()) {
                $this->addIndexFileRecursively(
                    $file->getPathname(),
                    $indexContent
                );
            }
        }
    }

    public function encrypt($data)
    {
        if (method_exists('Tools', 'encrypt')) {
            return Tools::encrypt($data);
        }

        if (method_exists('Tools', 'hash')) {
            return Tools::hash($data);
        }

        return hash('sha256', (string) $data);
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }
}
