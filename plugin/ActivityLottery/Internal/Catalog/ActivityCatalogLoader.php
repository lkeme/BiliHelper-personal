<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Catalog;

final class ActivityCatalogLoader
{
    /** @var CatalogSourceInterface[] */
    private array $sources;

    /**
     * @param CatalogSourceInterface[] $sources
     */
    public function __construct(array $sources)
    {
        $this->sources = $sources;
    }

    /**
     * @return ActivityCatalogItem[]
     */
    public function load(): array
    {
        /** @var array<string, ActivityCatalogItem> $merged */
        $merged = [];
        foreach ($this->sources as $source) {
            foreach ($source->load() as $item) {
                if (!$item instanceof ActivityCatalogItem) {
                    continue;
                }

                $id = $item->id();
                if ($id === '') {
                    continue;
                }

                $current = $merged[$id] ?? null;
                if ($current === null || $item->updateTimestamp() > $current->updateTimestamp()) {
                    $merged[$id] = $item;
                }
            }
        }

        return array_values($merged);
    }
}
