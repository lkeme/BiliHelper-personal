<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\Lottery;

use Bhp\Api\Response\DynamicReserveLottery;
use Bhp\Util\Common\Common;

final class LotteryContentAnalyzer
{
    /**
     * @return int[]
     */
    public function extractDynamicIds(string $data): array
    {
        $ids = [];
        preg_match_all('/https:\/\/t\.bilibili\.com\/[0-9]+/', $data, $matches);
        foreach ($matches[0] as $url) {
            if (!is_string($url)) {
                continue;
            }

            $ids[] = (int)str_replace('https://t.bilibili.com/', '', $url);
        }

        return $ids;
    }

    /**
     * @param array<int, array<string, mixed>> $articles
     * @return int[]
     */
    public function filterArticleIds(array $articles): array
    {
        $ids = [];
        foreach ($articles as $item) {
            if (!Common::isTimestampInToday((int)($item['publish_time'] ?? 0))) {
                continue;
            }

            $title = (string)($item['title'] ?? '');
            if (!str_contains($title, '抽奖') && !str_contains($title, '预约')) {
                continue;
            }

            $id = (int)($item['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function extractReserveLottery(array $data): ?DynamicReserveLottery
    {
        return DynamicReserveLottery::fromDetailData($data);
    }
}
