<?php declare(strict_types=1);

namespace Bhp\Plugin\ActivityLottery\Internal;

use Bhp\Api\Dynamic\ApiTopic;

final class EraTopicArchiveService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchArchives(string $topicId, int $limit = 20): array
    {
        $response = ApiTopic::feed($topicId, 0, '', $limit);
        if (($response['code'] ?? -1) !== 0) {
            return [];
        }

        $items = $response['data']['topic_card_list']['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $archives = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $dynamic = $item['dynamic_card_item'] ?? null;
            if (!is_array($dynamic) || !(bool)($dynamic['visible'] ?? false)) {
                continue;
            }

            $archive = $dynamic['modules']['module_dynamic']['major']['archive'] ?? null;
            if (!is_array($archive)) {
                continue;
            }

            $aid = trim((string)($archive['aid'] ?? ''));
            if ($aid === '') {
                continue;
            }

            $archives[] = [
                'aid' => $aid,
                'bvid' => trim((string)($archive['bvid'] ?? '')),
                'title' => trim((string)($archive['title'] ?? '')),
            ];

            if (count($archives) >= $limit) {
                break;
            }
        }

        return $archives;
    }
}
