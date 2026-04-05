<?php declare(strict_types=1);

namespace Bhp\Plugin\Builtin\Lottery;

use Bhp\Api\Dynamic\ApiDetail;
use Bhp\Api\Space\ApiArticle;
use Bhp\Request\Request;
use Bhp\Util\Exceptions\RequestException;

class LotteryDiscoveryService
{
    private ?ApiArticle $articleApi = null;
    private ?ApiDetail $detailApi = null;

    public function __construct(
        private readonly LotteryQueueCoordinator $queueCoordinator = new LotteryQueueCoordinator(new LotteryContentAnalyzer()),
        private readonly ?Request $request = null,
    ) {
    }

    /**
     * @return array{success: bool, added: int, error: string}
     */
    public function collectArticleCandidates(string $uid, LotteryRuntimeState $state): array
    {
        $response = $this->fetchArticleList($uid);
        if (($response['code'] ?? -1) !== 0) {
            return [
                'success' => false,
                'added' => 0,
                'error' => ($response['code'] ?? -1) . ' -> ' . ($response['message'] ?? 'unknown error'),
            ];
        }

        $added = $this->queueCoordinator->registerArticleIds($this->extractArticles($response), $state);

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
        try {
            $body = $this->fetchArticleBody($cv, $uid);
        } catch (RequestException $exception) {
            return [
                'success' => false,
                'added_urls' => [],
                'error' => $exception->getMessage(),
            ];
        }

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
     * @return array<string, mixed>
     */
    protected function fetchArticleList(string $uid): array
    {
        return $this->articleApi()->article($uid);
    }

    protected function fetchArticleBody(int $cv, string $uid): string
    {
        return $this->request()->getText('pc', 'https://www.bilibili.com/read/cv' . $cv, [], [
            'referer' => "https://space.bilibili.com/$uid/",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchDynamicDetail(int $dynamicId): array
    {
        return $this->detailApi()->detail($dynamicId);
    }

    /**
     * @param array<string, mixed> $response
     * @return array<int, array<string, mixed>>
     */
    private function extractArticles(array $response): array
    {
        $articles = $response['data']['articles'] ?? [];
        if (!is_array($articles)) {
            return [];
        }

        return array_values(array_filter($articles, static fn(mixed $article): bool => is_array($article)));
    }

    private function request(): Request
    {
        if ($this->request instanceof Request) {
            return $this->request;
        }

        throw new RequestException('LotteryDiscoveryService requires an explicit Request service.');
    }

    private function articleApi(): ApiArticle
    {
        return $this->articleApi ??= new ApiArticle($this->request());
    }

    private function detailApi(): ApiDetail
    {
        return $this->detailApi ??= new ApiDetail($this->request());
    }
}
