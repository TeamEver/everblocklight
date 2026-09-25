<?php

declare(strict_types=1);

namespace Everblocklight\Tools\Query;

final class GetAdminItemQuery
{
    public function __construct(
        public string $section,
        public ?int $id,
        public int $shopId,
        public int $langId
    ) {
    }
}
