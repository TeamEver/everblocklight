<?php

declare(strict_types=1);

namespace Everblocklight\Tests\Integrity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Vérifications statiques de cohérence du module (sans PrestaShop) :
 * elles protègent contre les régressions typiques d'un refactoring
 * (template supprimé encore référencé, route orpheline, clé de config non nettoyée...).
 */
final class ModuleIntegrityTest extends TestCase
{
    private const ROOT = EVERBLOCKLIGHT_MODULE_ROOT;

    /**
     * @return array<string, string> chemin relatif => contenu
     */
    private static function sources(array $extensions): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen(self::ROOT) + 1);
            if (preg_match('#^(vendor|tests|\.git)/#', $relative)) {
                continue;
            }
            if (in_array($file->getExtension(), $extensions, true)) {
                $files[$relative] = (string) file_get_contents($file->getPathname());
            }
        }
        ksort($files);

        return $files;
    }

    public function testMainFileDeclaresModuleIdentity(): void
    {
        $main = (string) file_get_contents(self::ROOT . '/everblocklight.php');

        self::assertMatchesRegularExpression('/class Everblocklight extends Module/', $main);
        self::assertStringContainsString("\$this->name = 'everblocklight';", $main);
        self::assertStringContainsString("\$this->author = 'Griiv';", $main);
        self::assertMatchesRegularExpression("/\\\$this->version = '\\d+\\.\\d+\\.\\d+';/", $main);
    }

    public function testReferencedTemplatesExist(): void
    {
        $missing = [];
        foreach (self::sources(['php', 'tpl', 'twig']) as $path => $content) {
            preg_match_all('#(?:views/templates/|module:everblocklight/views/templates/)?((?:hook|front)/[\w/]+\.tpl)#', $content, $m);
            foreach (array_unique($m[1]) as $template) {
                if (!is_file(self::ROOT . '/views/templates/' . $template)) {
                    $missing[] = $path . ' -> ' . $template;
                }
            }
            preg_match_all('#@Modules/everblocklight/(templates/[\w/]+\.twig)#', $content, $m);
            foreach (array_unique($m[1]) as $template) {
                if (!is_file(self::ROOT . '/' . $template)) {
                    $missing[] = $path . ' -> ' . $template;
                }
            }
        }

        self::assertSame([], $missing);
    }

    public function testRoutesPointToExistingControllerActions(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/config/routes.yml');
        $controller = (string) file_get_contents(self::ROOT . '/src/Controller/Admin/EverblocklightAdminController.php');
        preg_match_all("/_controller: '[^']+::(\\w+)'/", $routes, $m);

        self::assertNotEmpty($m[1]);
        foreach (array_unique($m[1]) as $action) {
            self::assertMatchesRegularExpression('/public function ' . $action . '\(/', $controller, "Action $action absente");
        }
    }

    public function testRouteNamesUsedInCodeAreDeclared(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/config/routes.yml');
        preg_match_all('/^(admin_everblocklight_\w+):/m', $routes, $m);
        $declared = array_flip($m[1]);

        $unknown = [];
        foreach (self::sources(['php', 'twig']) as $path => $content) {
            preg_match_all("/['\"](admin_everblocklight_\\w+)['\"]/", $content, $used);
            foreach (array_unique($used[1]) as $route) {
                if (!isset($declared[$route]) && !isset($declared[$route . '_create'])) {
                    $unknown[] = $path . ' -> ' . $route;
                }
            }
        }

        self::assertSame([], $unknown);
    }

    public function testConfigurationKeysUseModulePrefix(): void
    {
        $allowedForeignPrefixes = ['PS_', 'QCD_ASSOCIATED_CMS_PAGE_ID_STORE_'];
        $invalid = [];
        foreach (self::sources(['php']) as $path => $content) {
            preg_match_all("/Configuration::(?:get|updateValue|deleteByName|hasKey)\\(\\s*'([A-Z0-9_]+)/", $content, $m);
            foreach (array_unique($m[1]) as $key) {
                if (str_starts_with($key, 'EVERBLOCKLIGHT_')) {
                    continue;
                }
                foreach ($allowedForeignPrefixes as $prefix) {
                    if (str_starts_with($key, $prefix)) {
                        continue 2;
                    }
                }
                $invalid[] = $path . ' -> ' . $key;
            }
        }

        self::assertSame([], $invalid, 'Toute clé propre au module doit commencer par EVERBLOCKLIGHT_ pour être supprimée à la désinstallation');
    }

    public function testInstallAndUninstallSqlCoverTheSameTables(): void
    {
        $install = (string) file_get_contents(self::ROOT . '/sql/install.php');
        $uninstall = (string) file_get_contents(self::ROOT . '/sql/uninstall.php');
        preg_match_all("/CREATE TABLE IF NOT EXISTS `' \\. _DB_PREFIX_ \\. '(\\w+)`/", $install, $created);
        preg_match_all("/DROP TABLE IF EXISTS `' \\. _DB_PREFIX_ \\. '(\\w+)`/", $uninstall, $dropped);

        $expected = ['everblocklight', 'everblocklight_lang', 'everblocklight_shortcode', 'everblocklight_shortcode_lang'];
        self::assertSame($expected, $created[1]);
        self::assertEqualsCanonicalizing($created[1], $dropped[1]);
    }

    public function testNoReferenceToRemovedFeatures(): void
    {
        $forbidden = '/\b(EverblockFaq|everblock_faq|prettyblocks|qcdpagebuilder|EVERBLOCKLIGHT_OPTIONS_|everorderform|CheckoutStep|everlogin)\b/i';
        $hits = [];
        foreach (self::sources(['php', 'tpl', 'twig', 'js', 'yml']) as $path => $content) {
            if (str_starts_with($path, 'translations/')) {
                continue;
            }
            if (preg_match($forbidden, $content, $m)) {
                $hits[] = $path . ' -> ' . $m[0];
            }
        }

        self::assertSame([], $hits);
    }

    public function testNoOriginalEverblockIdentifiersRemain(): void
    {
        $hits = [];
        foreach (self::sources(['php', 'tpl', 'twig', 'js', 'yml', 'css']) as $path => $content) {
            if (preg_match('/(?<![\w\/.-])(everblock|Everblock|EverBlock|EVERBLOCK)(?!light|Light|LIGHT)/', $content, $m)) {
                $hits[] = $path . ' -> ' . $m[0];
            }
        }

        self::assertSame([], $hits, 'Un identifiant du module original subsiste (risque de conflit avec Ever Block)');
    }

    public static function phpFileProvider(): array
    {
        return array_map(static fn (string $path): array => [$path], array_combine(
            array_keys(self::sources(['php'])),
            array_keys(self::sources(['php']))
        ));
    }

    #[DataProvider('phpFileProvider')]
    public function testPhpFilesAreGuardedAgainstDirectAccess(string $path): void
    {
        $content = (string) file_get_contents(self::ROOT . '/' . $path);
        if (basename($path) === 'index.php' || str_starts_with($path, 'translations/') || str_starts_with($path, 'config/')) {
            self::assertTrue(true);

            return;
        }

        self::assertMatchesRegularExpression(
            "/defined\\('_PS_VERSION_'\\)|^namespace /m",
            $content,
            'Les fichiers PHP hors namespace doivent vérifier _PS_VERSION_'
        );
    }
}
