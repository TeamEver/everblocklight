<?php

declare(strict_types=1);

namespace Everblocklight\Tools\Handler;

use Everblocklight\Tools\Command\ClearEverblocklightCacheCommand;
use Everblocklight\Tools\Service\EverblocklightCache;

final class ClearEverblocklightCacheHandler
{
    public function __invoke(ClearEverblocklightCacheCommand $command): void
    {
        $this->handle($command);
    }

    public function handle(ClearEverblocklightCacheCommand $command): void
    {
        EverblocklightCache::clearAllModuleCache();
    }
}
