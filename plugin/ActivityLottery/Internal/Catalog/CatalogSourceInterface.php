<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Catalog;

interface CatalogSourceInterface
{
    /**
     * @return ActivityCatalogItem[]
     */
    public function load(): array;
}
