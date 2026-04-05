<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Catalog;

final class LocalCatalogSource implements CatalogSourceInterface
{
    public function __construct(private readonly string $path)
    {
    }

    /**
     * @return ActivityCatalogItem[]
     */
    public function load(): array
    {
        return $this->parseItemsFromPath($this->path);
    }

    public function priority(): int
    {
        return 200;
    }

    /**
     * @return ActivityCatalogItem[]
     */
    protected function parseItemsFromPath(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $items = $decoded['items'] ?? $decoded['data'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $result[] = ActivityCatalogItem::fromArray($item);
        }

        return $result;
    }
}

