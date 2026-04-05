<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Catalog;

interface CatalogSourceInterface
{
    /**
     * @return ActivityCatalogItem[]
     */
    public function load(): array;

    /**
     * 来源优先级。值越大优先级越高。
     */
    public function priority(): int;
}

