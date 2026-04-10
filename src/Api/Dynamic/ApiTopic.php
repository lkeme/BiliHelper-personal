<?php declare(strict_types=1);

namespace Bhp\Api\Dynamic;

use Bhp\Api\Support\AbstractApiClient;
use Bhp\Request\Request;

final class ApiTopic extends AbstractApiClient
{
    public function __construct(
        Request $request,
    ) {
        parent::__construct($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function feed(string $topicId, int $sortBy = 0, string $offset = '', int $pageSize = 20): array
    {
        return $this->decodeGet('pc', 'https://api.bilibili.com/x/polymer/web-dynamic/v1/feed/topic', [
            'topic_id' => $topicId,
            'sort_by' => $sortBy,
            'offset' => $offset,
            'page_size' => $pageSize,
            'source' => 'Web',
            'features' => 'itemOpusStyle,listOnlyfans,opusBigCover,onlyfansVote,decorationCard',
        ], [
            'origin' => 'https://t.bilibili.com',
            'referer' => 'https://t.bilibili.com/topic/name/' . $topicId,
        ], 'dynamic.topic.feed');
    }
}
