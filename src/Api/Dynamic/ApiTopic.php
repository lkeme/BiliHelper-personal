<?php declare(strict_types=1);

namespace Bhp\Api\Dynamic;

use Bhp\Api\Support\ApiJson;
use Bhp\Request\Request;
use Throwable;

final class ApiTopic
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function feed(string $topicId, int $sortBy = 0, string $offset = '', int $pageSize = 20): array
    {
        try {
            $raw = $this->request->getText('pc', 'https://api.bilibili.com/x/polymer/web-dynamic/v1/feed/topic', [
                'topic_id' => $topicId,
                'sort_by' => $sortBy,
                'offset' => $offset,
                'page_size' => $pageSize,
                'source' => 'Web',
                'features' => 'itemOpusStyle,listOnlyfans,opusBigCover,onlyfansVote,decorationCard',
            ], [
                'origin' => 'https://t.bilibili.com',
                'referer' => 'https://t.bilibili.com/topic/name/' . $topicId,
            ]);
        } catch (Throwable $throwable) {
            return [
                'code' => -500,
                'message' => 'dynamic.topic.feed 请求失败: ' . $throwable->getMessage(),
                'data' => [],
            ];
        }

        return ApiJson::decode($raw, 'dynamic.topic.feed');
    }
}
