<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Catalog;

final class RemoteCatalogSource extends LocalCatalogSource
{
    public function __construct(
        string $path,
        private readonly bool $enabled = false,
    ) {
        parent::__construct($path);
    }

    /**
     * @return ActivityCatalogItem[]
     */
    public function load(): array
    {
        if (!$this->enabled) {
            return [];
        }

        return parent::load();
    }
}
