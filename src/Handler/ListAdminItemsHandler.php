<?php

declare(strict_types=1);

namespace Everblocklight\Tools\Handler;

use Everblocklight\Tools\Query\ListAdminItemsQuery;
use Everblocklight\Tools\Repository\BlockRepository;
use Everblocklight\Tools\Repository\HookRepository;
use Everblocklight\Tools\Repository\ShortcodeRepository;

final class ListAdminItemsHandler
{
    public function __construct(
        private BlockRepository $blockRepository,
        private ShortcodeRepository $shortcodeRepository,
        private HookRepository $hookRepository
    ) {
    }

    public function __invoke(ListAdminItemsQuery $query): array
    {
        return $this->handle($query);
    }

    public function handle(ListAdminItemsQuery $query): array
    {
        return match ($query->section) {
            'blocks' => $this->blockRepository->list($query->shopId, $query->langId),
            'shortcodes' => $this->shortcodeRepository->list($query->shopId, $query->langId),
            'hooks' => $this->hookRepository->listDisplayHooks(),
            default => [],
        };
    }
}
