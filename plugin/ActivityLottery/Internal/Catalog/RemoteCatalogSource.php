<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal\Catalog;

use Bhp\Request\Request;

final class RemoteCatalogSource implements CatalogSourceInterface
{
    /**
     * @var callable(string): string
     */
    private readonly mixed $fetcher;

    public function __construct(
        private readonly string $url,
        private readonly bool $enabled = false,
        ?CatalogSourceInterface $reader = null,
        ?callable $fetcher = null,
    ) {
        $this->reader = $reader;
        $this->fetcher = $fetcher ?? static fn (string $url): string => (string)Request::get('other', $url);
    }
    private ?CatalogSourceInterface $reader = null;

    /**
     * @return ActivityCatalogItem[]
     */
    public function load(): array
    {
        if (!$this->enabled) {
            return [];
        }

        if ($this->reader instanceof CatalogSourceInterface) {
            return $this->reader->load();
        }

        if (trim($this->url) === '') {
            return [];
        }

        if (is_file($this->url)) {
            return (new LocalCatalogSource($this->url))->load();
        }

        $raw = (string)($this->fetcher)($this->url);
        if (trim($raw) === '') {
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

    public function priority(): int
    {
        return 100;
    }
}
