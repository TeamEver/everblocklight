<?php

declare(strict_types=1);

namespace Everblocklight\Tests\Unit;

use Everblocklight\Tools\Service\ShortcodeDocumentationProvider;
use ReflectionProperty;

final class ShortcodeDocumentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new ReflectionProperty(ShortcodeDocumentationProvider::class, 'cache'))->setValue(null, []);
        \Context::getContext()->language = (object) ['id' => 1];
    }

    /**
     * @return array<int, string>
     */
    private static function codes(array $docs): array
    {
        $codes = [];
        foreach ($docs as $group) {
            foreach ($group['entries'] ?? [] as $entry) {
                $codes[] = (string) ($entry['code'] ?? '');
            }
        }

        return $codes;
    }

    private static function titles(array $docs): array
    {
        return array_map(static fn (array $group): string => (string) ($group['title'] ?? ''), $docs);
    }

    public function testCoreDocumentationListsModuleShortcodes(): void
    {
        $codes = self::codes(ShortcodeDocumentationProvider::getDocumentation(new \Module()));

        self::assertContains('[cart_total]', $codes);
        self::assertContains('[alert type="success"]Content[/alert]', $codes);
        foreach ($codes as $code) {
            self::assertDoesNotMatchRegularExpression('/everinstagram|wordpress-posts|everfaq|everorderform/', $code, 'Shortcode retiré documenté');
        }
    }

    public function testExternalModulesCanAddGroupsBeforeAndAfter(): void
    {
        \Hook::$listeners['actionBeforeEverblocklightShortcodeDocumentation'][] = static function (array $params): void {
            $params['docs'][] = ['title' => 'Avant', 'entries' => [['code' => '[before_sc]']]];
        };
        \Hook::$listeners['actionAfterEverblocklightShortcodeDocumentation'][] = static function (array $params): void {
            $params['docs'][] = ['title' => 'Après ' . $params['domain'], 'entries' => [['code' => '[after_sc]']]];
        };

        $titles = self::titles(ShortcodeDocumentationProvider::getDocumentation(new \Module()));

        self::assertSame('Avant', $titles[0], 'Le hook Before ajoute ses groupes avant ceux du module');
        self::assertSame('Après Modules.Everblocklight.Shortcodes', end($titles), 'Le hook After ajoute ses groupes à la fin');
    }

    public function testCachedDocumentationKeepsHookAdditions(): void
    {
        $calls = 0;
        \Hook::$listeners['actionAfterEverblocklightShortcodeDocumentation'][] = static function (array $params) use (&$calls): void {
            ++$calls;
            $params['docs'][] = ['title' => 'Externe', 'entries' => [['code' => '[external_sc]']]];
        };

        $first = ShortcodeDocumentationProvider::getDocumentation(new \Module());
        $second = ShortcodeDocumentationProvider::getDocumentation(new \Module());

        self::assertSame(1, $calls, 'Deuxième appel servi depuis le cache');
        self::assertContains('[external_sc]', self::codes($second), 'Le cache contient les ajouts du hook After');
        self::assertSame($first, $second);
    }
}
