<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Catalog;

final class ActivityCatalogLoader
{
    /** @var CatalogSourceInterface[] */
    private array $sources;
    private ActivityCatalogValidator $validator;

    /**
     * @param CatalogSourceInterface[] $sources
     */
    public function __construct(array $sources, ?ActivityCatalogValidator $validator = null)
    {
        $this->sources = $sources;
        $this->validator = $validator ?? new ActivityCatalogValidator();
    }

    /**
     * @return ActivityCatalogItem[]
     */
    public function load(): array
    {
        /** @var array<string, ActivityCatalogItem> $merged */
        $merged = [];
        /** @var array<string, int> $sourcePriorityById */
        $sourcePriorityById = [];
        foreach ($this->sources as $source) {
            $sourcePriority = $source->priority();
            foreach ($source->load() as $item) {
                if (!$item instanceof ActivityCatalogItem) {
                    continue;
                }

                $id = $item->id();
                if ($id === '') {
                    continue;
                }

                $current = $merged[$id] ?? null;
                $currentPriority = $sourcePriorityById[$id] ?? PHP_INT_MIN;
                $incomingTimestamp = $item->updateTimestamp();
                $currentTimestamp = $current?->updateTimestamp() ?? PHP_INT_MIN;
                $shouldReplace = $current === null
                    || $incomingTimestamp > $currentTimestamp
                    || ($incomingTimestamp === $currentTimestamp && $sourcePriority > $currentPriority);
                if ($shouldReplace) {
                    $merged[$id] = $item;
                    $sourcePriorityById[$id] = $sourcePriority;
                }
            }
        }

        return $this->validator->validate(array_values($merged));
    }
}

