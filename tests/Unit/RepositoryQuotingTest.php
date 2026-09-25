<?php

declare(strict_types=1);

namespace Everblocklight\Tests\Unit;

use Everblocklight\Tools\Repository\BlockRepository;
use ReflectionClass;
use ReflectionMethod;

final class RepositoryQuotingTest extends TestCase
{
    public function testColumnsAreQuotedForReservedWords(): void
    {
        $repository = (new ReflectionClass(BlockRepository::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($repository, 'quoteColumns');
        $method->setAccessible(true);

        self::assertSame(
            ['`groups`' => '[]', '`name`' => 'Bloc', '`evil`' => 1],
            $method->invoke($repository, ['groups' => '[]', 'name' => 'Bloc', 'ev`il' => 1])
        );
    }

    public function testBlockSaveQuotesColumnsBeforeCallingDbal(): void
    {
        $source = (string) file_get_contents(EVERBLOCKLIGHT_MODULE_ROOT . '/src/Repository/BlockRepository.php');
        $save = substr($source, (int) strpos($source, 'public function save('));
        $save = substr($save, 0, (int) strpos($save, 'public function setActive('));

        self::assertLessThan(
            strpos($save, '->insert('),
            strpos($save, '$data = $this->quoteColumns($data);'),
            'La colonne `groups` doit être protégée avant insert()/update() (mot réservé MySQL 8)'
        );
    }
}
