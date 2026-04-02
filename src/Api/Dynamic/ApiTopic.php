<?php declare(strict_types=1);

namespace Bhp\Api\Dynamic;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;

final class ApiTopic
{
    /**
     * @return array<string, mixed>
     */
    public static function feed(
        string $topicId,
        int $sortBy = 0,
        string $offset = '',
        int $pageSize = 20,
    ): array {
        $url = 'https://api.bilibili.com/x/polymer/web-dynamic/v1/feed/topic';
        $payload = [
            'topic_id' => $topicId,
            'sort_by' => $sortBy,
            'offset' => $offset,
            'page_size' => $pageSize,
            'source' => 'Web',
            'features' => 'itemOpusStyle,listOnlyfans,opusBigCover,onlyfansVote,decorationCard',
        ];
        $headers = [
            'origin' => 'https://t.bilibili.com',
            'referer' => 'https://t.bilibili.com/topic/name/' . $topicId,
        ];

        return ApiJson::get('pc', $url, $payload, $headers, 'dynamic.topic.feed');
    }
}
