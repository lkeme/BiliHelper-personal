<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Catalog;

interface CatalogSourceInterface
{
    /**
     * @return ActivityCatalogItem[]
     */
    public function load(): array;

    /**
     * 处理priority
     * @return int
     */
    public function priority(): int;
}

