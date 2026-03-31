<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\Lottery;

use Bhp\Api\Dynamic\ApiDetail;
use Bhp\Api\Response\SpaceArticleCatalog;
use Bhp\Api\Space\ApiArticle;

class LotteryDiscoveryService
{
    public function __construct(
        private readonly LotteryQueueCoordinator $queueCoordinator = new LotteryQueueCoordinator(new LotteryContentAnalyzer()),
    ) {
    }

    /**
     * @return array{success: bool, added: int, error: string}
     */
    public function collectArticleCandidates(string $uid, LotteryRuntimeState $state): array
    {
        $catalog = $this->fetchArticleList($uid);
        if (!$catalog->isSuccessful()) {
            return [
                'success' => false,
                'added' => 0,
                'error' => $catalog->code . ' -> ' . $catalog->message,
            ];
        }

        $added = $this->queueCoordinator->registerArticleIds($catalog->articles(), $state);

        return [
            'success' => true,
            'added' => count($added),
            'error' => '',
        ];
    }

    /**
     * @return array{success: bool, added_urls: array<int, string>, error: string}
     */
    public function collectDynamicCandidatesFromArticle(int $cv, string $uid, LotteryRuntimeState $state): array
    {
        $body = $this->fetchArticleBody($cv, $uid);
        $dynamicIds = $this->queueCoordinator->registerDynamicIds($body, $state);

        return [
            'success' => true,
            'added_urls' => array_map(
                static fn(int $dynamicId): string => 'https://t.bilibili.com/' . $dynamicId,
                $dynamicIds,
            ),
            'error' => '',
        ];
    }

    /**
     * @return array{success: bool, added: bool, error: string}
     */
    public function collectReserveCandidate(int $dynamicId, LotteryRuntimeState $state): array
    {
        $response = $this->fetchDynamicDetail($dynamicId);
        if (($response['code'] ?? -1) !== 0) {
            return [
                'success' => false,
                'added' => false,
                'error' => ($response['code'] ?? -1) . ' -> ' . ($response['message'] ?? 'unknown error'),
            ];
        }

        $lottery = $this->queueCoordinator->registerReserveLottery((array)($response['data'] ?? []), $state);
        if ($lottery === null) {
            return [
                'success' => false,
                'added' => false,
                'error' => '未找到预约信息',
            ];
        }

        return [
            'success' => true,
            'added' => true,
            'error' => '',
        ];
    }

    /**
     * @return SpaceArticleCatalog
     */
    protected function fetchArticleList(string $uid): SpaceArticleCatalog
    {
        return ApiArticle::articleCatalog($uid);
    }

    protected function fetchArticleBody(int $cv, string $uid): string
    {
        return ApiArticle::articleBody($cv, $uid);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchDynamicDetail(int $dynamicId): array
    {
        return ApiDetail::detail($dynamicId);
    }
}
