<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\BigPointExchange\Internal;

final class BigPointCatalogService
{
    /**
     * @param array<string, mixed> $homepageResponse
     * @return array<int, string>
     */
    public function extractCategories(array $homepageResponse): array
    {
        $categories = [];

        foreach ((array)($homepageResponse['data']['goods_category'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = (int)($item['id'] ?? 0);
            $name = trim((string)($item['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }

            $categories[$id] = $name;
        }

        return $categories;
    }

    /**
     * @param array<string, mixed> $skuListResponse
     * @return array<int, array<string, mixed>>
     */
    public function extractPageItems(int $categoryId, string $categoryName, array $skuListResponse, ?int $serverTime = null): array
    {
        $items = [];

        foreach ((array)($skuListResponse['data']['skus'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $normalized = $this->normalizeItem($categoryId, $categoryName, $item, $serverTime);
            if ($normalized === null) {
                continue;
            }

            $items[] = $normalized;
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, array<string, mixed>>
     */
    public function indexByToken(array $items): array
    {
        $indexed = [];

        foreach ($items as $item) {
            $token = trim((string)($item['token'] ?? ''));
            if ($token === '') {
                continue;
            }

            $indexed[$token] = $item;
        }

        return $indexed;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>|null
     */
    private function normalizeItem(int $categoryId, string $categoryName, array $item, ?int $serverTime = null): ?array
    {
        $token = trim((string)($item['token'] ?? ''));
        $title = trim((string)($item['title'] ?? ''));
        if ($token === '' || $title === '') {
            return null;
        }

        $originPrice = max(0, (int)($item['price']['origin'] ?? 0));
        $salePrice = max(0, (int)($item['price']['sale'] ?? $originPrice));
        if ($salePrice <= 0) {
            $salePrice = $originPrice;
        }

        $availabilityLabel = $this->resolveAvailabilityLabel($item, $serverTime);

        return [
            'token' => $token,
            'title' => $title,
            'category_id' => $categoryId,
            'category_name' => trim($categoryName),
            'origin_price' => $originPrice,
            'sale_price' => $salePrice,
            'display_price' => $this->formatDisplayPrice($salePrice, $originPrice),
            'surplus_num' => max(0, (int)($item['inventory']['surplus_num'] ?? 0)),
            'is_sold_out' => (bool)($item['inventory']['is_sold_out'] ?? false),
            'next_sold_time' => max(0, (int)($item['inventory']['next_sold_time'] ?? 0)),
            'next_sold_text' => trim((string)($item['inventory']['next_sold_text'] ?? '')),
            'availability_label' => $availabilityLabel,
            'state' => (int)($item['state'] ?? 0),
            'exchange_limit_type' => (int)($item['exchange_limit_type'] ?? 0),
            'exchange_limit_num' => (int)($item['exchange_limit_num'] ?? 0),
            'option_label' => sprintf(
                '%s | %s | %s',
                $title,
                $this->formatDisplayPrice($salePrice, $originPrice),
                $availabilityLabel,
            ),
            'raw' => $item,
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function resolveAvailabilityLabel(array $item, ?int $serverTime = null): string
    {
        $nextSoldText = trim((string)($item['inventory']['next_sold_text'] ?? ''));
        $nextSoldTime = max(0, (int)($item['inventory']['next_sold_time'] ?? 0));
        $isSoldOut = (bool)($item['inventory']['is_sold_out'] ?? false);
        $surplus = max(0, (int)($item['inventory']['surplus_num'] ?? 0));

        if ($nextSoldText !== '' && $nextSoldTime > 0 && $serverTime !== null && $nextSoldTime > $serverTime) {
            return $nextSoldText;
        }

        if ($isSoldOut || $surplus <= 0) {
            return '暂兑完';
        }

        return '可兑换';
    }

    private function formatDisplayPrice(int $salePrice, int $originPrice): string
    {
        if ($originPrice > 0 && $salePrice > 0 && $salePrice < $originPrice) {
            return sprintf('%d/%d 大积分', $salePrice, $originPrice);
        }

        return sprintf('%d 大积分', max($salePrice, $originPrice));
    }
}
