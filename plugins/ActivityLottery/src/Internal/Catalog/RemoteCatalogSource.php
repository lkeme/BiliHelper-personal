<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\ActivityLottery\Internal\Catalog;

use Bhp\Util\Exceptions\RequestException;

final class RemoteCatalogSource implements CatalogSourceInterface
{
    /**
     * @var callable(string): string
     */
    private readonly mixed $fetcher;

    /**
     * 初始化 RemoteCatalogSource
     * @param string|array $url
     * @param bool $enabled
     * @param CatalogSourceInterface $reader
     * @param callable $fetcher
     */
    public function __construct(
        private readonly string|array $url,
        private readonly bool $enabled = false,
        ?CatalogSourceInterface $reader = null,
        ?callable $fetcher = null,
    ) {
        $this->reader = $reader;
        $this->fetcher = $fetcher ?? static fn (string $url): string => '';
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

        $urls = $this->urls();
        if ($urls === []) {
            return [];
        }

        foreach ($urls as $url) {
            if (is_file($url)) {
                $items = (new LocalCatalogSource($url))->load();
                if ($items !== []) {
                    return $items;
                }
                continue;
            }

            try {
                $raw = (string)($this->fetcher)($url);
            } catch (RequestException) {
                continue;
            }

            if (trim($raw) === '') {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                continue;
            }

            $items = $decoded['items'] ?? $decoded['data'] ?? [];
            if (!is_array($items)) {
                continue;
            }

            $result = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $result[] = ActivityCatalogItem::fromArray($item);
            }

            if ($result !== []) {
                return $result;
            }
        }

        return [];
    }

    /**
     * 处理priority
     * @return int
     */
    public function priority(): int
    {
        return 100;
    }

    /**
     * @return string[]
     */
    private function urls(): array
    {
        if (is_array($this->url)) {
            return array_values(array_filter(array_map(
                static fn (mixed $item): string => trim((string)$item),
                $this->url,
            )));
        }

        $url = trim((string)$this->url);
        return $url === '' ? [] : [$url];
    }
}

