<?php

declare(strict_types=1);

namespace Everblocklight\Tools\Query;

final class ListAdminItemsQuery
{
    public function __construct(
        public string $section,
        public int $shopId,
        public int $langId
    ) {
    }
}
