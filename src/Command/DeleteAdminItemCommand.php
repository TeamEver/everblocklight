<?php

declare(strict_types=1);

namespace Everblocklight\Tools\Command;

final class DeleteAdminItemCommand
{
    public function __construct(
        public string $section,
        public int $id,
        public int $shopId
    ) {
    }
}
