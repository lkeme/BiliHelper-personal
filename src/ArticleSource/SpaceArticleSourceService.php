<?php declare(strict_types=1);

namespace Bhp\ArticleSource;

use Bhp\Api\Space\ApiArticle;
use Bhp\Cache\Cache;
use Bhp\Runtime\AppContext;
use Closure;
use RuntimeException;

final class SpaceArticleSourceService
{
    /**
     * @param callable():array<int, array<string, mixed>>|null $articleListLoader
     * @param callable():int|null $clock
     */
    public function __construct(
        private readonly ?AppContext $context = null,
        ?SpaceArticleSourceConfig $config = null,
        ?SpaceArticleCacheStore $cacheStore = null,
        ?SpaceArticleParseService $parseService = null,
        ?callable $articleListLoader = null,
        ?callable $clock = null,
    ) {
        $this->config = $config ?? new SpaceArticleSourceConfig();
        $this->cacheStore = $cacheStore ?? new SpaceArticleCacheStore($this->cache(), $this->config->retentionDays());
        $this->parseService = $parseService ?? new SpaceArticleParseService();
        $this->articleListLoader = $articleListLoader !== null ? Closure::fromCallable($articleListLoader) : null;
        $this->clock = $clock !== null ? Closure::fromCallable($clock) : null;
    }

    private readonly SpaceArticleSourceConfig $config;
    private readonly SpaceArticleCacheStore $cacheStore;
    private readonly SpaceArticleParseService $parseService;
    private readonly ?Closure $articleListLoader;
    private readonly ?Closure $clock;

    /**
     * 处理快照ForToday
     * @return SpaceArticleDailySnapshot
     */
    public function snapshotForToday(): SpaceArticleDailySnapshot
    {
        $now = $this->now();
        $bizDate = $this->config->bizDate($now);
        $existing = $this->cacheStore->load($bizDate);
        if ($existing instanceof SpaceArticleDailySnapshot && $existing->fetchAttempted) {
            return $existing;
        }

        try {
            $snapshot = $this->buildSnapshot($bizDate, $now);
        } catch (\Throwable $throwable) {
            if ($this->context instanceof AppContext) {
                $this->context->log()->recordWarning('文章源: 获取当日稿件失败 ' . $throwable->getMessage());
            }

            return SpaceArticleDailySnapshot::pending($bizDate, $now);
        }

        $this->cacheStore->save($snapshot);

        return $snapshot;
    }

    /**
     * 构建快照
     * @param string $bizDate
     * @param int $fetchedAt
     * @return SpaceArticleDailySnapshot
     */
    private function buildSnapshot(string $bizDate, int $fetchedAt): SpaceArticleDailySnapshot
    {
        $candidates = $this->fetchTodayCandidates($fetchedAt);
        if ($candidates === []) {
            return SpaceArticleDailySnapshot::pending($bizDate, $fetchedAt);
        }

        $reservationCandidate = $this->selectLatestCandidate($candidates, $this->config->rules()['reservation']);
        $lotteryCandidate = $this->selectLatestCandidate($candidates, $this->config->rules()['lottery']);

        if (!$reservationCandidate instanceof SpaceArticleCandidate && !$lotteryCandidate instanceof SpaceArticleCandidate) {
            return SpaceArticleDailySnapshot::pending($bizDate, $fetchedAt);
        }

        $reservationIds = [];
        $lotteryIds = [];
        $reservationTitle = $reservationCandidate?->title;
        $lotteryTitle = $lotteryCandidate?->title;

        if ($reservationCandidate instanceof SpaceArticleCandidate) {
            $reservationIds = $this->parseService->extractIds(
                ['summary' => $reservationCandidate->summary],
                $this->config->rules()['reservation'],
            );
        }

        if ($lotteryCandidate instanceof SpaceArticleCandidate) {
            $lotteryIds = $this->parseService->extractIds(
                ['summary' => $lotteryCandidate->summary],
                $this->config->rules()['lottery'],
            );
        }

        return new SpaceArticleDailySnapshot(
            $bizDate,
            true,
            $fetchedAt,
            $reservationCandidate?->cvId,
            $reservationTitle,
            $lotteryCandidate?->cvId,
            $lotteryTitle,
            $reservationIds,
            $lotteryIds,
        );
    }

    /**
     * @return SpaceArticleCandidate[]
     */
    private function fetchTodayCandidates(int $now): array
    {
        $startOfDay = $this->config->startOfDayTimestamp($now);
        $candidates = [];
        foreach ($this->loadArticleList() as $row) {
            $candidate = SpaceArticleCandidate::fromArticleRow($row);
            if (!$candidate instanceof SpaceArticleCandidate) {
                continue;
            }

            if ($candidate->publishTime < $startOfDay) {
                continue;
            }

            $candidates[] = $candidate;
        }

        return $candidates;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadArticleList(): array
    {
        if (is_callable($this->articleListLoader)) {
            $loaded = ($this->articleListLoader)();

            return is_array($loaded) ? array_values(array_filter($loaded, 'is_array')) : [];
        }

        $response = $this->articleApi()->article(
            $this->config->hostMid(),
            $this->config->articlePage(),
            $this->config->articlePageSize(),
        );
        if (($response['code'] ?? 0) !== 0) {
            $code = $response['code'] ?? 'unknown';
            $message = is_string($response['message'] ?? null) ? $response['message'] : 'unknown';

            throw new RuntimeException("space.article 拉取失败 {$code} -> {$message}");
        }
        $articles = $response['data']['articles'] ?? null;

        return is_array($articles) ? array_values(array_filter($articles, 'is_array')) : [];
    }

    /**
     * @param SpaceArticleCandidate[] $candidates
     */
    private function selectLatestCandidate(array $candidates, SpaceArticleRule $rule): ?SpaceArticleCandidate
    {
        $matched = array_values(array_filter(
            $candidates,
            static fn(SpaceArticleCandidate $candidate): bool => str_starts_with($candidate->title, $rule->titlePrefix),
        ));
        if ($matched === []) {
            return null;
        }

        usort($matched, static function (SpaceArticleCandidate $left, SpaceArticleCandidate $right): int {
            return $right->publishTime <=> $left->publishTime;
        });

        return $matched[0];
    }

    /**
     * 处理文章API
     * @return ApiArticle
     */
    private function articleApi(): ApiArticle
    {
        return new ApiArticle($this->request());
    }

    /**
     * 处理请求
     * @return \Bhp\Request\Request
     */
    private function request(): \Bhp\Request\Request
    {
        if ($this->context instanceof AppContext) {
            return $this->context->request();
        }

        throw new RuntimeException('SpaceArticleSourceService requires AppContext when no loaders are provided.');
    }

    /**
     * 处理缓存
     * @return Cache
     */
    private function cache(): Cache
    {
        if ($this->context instanceof AppContext) {
            return $this->context->cache();
        }

        throw new RuntimeException('SpaceArticleSourceService requires Cache when no cache store is provided.');
    }

    /**
     * 获取当前时间
     * @return int
     */
    private function now(): int
    {
        if (is_callable($this->clock)) {
            return (int)($this->clock)();
        }

        return time();
    }
}
