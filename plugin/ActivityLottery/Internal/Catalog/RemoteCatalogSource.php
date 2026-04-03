<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Catalog;

final class RemoteCatalogSource implements CatalogSourceInterface
{
    private CatalogSourceInterface $reader;

    public function __construct(
        string $path,
        private readonly bool $enabled = false,
        ?CatalogSourceInterface $reader = null,
    ) {
        $this->reader = $reader ?? new LocalCatalogSource($path);
    }

    /**
     * @return ActivityCatalogItem[]
     */
    public function load(): array
    {
        if (!$this->enabled) {
            return [];
        }

        return $this->reader->load();
    }

    public function priority(): int
    {
        return 100;
    }
}
